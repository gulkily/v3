<?php

declare(strict_types=1);
namespace ForumRewrite\Images;

use RuntimeException;

/** Content identity, captured while holding the instance writer lock. */
final class ExportIdentity
{
    /**
     * Cheap freshness identity: the committed revision uniquely identifies tracked
     * content, a whole-tree `git status --porcelain` signal catches any dirty or
     * untracked file that would be archived (not just records/), the image
     * inventory is trusted by its content-addressed filenames (never re-hashed
     * here), and the small fixed generator-source list is digested. This performs
     * no SHA-256 over repository or image bytes, so it stays O(git status) rather
     * than O(repository bytes) — the previous per-file hash was the cause of the
     * 120s export timeout on large instances.
     *
     * @param array<string, array{sha256:string,type:string,bytes:int,width:int,height:int}> $inventory
     */
    public static function capture(string $repository, string $revision, array $inventory): string
    {
        $porcelain = self::porcelainStatus($repository);
        // Image identity from content-addressed filenames + sizes; no re-hash.
        $images = [];
        foreach ($inventory as $name => $entry) {
            $images[(string) $name] = (int) ($entry['bytes'] ?? 0);
        }
        ksort($images);
        $generators = [];
        foreach ([__FILE__, __DIR__ . '/ImageBackup.php', __DIR__ . '/RepositorySnapshot.php',
            __DIR__ . '/RepositoryArchiveReadme.php', __DIR__ . '/../Scoring/FastmodScoreSnapshot.php', __DIR__ . '/../Scoring/FastmodScoreTransfer.php', __DIR__ . '/ImageReference.php',
            __DIR__ . '/../Canonical/CanonicalRecordRepository.php', __DIR__ . '/../Canonical/LegacyPostTimestamp.php',
            dirname(__DIR__, 3) . '/resources/repository-archive/README.md'] as $source) {
            $generators[] = self::digest($source);
        }
        return hash('sha256', json_encode(['export-v2', $revision, $porcelain, $images, $generators], JSON_THROW_ON_ERROR));
    }

    /**
     * Whole-tree porcelain status (excluding nothing the archive includes), so an
     * uncommitted change to any archived file — not only records/ — yields a
     * distinct identity. Sorted for determinism; the content is a short status
     * listing, not file bytes.
     */
    private static function porcelainStatus(string $repository): string
    {
        $command = 'git -C ' . escapeshellarg($repository) . ' status --porcelain';
        exec($command . ' 2>&1', $output, $status);
        if ($status !== 0) {
            throw new RuntimeException('Cannot verify export source working tree.');
        }
        sort($output);
        return implode("\n", $output);
    }

    private static function digest(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) throw new RuntimeException('Cannot verify export source.');
        return $hash;
    }
}
