<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\ReactionRecordParser;
use ForumRewrite\ReadModel\IncrementalReadModelUpdater;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class ReactionRecordFormatParityTest
{
    private const APPROVED = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    private const OTHER = 'openpgp:1111111111111111111111111111111111111111';

    /**
     * kind, record ID, created, target, tags, author, label source.
     */
    private const EVENTS = [
        ['thread', 'thread-label-p1', '2026-04-15T15:31:00Z', 'root-001', ['like', 'answered'], self::APPROVED, null],
        ['thread', 'thread-label-p2', '2026-04-15T15:32:00Z', 'root-001', ['like'], self::APPROVED, null],
        ['thread', 'thread-label-p3', '2026-04-15T15:33:00Z', 'root-001', ['bug'], null, null],
        ['thread', 'thread-label-p4', '2026-04-15T15:34:00Z', 'root-001', ['topic'], self::OTHER, 'root-001'],
        ['thread', 'thread-label-p5', '2026-04-15T15:35:00Z', 'reply-001', ['not-a-root'], null, null],
        ['post', 'post-reaction-p1', '2026-04-15T15:36:00Z', 'reply-001', ['flag'], self::APPROVED, null],
        ['post', 'post-reaction-p2', '2026-04-15T15:37:00Z', 'reply-001', ['flag'], self::APPROVED, null],
        ['post', 'post-reaction-p3', '2026-04-15T15:38:00Z', 'root-001', ['like'], self::OTHER, null],
        ['post', 'post-reaction-p4', '2026-04-15T15:39:00Z', 'root-001', ['like'], null, null],
    ];

    public function testMixedFormatsBuildTheSameReadModelAsLegacy(): void
    {
        $legacy = $this->rebuild($this->repository(static fn (int $i): bool => false));
        $unified = $this->rebuild($this->repository(static fn (int $i): bool => true));
        $mixed = $this->rebuild($this->repository(static fn (int $i): bool => $i % 2 === 1));

        assertSame($legacy, $unified);
        assertSame($legacy, $mixed);
        assertTrue(str_contains(implode("\n", $legacy['threads']), 'answered'));
        assertTrue(in_array('["thread_label_invalid_count","1"]', $legacy['metadata'], true));
    }

    public function testIncrementalUpdatesMatchRebuildForUnifiedRecords(): void
    {
        $root = $this->repository(static fn (int $i): bool => false, false);
        $database = $this->databasePath('incremental');
        (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root), 'parity_incremental'))->rebuild();
        $updater = new IncrementalReadModelUpdater($database, $root);
        foreach (self::EVENTS as $i => $event) {
            if ($event[3] === 'reply-001' && $event[0] === 'thread') {
                continue; // Invalid thread targets are covered by the full rebuild test.
            }
            $this->writeEvent($root, $event, $i % 2 === 0);
            $event[0] === 'thread'
                ? $updater->applyThreadLabelWrite($event[3], 'deadbeef')
                : $updater->applyPostReactionWrite($event[3], 'deadbeef');
        }

        $rebuilt = $this->rebuild($root);
        $incremental = $this->dump($database);
        foreach (['threads', 'posts', 'viewer_tags', 'reaction_signatures'] as $table) {
            if (isset($rebuilt[$table])) {
                assertSame($rebuilt[$table], $incremental[$table]);
            }
        }
    }

    public function testBrowserAndServerBuildIdenticalSignedBytes(): void
    {
        $script = <<<'NODE'
global.window = {};
global.document = { addEventListener(){}, querySelectorAll(){ return []; }, querySelector(){ return null; } };
const fs = require('fs');
require('vm').runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
const build = window.ForumThreadReactions.reactionRecord;
process.stdout.write(JSON.stringify([
  build('thread', 'thread-label-0a1b', '2026-10-30T15:30:00Z', 'root-001', 'like', 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'),
  build('post', 'post-reaction-0a1b', '2026-10-30T15:30:00Z', 'reply-001', 'flag', 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954'),
]));
NODE;
        $output = [];
        $exitCode = 0;
        exec(sprintf('node -e %s %s 2>&1', escapeshellarg($script), escapeshellarg(__DIR__ . '/../public/assets/thread_reactions.js')), $output, $exitCode);
        assertSame(0, $exitCode);
        [$thread, $post] = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame(ReactionRecordParser::build('thread', 'thread-label-0a1b', '2026-10-30T15:30:00Z', 'root-001', ['like'], self::APPROVED), $thread);
        assertSame(ReactionRecordParser::build('post', 'post-reaction-0a1b', '2026-10-30T15:30:00Z', 'reply-001', ['flag'], self::APPROVED), $post);
        assertSame('reply-001', (new \ForumRewrite\Canonical\PostReactionRecordParser())->parse($post)->postId);
    }

    /**
     * @param callable(int):bool $unified
     */
    private function repository(callable $unified, bool $withEvents = true): string
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-reaction-parity-' . bin2hex(random_bytes(6));
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $root);
        @mkdir($root . '/records/post-reactions', 0777, true);
        if ($withEvents) {
            foreach (self::EVENTS as $i => $event) {
                $this->writeEvent($root, $event, $unified($i));
            }
        }

        return $root;
    }

    private function writeEvent(string $root, array $event, bool $unified): void
    {
        [$kind, $id, $created, $target, $tags, $author, $source] = $event;
        $sourceHash = $source === null ? null : str_repeat('b', 64);
        if ($unified) {
            $bytes = ReactionRecordParser::build($kind, $id, $created, $target, $tags, $author,
                sourcePostId: $source, sourcePostSha256: $sourceHash);
        } else {
            $bytes = "Record-ID: {$id}\nCreated-At: {$created}\n"
                . ($kind === 'thread' ? 'Thread-ID' : 'Post-ID') . ": {$target}\nOperation: add\n"
                . ($kind === 'thread' ? 'Labels' : 'Tags') . ': ' . implode(' ', $tags) . "\n"
                . ($author !== null ? "Author-Identity-ID: {$author}\n" : '')
                . ($source !== null ? "Source-Post-ID: {$source}\nSource-Post-SHA256: {$sourceHash}\n" : '')
                . "\n";
        }
        file_put_contents($root . '/records/' . ($kind === 'thread' ? 'thread-labels/' : 'post-reactions/') . $id . '.txt', $bytes);
    }

    /**
     * @return array<string,list<array<string,mixed>>>
     */
    private function rebuild(string $root): array
    {
        $database = $this->databasePath('rebuild');
        (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root), 'parity_rebuild'))->rebuild();

        return $this->dump($database);
    }

    /**
     * @return array<string,list<array<string,mixed>>>
     */
    private function dump(string $database): array
    {
        $pdo = new PDO('sqlite:' . $database);
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        $dump = [];
        foreach ($tables as $table) {
            $rows = $pdo->query('SELECT * FROM "' . $table . '"')->fetchAll(PDO::FETCH_NUM);
            if ($table === 'metadata') {
                $rows = array_values(array_filter($rows, static fn (array $row): bool => !preg_match('/(_at|_ms|_seconds|commit|duration|elapsed|timing|version|build|fingerprint|repository_root)/', (string) $row[0])));
            }
            $rows = array_map(static fn (array $row): string => json_encode($row), $rows);
            sort($rows);
            $dump[$table] = $rows;
        }

        return $dump;
    }

    private function databasePath(string $label): string
    {
        return sys_get_temp_dir() . '/forum-rewrite-reaction-parity-' . $label . '-' . bin2hex(random_bytes(6)) . '.sqlite3';
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }
                continue;
            }
            copy($item->getPathname(), $targetPath);
        }
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting that values are identical. Expected '
                . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

if (!function_exists('assertTrue')) {
    function assertTrue(bool $condition): void
    {
        if (!$condition) {
            throw new RuntimeException('Failed asserting that condition is true.');
        }
    }
}
