<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class ReadModelPostReactionsTest
{
    public function testApprovedFlagHidesReplyAgentPostAndDeduplicatesFlagger(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-post-reactions-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);

        $identityPath = $repositoryRoot . '/records/identity/identity-openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954.txt';
        file_put_contents(
            $identityPath,
            str_replace(
                "Signer-Fingerprint:",
                "Username: reply-agent\nSigner-Fingerprint:",
                (string) file_get_contents($identityPath)
            )
        );
        $replyPath = $repositoryRoot . '/records/posts/reply-001.txt';
        file_put_contents(
            $replyPath,
            str_replace(
                "\n\nReply body.",
                "\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\nReply body.",
                (string) file_get_contents($replyPath)
            )
        );
        mkdir($repositoryRoot . '/records/post-reactions');
        file_put_contents(
            $repositoryRoot . '/records/post-reactions/post-reaction-20260415153100-ab12cd35.txt',
            "Record-ID: post-reaction-20260415153100-ab12cd35\nCreated-At: 2026-04-15T15:31:00Z\nPost-ID: reply-001\nOperation: add\nTags: flag flag\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/post-reactions/post-reaction-20260415153200-ab12cd36.txt',
            "Record-ID: post-reaction-20260415153200-ab12cd36\nCreated-At: 2026-04-15T15:32:00Z\nPost-ID: reply-001\nOperation: add\nTags: flag\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\n"
        );

        $this->rebuild($repositoryRoot, $databasePath);

        $pdo = new PDO('sqlite:' . $databasePath);
        $row = $pdo->query("SELECT post_tags_json, post_score_total, approved_flag_count, is_hidden, hidden_reason FROM posts WHERE post_id = 'reply-001'")->fetch();

        assertSame('["flag"]', $row['post_tags_json']);
        assertSame('-100', (string) $row['post_score_total']);
        assertSame('1', (string) $row['approved_flag_count']);
        assertSame('1', (string) $row['is_hidden']);
        assertSame('approved_flagged_reply_agent', $row['hidden_reason']);
    }

    public function testUnapprovedFlagAndHumanAuthoredFlagDoNotHidePost(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-post-reactions-visible-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);

        mkdir($repositoryRoot . '/records/post-reactions');
        file_put_contents(
            $repositoryRoot . '/records/post-reactions/post-reaction-20260415153100-ab12cd35.txt',
            "Record-ID: post-reaction-20260415153100-ab12cd35\nCreated-At: 2026-04-15T15:31:00Z\nPost-ID: reply-001\nOperation: add\nTags: flag\n\n"
        );
        file_put_contents(
            $repositoryRoot . '/records/post-reactions/post-reaction-20260415153200-ab12cd36.txt',
            "Record-ID: post-reaction-20260415153200-ab12cd36\nCreated-At: 2026-04-15T15:32:00Z\nPost-ID: root-001\nOperation: add\nTags: flag\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\n"
        );

        $this->rebuild($repositoryRoot, $databasePath);

        $pdo = new PDO('sqlite:' . $databasePath);
        $reply = $pdo->query("SELECT post_tags_json, post_score_total, approved_flag_count, is_hidden FROM posts WHERE post_id = 'reply-001'")->fetch();
        $root = $pdo->query("SELECT post_tags_json, post_score_total, approved_flag_count, is_hidden FROM posts WHERE post_id = 'root-001'")->fetch();

        assertSame('["flag"]', $reply['post_tags_json']);
        assertSame('0', (string) $reply['post_score_total']);
        assertSame('0', (string) $reply['approved_flag_count']);
        assertSame('0', (string) $reply['is_hidden']);
        assertSame('["flag"]', $root['post_tags_json']);
        assertSame('-100', (string) $root['post_score_total']);
        assertSame('1', (string) $root['approved_flag_count']);
        assertSame('0', (string) $root['is_hidden']);
    }

    private function rebuild(string $repositoryRoot, string $databasePath): void
    {
        $builder = new ReadModelBuilder(
            $repositoryRoot,
            $databasePath,
            new CanonicalRecordRepository($repositoryRoot),
            'post_reaction_test',
        );
        $builder->rebuild();
    }

    private function createTempFixtureRoot(): string
    {
        $tempRoot = sys_get_temp_dir() . '/forum-rewrite-post-reaction-fixture-' . bin2hex(random_bytes(6));
        mkdir($tempRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $tempRoot);

        return $tempRoot;
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
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
                . '.'
            );
        }
    }
}
