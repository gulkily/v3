<?php

declare(strict_types=1);
namespace ForumRewrite\Import;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Scoring\{FastmodScoreTransfer, FastScoreContextFactory, FastScoreDatabaseConfig, FastScoringConfig, FastmodSelection, SqliteFastScoreStore};
use ForumRewrite\Support\PrivateConfig;
use PDO;
use RuntimeException;

/** Shared compatibility rules for previews and recovered imports; never calls a provider. */
final class FastmodScoreImport
{
    public function __construct(private readonly string $projectRoot) {}

    public function databasePath(): string
    {
        $path = FastScoreDatabaseConfig::path($this->projectRoot, PrivateConfig::load($this->projectRoot));
        return str_starts_with($path, '/') ? $path : getcwd() . '/' . $path;
    }

    public function read(string $source, array $exclusions = []): array
    {
        $path = $source . '/' . FastmodScoreTransfer::PATH;
        if (isset($exclusions[FastmodScoreTransfer::PATH])) return ['rows' => [], 'status' => 'rejected', 'reason' => 'Score supplement exceeds size limit.'];
        if (!file_exists($path) && !is_link($path)) return ['rows' => [], 'status' => 'absent'];
        try {
            if (!is_file($path) || is_link($path) || filesize($path) > FastmodScoreTransfer::MAX_BYTES) throw new RuntimeException('Invalid score supplement file.');
            $text = file_get_contents($path, false, null, 0, FastmodScoreTransfer::MAX_BYTES + 1);
            if ($text === false) throw new RuntimeException('Cannot read score supplement.');
            return ['rows' => FastmodScoreTransfer::decode($text), 'status' => 'valid'];
        } catch (\InvalidArgumentException|RuntimeException $error) {
            return ['rows' => [], 'status' => 'rejected', 'reason' => $error->getMessage()];
        }
    }

    /** IDs are authorized only by accepted/identical posts in this content plan. */
    public function hashes(array $entries, string $source, string $destination, bool $applied = false): array
    {
        $hashes = []; $destinationPaths = null; $factory = new FastScoreContextFactory(static fn ($id) => null);
        $sourceRecords = new CanonicalRecordRepository($source, requireLegacyTimestampMetadata: true, signatureKeyFallbackRoot: $destination);
        $destinationRecords = new CanonicalRecordRepository($destination);
        foreach ($entries as $path => $entry) {
            if (!str_starts_with($path, 'records/posts/') || !str_ends_with($path, '.txt') || !in_array($entry['state'], ['import', 'duplicate'], true)) continue;
            $root = $applied || $entry['state'] === 'duplicate' ? $destination : $source;
            if ($root === $destination && !is_file($root . '/' . $path) && $entry['state'] === 'duplicate') {
                // Identical content can already exist under a dated/flat canonical path.
                if ($destinationPaths === null) {
                    $destinationPaths = [];
                    foreach (ContentImportPlanner::files($destination) as $candidate) {
                        if (str_starts_with($candidate, 'records/posts/') && str_ends_with($candidate, '.txt')) $destinationPaths[basename($candidate)][] = $candidate;
                    }
                }
                foreach ($destinationPaths[basename($path)] ?? [] as $candidate) {
                    if (hash_file('sha256', $root . '/' . $candidate) === $entry['hash']) { $path = $candidate; break; }
                }
            }
            // A removal tombstone or changed duplicate never authorizes score transfer.
            if (!is_file($root . '/' . $path) || is_link($root . '/' . $path) || hash_file('sha256', $root . '/' . $path) !== $entry['hash']) continue;
            $post = ($root === $destination ? $destinationRecords : $sourceRecords)->loadPost($path);
            $hashes[$post->postId] = $factory->contentHashForPost(['post_id' => $post->postId, 'subject' => $post->subject, 'body' => $post->body]);
        }
        return $hashes;
    }

    public function process(array $supplement, array $hashes, bool $preview): array
    {
        $counts = ['imported' => 0, 'existing' => 0, 'duplicate' => 0, 'incompatible' => 0];
        if ($supplement['status'] !== 'valid' || $supplement['rows'] === []) return ['status' => $supplement['status'], 'reason' => $supplement['reason'] ?? '', 'counts' => $counts];
        $config = FastScoringConfig::fromPrivateConfig(PrivateConfig::load($this->projectRoot));
        $composition = (new FastmodSelection($this->projectRoot))->composition($config);
        // Incompatible supplements must not initialize or migrate a private store.
        $ids = $composition['ids']; sort($ids);
        $eligible = false;
        foreach ($supplement['rows'] as $row) {
            $names = array_keys($row['scores']); sort($names);
            if (($hashes[$row['post_id']] ?? null) === $row['content_hash'] && $row['rubric_revision'] === $composition['revision'] && $names === $ids) { $eligible = true; break; }
        }
        if (!$eligible) { $counts['incompatible'] = count($supplement['rows']); return ['status' => 'valid', 'counts' => $counts]; }
        $path = $this->databasePath();
        if ($preview) {
            $pdo = is_file($path) ? new PDO('sqlite:file:' . str_replace(['%', '?', '#'], ['%25', '%3f', '%23'], $path) . '?mode=ro') : new PDO('sqlite::memory:');
            $hasTable = $pdo->query("SELECT name FROM sqlite_master WHERE name = 'post_fast_scores'")->fetchColumn() !== false;
            if (!$hasTable) $pdo = new PDO('sqlite::memory:');
            $store = new SqliteFastScoreStore($pdo, !$hasTable);
        } else {
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) throw new RuntimeException('Cannot create private score directory.');
            $store = new SqliteFastScoreStore(new PDO('sqlite:' . $path));
            chmod($path, 0600);
        }
        return ['status' => 'valid', 'counts' => $store->importScoredRows($supplement['rows'], $hashes, $composition['revision'], $composition['ids'], $preview)];
    }
}
