<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelCandidateBuilder;
use ForumRewrite\Retraction\PublicationGuard;
use ForumRewrite\ReadModel\ReadModelMetadata;
use PDO;
use RuntimeException;

final class HighTrafficRenderInput
{
    /** Maintenance only. Never call from an HTTP handler or render worker. */
    public static function build(array $context, string $liveDatabase, ?\Closure $progressReporter = null): string
    {
        HighTrafficPublication::validate($context);
        $path = self::sharedPath($liveDatabase);
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) {
            throw new RuntimeException('Cannot create prepared-model directory: ' . dirname($path));
        }
        $lock = fopen($path . '.lock', 'c');
        if ($lock === false) throw new RuntimeException('Cannot open prepared-model lock: ' . $path . '.lock');
        $candidate = $path . '.candidate-' . bin2hex(random_bytes(8));
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('High-traffic model preparation is already running; retry after it finishes.');
            if ($context['state']['absent'] === []) {
                try {
                    if (!is_file($liveDatabase) || (new \ForumRewrite\ReadModel\ReadModelStaleMarker($liveDatabase))->exists()) {
                        throw new RuntimeException('Live model is missing or marked stale.');
                    }
                    ReadModelCandidateBuilder::assertValid($context['repository'], $liveDatabase);
                    PublicationGuard::begin($liveDatabase, $context['repository']);
                    $progressReporter?->__invoke('Reusing current live read model');
                    return $liveDatabase;
                } catch (\Throwable) {
                    $candidate = (new ReadModelCandidateBuilder($context['repository'], $liveDatabase, 'high_traffic_prepare', $progressReporter))->build();
                    try {
                        HighTrafficPublication::validate($context);
                        PublicationGuard::begin($candidate, $context['repository']);
                        (new \ForumRewrite\ReadModel\ReadModelCandidatePromoter($context['repository'], $liveDatabase, $progressReporter))->promote($candidate);
                    } finally {
                        if (is_file($candidate)) unlink($candidate);
                    }
                    return $liveDatabase;
                }
            }
            if (is_file($path)) {
                try {
                    $pdo = self::open($path);
                    $current = self::matches($pdo, $context);
                    if ($current) {
                        ReadModelCandidateBuilder::assertValid($context['repository'], $path);
                        PublicationGuard::beginSnapshot($path);
                    }
                }
                catch (\Throwable) { $current = false; }
                finally { $pdo = null; }
                if ($current) {
                    $progressReporter?->__invoke('Reusing prepared model; canonical inputs are unchanged');
                    return $path;
                }
            }
            $progressReporter?->__invoke('Preparing shared model once; page rendering never performs this rebuild');
            (new ReadModelBuilder(
                $context['repository'], $candidate, new CanonicalRecordRepository($context['repository']),
                'high_traffic_absent_targets', $progressReporter, publicationExclusions: $context['state']['absent'],
            ))->rebuild();
            ReadModelCandidateBuilder::assertValid($context['repository'], $candidate);
            PublicationGuard::beginSnapshot($candidate);
            $pdo = new PDO('sqlite:' . $candidate);
            $pdo->prepare('INSERT INTO metadata (key, value) VALUES (?, ?)')->execute([
                'high_traffic_publication_identity', HighTrafficPublication::identity($context),
            ]);
            $pdo->prepare('INSERT INTO metadata (key, value) VALUES (?, ?)')->execute([
                'high_traffic_safety_identity', self::safetyIdentity($context),
            ]);
            $pdo = null;
            HighTrafficPublication::validate($context);
            if (!chmod($candidate, 0600) || !rename($candidate, $path)) throw new RuntimeException('Cannot publish prepared model: ' . $path);
            $progressReporter?->__invoke('Prepared shared model: ' . $path);
            return $path;
        } finally {
            if (is_file($candidate)) unlink($candidate);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Rendering only copies a validated model. It must never index canonical records. */
    public static function prepare(array $context, string $liveDatabase, string $directory, ?\Closure $progressReporter = null): string
    {
        HighTrafficPublication::validate($context);
        if ($context['state']['absent'] === []) return $liveDatabase;
        $source = self::sharedPath($liveDatabase);
        $recovery = 'Run ./v3 high-traffic prepare with the same repository/database settings, then retry ./v3 high-traffic refresh /. No rebuild was started by this render.';
        if (!is_file($source)) throw new RuntimeException('Prepared high-traffic model is missing: ' . $source . '. ' . $recovery);
        try { $pdo = self::open($source); $safe = self::matches($pdo, $context, exact: false); }
        catch (\Throwable $error) { throw new RuntimeException('Cannot validate prepared model ' . $source . ': ' . $error->getMessage() . '. ' . $recovery, 0, $error); }
        if (!$safe) throw new RuntimeException('Prepared high-traffic model is unsafe to reuse: retraction evidence changed. ' . $recovery);
        if (!self::matches($pdo, $context)) $progressReporter?->__invoke('Prepared model predates current records; run ./v3 high-traffic prepare to include latest content');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Cannot create render-model workspace.');
        $path = $directory . '/read.sqlite3';
        if (file_exists($path)) throw new RuntimeException('High-traffic candidate already exists; retry with a fresh workspace.');
        $progressReporter?->__invoke('copying prepared SQLite model (no indexing)');
        // VACUUM INTO gives a consistent copy, including committed WAL data, without modifying the source.
        $pdo->exec('VACUUM INTO ' . $pdo->quote($path));
        $pdo = null;
        if (!chmod($path, 0600)) throw new RuntimeException('Cannot protect private render model permissions.');
        ReadModelCandidateBuilder::assertValid($context['repository'], $path, requireCurrentHead: false);
        PublicationGuard::beginSnapshot($path);
        HighTrafficPublication::validate($context);
        return $path;
    }

    public static function sharedPath(string $liveDatabase): string
    {
        return $liveDatabase . '.high-traffic.sqlite3';
    }

    private static function safetyIdentity(array $context): string
    {
        // Routine content can be stale, as with shared HTML. Retractions/absence cannot.
        // Source-removal generation is checked separately by PublicationGuard.
        return hash('sha256', json_encode([$context['repository'], $context['state']], JSON_THROW_ON_ERROR));
    }

    private static function open(string $path): PDO
    {
        return new PDO('sqlite:file:' . $path . '?mode=ro', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    private static function matches(PDO $pdo, array $context, bool $exact = true): bool
    {
        $metadata = ReadModelMetadata::readMetadata($pdo);
        return ReadModelMetadata::hasExpectedSchemaIdentity($metadata)
            && ($metadata['repository_root'] ?? null) === $context['repository']
            && ($metadata['high_traffic_safety_identity'] ?? null) === self::safetyIdentity($context)
            && (!$exact || ($metadata['high_traffic_publication_identity'] ?? null) === HighTrafficPublication::identity($context));
    }
}
