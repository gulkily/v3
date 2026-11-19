<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\PostRecordParser;

require __DIR__ . '/../autoload.php';

/** Ported-* headers: a ported post names its source, its source author and who ported it. */
final class ThreadPortRecordTest
{
    private const PORTED = "Ported-From: https://news.ycombinator.com/item?id=8863\nPorted-Author: dhouston\nPorted-Created-At: 2007-04-04T19:16:40Z\nPorted-By: openpgp:abababababababababababababababababababab\n";

    public function testParserReadsPortedHeadersOnARootAndAReply(): void
    {
        $root = (new PostRecordParser())->parse("Post-ID: t1\nCreated-At: 2026-11-19T12:00:00Z\nBoard-Tags: general\nSubject: My YC app\n" . self::PORTED . "\nhello\n");
        threadPortAssertSame('dhouston', $root->ported?->author);
        threadPortAssertSame('https://news.ycombinator.com/item?id=8863', $root->ported?->from);
        $reply = (new PostRecordParser())->parse("Post-ID: r1\nCreated-At: 2026-11-19T12:00:00Z\nBoard-Tags: general\nThread-ID: t1\nParent-ID: t1\n" . str_replace('8863', '8869', self::PORTED) . "\nhi\n");
        threadPortAssertSame('openpgp:abababababababababababababababababababab', $reply->ported?->by);
        threadPortAssertSame(null, (new PostRecordParser())->parse("Post-ID: t2\nCreated-At: 2026-11-19T12:00:00Z\nBoard-Tags: general\n\nplain\n")->ported);
    }

    public function testParserRejectsPartialOrWrongSitePortedHeaders(): void
    {
        $base = "Post-ID: t1\nCreated-At: 2026-11-19T12:00:00Z\nBoard-Tags: general\n";
        $this->assertRejected($base . "Ported-Author: dhouston\n\nx\n", 'must all be present or all absent');
        $this->assertRejected($base . str_replace('news.ycombinator.com', 'evil.example', self::PORTED) . "\nx\n", 'Ported-From must be a Hacker News item address');
        $this->assertRejected($base . str_replace('dhouston', 'd houston', self::PORTED) . "\nx\n", 'Ported-Author must be');
        $this->assertRejected($base . str_replace('2007-04-04T19:16:40Z', 'yesterday', self::PORTED) . "\nx\n", 'Ported-Created-At must use');
    }

    public function testReadModelKeepsPortedColumnsAcrossRebuildAndFromTheLastSchema(): void
    {
        $root = $this->environment();
        $database = $root . '.sqlite3';
        $application = new Application(dirname(__DIR__), $root, $database, $root . '-public');
        $this->get($application, '/');
        $columns = static fn (): array => (new PDO('sqlite:' . $database))
            ->query("SELECT post_id, ported_author FROM posts WHERE post_id IN ('ported-root', 'ported-reply') ORDER BY post_id")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        threadPortAssertSame(['ported-reply' => 'pg', 'ported-root' => 'dhouston'], $columns());

        // A version 55 database (no columns) is rebuilt at startup before the columns are read.
        $pdo = new PDO('sqlite:' . $database);
        foreach (['ported_from', 'ported_author', 'ported_created_at', 'ported_by'] as $column) {
            $pdo->exec('ALTER TABLE posts DROP COLUMN ' . $column);
        }
        $pdo->exec("UPDATE metadata SET value = '55' WHERE key = 'schema_version'");
        $pdo = null;
        $this->get(new Application(dirname(__DIR__), $root, $database, $root . '-public'), '/');
        threadPortAssertSame(\ForumRewrite\ReadModel\ReadModelMetadata::SCHEMA_VERSION, (new PDO('sqlite:' . $database))->query("SELECT value FROM metadata WHERE key = 'schema_version'")->fetchColumn());
        threadPortAssertSame(['ported-reply' => 'pg', 'ported-root' => 'dhouston'], $columns());
    }

    public function testPortedPostsShowTheirSourceAuthorAndOnlyTheRootReachesRecent(): void
    {
        $root = $this->environment();
        $application = new Application(dirname(__DIR__), $root, $root . '.sqlite3', $root . '-public');
        $thread = $this->get($application, '/threads/ported-root');
        threadPortAssertContains('<span class="ported-author">dhouston on <a href="https://news.ycombinator.com/item?id=8863" rel="nofollow noopener">Hacker News</a></span>', $thread);
        threadPortAssertContains('<span class="ported-author">pg on <a href="https://news.ycombinator.com/item?id=8869"', $thread);
        threadPortAssertSame(false, str_contains($thread, '/user/dhouston') || str_contains($thread, '/profiles/dhouston'));

        $recent = $this->get($application, '/recent/');
        threadPortAssertContains('My YC app: Dropbox', $recent);
        threadPortAssertSame(false, str_contains($recent, 'ported-reply'));
        threadPortAssertSame(1, substr_count($recent, '<span class="ported-author">'));
    }

    private function assertRejected(string $contents, string $message): void
    {
        try {
            (new PostRecordParser())->parse($contents);
        } catch (CanonicalRecordParseException $exception) {
            threadPortAssertContains($message, $exception->getMessage());
            return;
        }
        throw new RuntimeException('Expected rejection: ' . $message);
    }

    private function environment(): string
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-thread-port-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/fixtures/parity_minimal_v1', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $target = $root . '/' . $items->getSubPathName();
            $item->isDir() ? (is_dir($target) || mkdir($target, 0777, true)) : copy($item->getPathname(), $target);
        }
        file_put_contents($root . '/records/posts/ported-root.txt', "Post-ID: ported-root\nCreated-At: 2026-11-19T12:00:00Z\nBoard-Tags: general\nSubject: My YC app: Dropbox\n" . self::PORTED . "\nhello\n");
        file_put_contents($root . '/records/posts/ported-reply.txt', "Post-ID: ported-reply\nCreated-At: 2026-11-19T12:00:00Z\nBoard-Tags: general\nThread-ID: ported-root\nParent-ID: ported-root\n"
            . str_replace(['8863', 'dhouston'], ['8869', 'pg'], self::PORTED) . "\nnice\n");
        foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"', 'git add .', 'git -c commit.gpgsign=false commit -m init'] as $command) {
            exec('cd ' . escapeshellarg($root) . ' && ' . $command . ' 2>&1', $output, $status);
            if ($status !== 0) {
                throw new RuntimeException('Command failed: ' . $command);
            }
        }
        mkdir($root . '-public', 0777, true);
        return $root;
    }

    private function get(Application $application, string $path): string
    {
        http_response_code(200);
        ob_start();
        try {
            $application->handle('GET', $path);
        } finally {
            $html = (string) ob_get_clean();
        }
        return $html;
    }
}

function threadPortAssertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function threadPortAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Expected to find ' . var_export($needle, true) . ' in ' . substr($haystack, 0, 600));
    }
}
