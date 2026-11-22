<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\Security\ApprovalAcceptance;

final class ApprovalRootSuggestionsCommandTest
{
    public function testSuggestsOneRootPerUncoveredSourceWithEvidence(): void
    {
        $this->withFixture(function (string $root, string $database): void {
            $before = $this->snapshot($root);
            [$code, $out, $err] = $this->run($root, $database, ['--json']);
            assertSame(0, $code);
            assertSame('', $err);
            $report = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
            assertSame([], $report['warnings']);
            assertSame(1, $report['existing_seeds']);
            assertSame(1, $report['sources_already_covered']);
            // Receipt-backed operator first, then unverified by size, then contradicted receipts.
            assertSame([$this->id(10), $this->id(31), $this->id(20), $this->id(50)], array_column($report['suggestions'], 'identity_id'));
            assertSame(['operator_receipt', 'unverified', 'unverified', 'receipt_says_not_operator'], array_column($report['suggestions'], 'evidence'));
            assertSame([3, 3, 2, 2], array_column($report['suggestions'], 'restores'));
            // The cycle {30, 31} needs one seed; 31 gave more approvals.
            sort($report['suggestions'][1]['component_keys']);
            assertSame([$this->id(30), $this->id(31)], $report['suggestions'][1]['component_keys']);
            // 40's approval sits outside 41's bootstrap thread, so neither is reached; 60 has no edges.
            assertSame(3, $report['keys_without_approval_path']);
            assertSame(12, $report['keys_restored']);
            assertStringContains("./v3 approval seed {$this->id(10)} 'restored root'", $report['suggestions'][0]['seed_command']);
            assertSame($before, $this->snapshot($root));
        });
    }

    public function testTextOutputPrintsCommandsWithoutRunningThem(): void
    {
        $this->withFixture(function (string $root, string $database): void {
            [$code, $out] = $this->run($root, $database, []);
            assertSame(0, $code);
            assertStringContains('Approval root suggestions (read-only)', $out);
            assertStringContains('Review, then run:', $out);
            assertStringContains('./v3 approval seed ' . $this->id(20), $out);
            assertSame(1, count(glob($root . '/records/approval-seeds/*.txt')));
        });
    }

    public function testMissingInputsAndInvalidOptionsFail(): void
    {
        $this->withFixture(function (string $root, string $database): void {
            $before = $this->snapshot($root);
            foreach ([
                [$root, $root . '/missing.sqlite3', []],
                [$root . '/missing-repository', $database, []],
                [$root, $database, ['--unknown']],
            ] as [$repository, $db, $options]) {
                [$code, $out, $err] = $this->run($repository, $db, $options);
                assertSame(1, $code);
                assertSame('', $out);
                assertStringContains('Error:', $err);
            }
            [$code, $out] = $this->run($root . '/missing', $root . '/missing.sqlite3', ['--help']);
            assertSame(0, $code);
            assertStringContains('./v3 approval suggest-roots', $out);
            assertSame($before, $this->snapshot($root));
        });
    }

    private function id(int $number): string
    {
        return 'openpgp:' . str_pad(dechex($number), 40, '0', STR_PAD_LEFT);
    }

    private function withFixture(callable $test): void
    {
        $root = sys_get_temp_dir() . '/suggest roots #?-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/approval-seeds', 0777, true);
        mkdir($root . '/records/posts', 0777, true);
        mkdir($root . '/records/approval-acceptances', 0777, true);
        $database = $root . '/read model.sqlite3';
        try {
            $pdo = new PDO('sqlite:' . $database);
            foreach (ReadModelSchema::statements() as $statement) {
                $pdo->exec($statement);
            }
            $insert = $pdo->prepare('INSERT INTO metadata (key, value) VALUES (?, ?)');
            foreach (array_merge(ReadModelMetadata::expectedSchemaIdentity(), [
                'repository_root' => $root, 'repository_head' => 'no-git',
            ]) as $key => $value) {
                $insert->execute([$key, $value]);
            }
            file_put_contents($root . '/records/approval-seeds/openpgp-' . substr($this->id(1), 8) . '.txt',
                'Approved-Identity-ID: ' . $this->id(1) . "\nSeed-Reason: test\n\nRoot seed.\n");
            $profile = $pdo->prepare('INSERT INTO profiles
                (identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint,
                 bootstrap_post_id, bootstrap_thread_id, public_key, is_approved, approved_by_identity_id, approved_by_label)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, NULL)');
            foreach ([1, 2, 10, 11, 12, 20, 21, 30, 31, 32, 40, 41, 50, 51, 60] as $number) {
                $name = 'user' . $number;
                $profile->execute([$this->id($number), 'key-' . $number, $name, $name, $name, substr($this->id($number), 8),
                    'boot-' . $number, 'boot-' . $number, '']);
            }
            $sequence = 0;
            foreach ([
                [1, 2, null], [10, 11, true], [11, 12, null], [20, 21, null],
                [30, 31, null], [31, 30, null], [31, 32, null], [50, 51, false],
                [40, 41, null, 'boot-60'],
            ] as $grant) {
                [$actor, $target, $operator] = $grant;
                $thread = $grant[3] ?? 'boot-' . $target;
                $postId = 'grant-' . $actor . '-' . $target;
                $body = 'Approve-Identity-ID: ' . $this->id($target) . "\n";
                $created = sprintf('2026-01-01T00:%02d:00Z', ++$sequence);
                $contents = "Post-ID: {$postId}\nCreated-At: {$created}\nBoard-Tags: identity approval\nThread-ID: {$thread}\n"
                    . "Parent-ID: {$thread}\nAuthor-Identity-ID: {$this->id($actor)}\n\n{$body}";
                file_put_contents($root . '/records/posts/' . $postId . '.txt', $contents);
                if ($operator !== null) {
                    $context = ['actor_identity_id' => $this->id($actor), 'target_identity_id' => $this->id($target),
                        'actor_username' => 'user' . $actor, 'target_username' => 'user' . $target,
                        'target_profile_slug' => 'key-' . $target, 'target_thread_id' => $thread, 'target_post_id' => $thread,
                        'existing_key_count' => 0, 'classification' => 'new_account', 'actor_is_operator' => $operator,
                        'cross_account_allowed' => false, 'allowed' => true];
                    file_put_contents($root . '/' . ApprovalAcceptance::path($postId),
                        ApprovalAcceptance::encode($contents, $context, $created, 'direct'));
                }
                $pdo->prepare('INSERT INTO posts (post_id, created_at, thread_id, parent_id, body, board_tags_json, author_identity_id, sequence_number)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$postId, $created, $thread, $thread, $body, '["identity","approval"]', $this->id($actor), $sequence]);
            }
            $pdo = null;
            $test($root, $database);
        } finally {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($root);
        }
    }

    private function snapshot(string $root): array
    {
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $item) {
            if ($item->isFile()) {
                $files[$item->getPathname()] = hash_file('sha256', $item->getPathname());
            }
        }
        ksort($files);
        return $files;
    }

    private function run(string $root, string $database, array $options): array
    {
        $process = proc_open(
            array_merge([dirname(__DIR__) . '/v3', 'approval', 'suggest-roots', '--repository-root=' . $root, '--database-path=' . $database], $options),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            dirname(__DIR__),
        );
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }
}
