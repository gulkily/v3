<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Port\PortFile;
use ForumRewrite\Write\LocalWriteService;

require __DIR__ . '/../autoload.php';

/** A port file becomes one thread, every comment under its parent, in one commit. */
final class ThreadPortWriteTest
{
    public function testSmallPortWritesOneCommitWithParentsAndLabel(): void
    {
        [$root, $service] = $this->environment();
        $before = $this->commitCount($root);
        $result = $service->createPortedThread(PortFile::parse($this->portText(3)), 'openpgp:abababababababababababababababababababab');
        threadPortWriteAssertSame(false, $result['existing']);
        threadPortWriteAssertSame(4, $result['post_count']);
        threadPortWriteAssertSame($before + 1, $this->commitCount($root));
        $this->assertParents($root, $result['thread_id'], 3);

        $pdo = new PDO('sqlite:' . $root . '.sqlite3');
        $labels = (string) $pdo->query("SELECT thread_labels_json FROM threads WHERE root_post_id = " . $pdo->quote($result['thread_id']))->fetchColumn();
        threadPortWriteAssertContains('ported', $labels);
        $byline = (string) $pdo->query("SELECT author_label FROM posts WHERE ported_from = 'https://news.ycombinator.com/item?id=1001'")->fetchColumn();
        threadPortWriteAssertSame('user1 on Hacker News', $byline);
    }

    public function testLargePortStaysOneCommitAndRepeatOpensTheSameThread(): void
    {
        [$root, $service] = $this->environment();
        $before = $this->commitCount($root);
        $started = microtime(true);
        $result = $service->createPortedThread(PortFile::parse($this->portText(300)), 'openpgp:abababababababababababababababababababab');
        $seconds = microtime(true) - $started;
        threadPortWriteAssertSame($before + 1, $this->commitCount($root));
        $this->assertParents($root, $result['thread_id'], 300);
        if ($seconds > 30) {
            throw new RuntimeException('300-comment port took ' . round($seconds, 1) . 's');
        }

        $again = $service->createPortedThread(PortFile::parse($this->portText(300)), 'openpgp:abababababababababababababababababababab');
        threadPortWriteAssertSame(true, $again['existing']);
        threadPortWriteAssertSame($result['thread_id'], $again['thread_id']);
        threadPortWriteAssertSame($before + 1, $this->commitCount($root));

        // A full rebuild keeps the same parents and the port order.
        $database = $root . '.sqlite3';
        $order = static fn (): array => (new PDO('sqlite:' . $database))->query("SELECT ported_from FROM posts WHERE thread_id = " . (new PDO('sqlite:' . $database))->quote($result['thread_id']) . " ORDER BY sequence_number")->fetchAll(PDO::FETCH_COLUMN);
        $incremental = $order();
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
        threadPortWriteAssertSame($incremental, $order());
        $this->assertParents($root, $result['thread_id'], 300);
    }

    public function testRefusedPortWritesNothingAndRefusedCharactersBecomeQuestionMarks(): void
    {
        [$root, $service] = $this->environment();
        $before = $this->commitCount($root);
        $broken = str_replace("Parent: 1001\n", "Parent: 999999\n", $this->portText(3));
        try {
            $service->createPortedThread(PortFile::parse($broken), 'openpgp:abababababababababababababababababababab');
            throw new RuntimeException('Expected a refusal');
        } catch (RuntimeException $exception) {
            threadPortWriteAssertContains('replies to a comment that is not in the port', $exception->getMessage());
        }
        threadPortWriteAssertSame($before, $this->commitCount($root));

        $emoji = str_replace('Comment 2 text', "Nice \u{1F600} work\n%%% not a separator\n%%literal", $this->portText(3));
        $parsed = PortFile::parse(PortFile::parse($emoji)->toText());
        threadPortWriteAssertContains("%% not a separator\n%literal", $parsed->items[2]->text);
        $result = $service->createPortedThread($parsed, 'openpgp:abababababababababababababababababababab');
        $body = (string) (new PDO('sqlite:' . $root . '.sqlite3'))->query("SELECT body FROM posts WHERE ported_from = 'https://news.ycombinator.com/item?id=1002'")->fetchColumn();
        threadPortWriteAssertContains('Nice ? work', $body);
        threadPortWriteAssertSame(false, $result['existing']);
    }

    /** A story with $comments comments: each comment replies to the root or to an earlier comment. */
    private function portText(int $comments): string
    {
        $text = "Port-Format: hn-v1\nThread-Title: Test story\nThread-Link: https://news.ycombinator.com/item?id=1000\n"
            . "%%\nItem: 1000\nAuthor: op\nCreated-At: 2026-01-01T00:00:00Z\nTitle: Test story\nLink: https://example.com/story\n\nStory text\n";
        for ($i = 1; $i <= $comments; $i++) {
            $parent = $i <= 2 ? 1000 : 1000 + intdiv($i, 2);
            $text .= "%%\nItem: " . (1000 + $i) . "\nParent: {$parent}\nAuthor: user{$i}\nCreated-At: 2026-01-01T00:" . sprintf('%02d', $i % 60) . ":00Z\n\nComment {$i} text\n";
        }
        return $text;
    }

    private function assertParents(string $root, string $threadId, int $comments): void
    {
        $pdo = new PDO('sqlite:' . $root . '.sqlite3');
        $rows = $pdo->query('SELECT post_id, parent_id, ported_from FROM posts WHERE thread_id = ' . $pdo->quote($threadId))->fetchAll(PDO::FETCH_ASSOC);
        threadPortWriteAssertSame($comments + 1, count($rows));
        $sourceByPost = array_column($rows, 'ported_from', 'post_id');
        foreach ($rows as $row) {
            $item = (int) substr((string) $row['ported_from'], strlen('https://news.ycombinator.com/item?id='));
            if ($item === 1000) {
                threadPortWriteAssertSame($threadId, $row['post_id']);
                continue;
            }
            $i = $item - 1000;
            $expected = $i <= 2 ? 1000 : 1000 + intdiv($i, 2);
            threadPortWriteAssertSame('https://news.ycombinator.com/item?id=' . $expected, $sourceByPost[$row['parent_id']] ?? null);
        }
    }

    private function commitCount(string $root): int
    {
        return (int) trim((string) shell_exec('cd ' . escapeshellarg($root) . ' && git rev-list --count HEAD'));
    }

    /** @return array{0: string, 1: LocalWriteService} */
    private function environment(): array
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-thread-port-write-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/fixtures/parity_minimal_v1', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $target = $root . '/' . $items->getSubPathName();
            $item->isDir() ? (is_dir($target) || mkdir($target, 0777, true)) : copy($item->getPathname(), $target);
        }
        foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"', 'git add .', 'git -c commit.gpgsign=false commit -m init'] as $command) {
            exec('cd ' . escapeshellarg($root) . ' && ' . $command . ' 2>&1', $output, $status);
            if ($status !== 0) {
                throw new RuntimeException('Command failed: ' . $command);
            }
        }
        mkdir($root . '-public', 0777, true);
        $application = new Application(dirname(__DIR__), $root, $root . '.sqlite3', $root . '-public');
        ob_start();
        try {
            $application->handle('GET', '/');
        } finally {
            ob_end_clean();
        }
        return [$root, new LocalWriteService($root, $root . '.sqlite3', $root . '-public', new CanonicalRecordRepository($root))];
    }
}

function threadPortWriteAssertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function threadPortWriteAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Expected to find ' . var_export($needle, true) . ' in ' . substr($haystack, 0, 600));
    }
}
