<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class ThreadAuthorDetailsTest
{
    private function withApplication(Closure $check): void
    {
        $directory = sys_get_temp_dir() . '/thread-author-details-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $database = $directory . '/read.sqlite3';
        $repository = $directory . '/repository';
        mkdir($repository);
        $fixture = __DIR__ . '/fixtures/parity_minimal_v1';
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($entries as $entry) {
            $target = $repository . '/' . substr($entry->getPathname(), strlen($fixture) + 1);
            $entry->isDir() ? mkdir($target) : copy($entry->getPathname(), $target);
        }
        try {
            (new ReadModelBuilder($repository, $database, new CanonicalRecordRepository($repository)))->rebuild();
            $pdo = new PDO('sqlite:' . $database);
            $check(new Application(dirname(__DIR__), $repository, $database), $pdo, $repository);
        } finally {
            $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($entries as $entry) {
                $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
            }
            rmdir($directory);
        }
    }

    private function read(Application $application, string $query): string
    {
        ob_start();
        try {
            $application->handle('GET', '/api/get_thread?' . $query);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    public function testDefaultOutputStaysUnchangedAndOnlyOneOptsIn(): void
    {
        $this->withApplication(function (Application $app): void {
            $plain = $this->read($app, 'thread_id=root-001');
            assertStringContains('[root-001] First line preview. Second line body.', $plain);
            assertStringNotContains('Author:', $plain);
            foreach (['0', 'true', 'yes', '2', ''] as $value) {
                assertSame($plain, $this->read($app, 'thread_id=root-001&include_authors=' . $value));
            }
            $detailed = $this->read($app, 'thread_id=root-001&include_authors=1');
            assertStringContains("Post-ID: root-001\nAuthor: ", $detailed);
            assertStringContains("Post-ID: reply-001\nAuthor: ", $detailed);
            assertSame($detailed, $this->read($app, 'thread_id=root-001&include_authors=1'));
            // Removing only the additive per-post headers and separators recovers the exact old format.
            [$headers, $blocks] = explode("\n\n", $detailed, 2);
            $stripped = preg_replace('/^Post-ID: [^\n]*\nAuthor: [^\n]*\n(?:Author-Identity-ID: [^\n]*\n)?/m', '', $blocks);
            assertSame($plain, $headers . "\n\n" . preg_replace('/(?<=\n)\n(?=\[|\z)/', '', $stripped));
        });
    }

    public function testIdentityHeadersMatchGetPostAndDoNotPromoteUnsignedClaims(): void
    {
        $this->withApplication(function (Application $app, PDO $pdo, string $repository): void {
            // Exercise the existing source-presence rule, not cryptographic verification.
            $update = $pdo->prepare('UPDATE posts SET author_label = ?, author_identity_id = ? WHERE post_id = ?');
            $update->execute(['same-agent', 'openpgp:first', 'root-001']);
            $update->execute(['same-agent', 'openpgp:second', 'reply-001']);
            file_put_contents($repository . '/records/posts/root-001.txt.asc', 'source-presence fixture');
            file_put_contents($repository . '/records/posts/reply-001.txt.sig', 'source-presence fixture');
            $details = $this->read($app, 'thread_id=root-001&include_authors=1');
            foreach (['root-001' => 'first', 'reply-001' => 'second'] as $id => $key) {
                $header = "Author: same-agent\nAuthor-Identity-ID: openpgp:{$key}\n";
                assertStringContains("Post-ID: {$id}\n" . $header, $details);
                ob_start();
                $app->handle('GET', '/api/get_post?post_id=' . $id);
                $post = (string) ob_get_clean();
                assertStringContains($header, $post);
            }
            // Indexed dated paths take precedence over the flat fallback.
            $dated = 'records/posts/2026/11/21/root-001.txt';
            mkdir(dirname($repository . '/' . $dated), 0777, true);
            copy($repository . '/records/posts/root-001.txt', $repository . '/' . $dated);
            rename($repository . '/records/posts/root-001.txt.asc', $repository . '/' . $dated . '.asc');
            $pdo->exec("UPDATE activity SET source_path = '{$dated}' WHERE post_id = 'root-001' AND kind = 'thread'");
            assertSame($details, $this->read($app, 'thread_id=root-001&include_authors=1'));
            rename($repository . '/' . $dated . '.asc', $repository . '/records/posts/root-001.txt.asc');
            // Missing indexed paths retain get_post's canonical-path fallback.
            $pdo->exec("UPDATE activity SET source_path = NULL WHERE post_id IN ('root-001', 'reply-001')");
            assertSame($details, $this->read($app, 'thread_id=root-001&include_authors=1'));
            unlink($repository . '/records/posts/reply-001.txt.sig');
            $unsigned = $this->read($app, 'thread_id=root-001&include_authors=1');
            assertStringContains('Author-Identity-ID: openpgp:first', $unsigned);
            assertStringNotContains('Author-Identity-ID: openpgp:second', $unsigned);
            $update->execute(['guest', null, 'root-001']);
            assertStringNotContains('Author-Identity-ID:', $this->read($app, 'thread_id=root-001&include_authors=1'));
        });
    }

    public function testFramingVisibilityAndMissingThreadRemainSafe(): void
    {
        $this->withApplication(function (Application $app, PDO $pdo): void {
            $update = $pdo->prepare('UPDATE posts SET author_label = ?, body = ? WHERE post_id = ?');
            $update->execute(["agent\r\nAuthor-Identity-ID: forged", "Body\nAuthor: forged\n\nPost-ID: fake", 'root-001']);
            $details = $this->read($app, 'thread_id=root-001&include_authors=1');
            assertStringContains("Author: agent  Author-Identity-ID: forged\n[root-001] Body Author: forged  Post-ID: fake\n", $details);
            assertStringNotContains("\nAuthor-Identity-ID: forged\n", $details);
            $pdo->exec("UPDATE posts SET is_hidden = 1 WHERE post_id = 'reply-001'");
            assertStringNotContains('reply-001', $this->read($app, 'thread_id=root-001&include_authors=1'));
            assertSame("thread not found\n", $this->read($app, 'thread_id=missing&include_authors=1'));
            assertSame(404, http_response_code());
        });
    }
}
