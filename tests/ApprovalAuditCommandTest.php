<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelSchema;

final class ApprovalAuditCommandTest
{
    public function testAuditClassifiesCurrentGrantsWithoutChangingState(): void
    {
        $this->withFixture(function (string $root, string $database): void {
            $before = $this->snapshot($root);
            [$code, $out, $err] = $this->run($root, $database, ['--json']);
            assertSame(0, $code);
            assertSame('', $err);
            $report = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
            assertSame([], $report['warnings']);
            assertSame(9, $report['approved_keys']);
            assertSame(7, $report['approved_usernames']);
            assertSame(3, $report['review_candidates']);
            assertSame([
                'root_seed' => 1,
                'operator_approval' => 1,
                'same_username' => 1,
                'cross_username_single_key' => 3,
                'cross_username_multiple_keys' => 2,
                'unknown' => 1,
            ], $report['counts']);
            $rows = array_column($report['rows'], null, 'identity_id');
            // Both Bob keys are candidates, including his possibly legitimate first key.
            assertSame('cross_username_multiple_keys', $rows[$this->id(4)]['category']);
            assertSame('cross_username_multiple_keys', $rows[$this->id(5)]['category']);
            // Mallory's approver attribution label says "root", but she is not seeded.
            assertSame('cross_username_single_key', $rows[$this->id(7)]['category']);
            // Canonical tokens, rather than display-name spelling, determine equality.
            assertSame('same_username', $rows[$this->id(3)]['category']);
            assertSame(false, isset($rows[$this->id(10)]));
            assertSame($before, $this->snapshot($root));
        });
    }

    public function testReviewFilterKeepsFullCountsAndTextExplainsUncertainty(): void
    {
        $this->withFixture(function (string $root, string $database): void {
            [$code, $out] = $this->run($root, $database, ['--review-only', '--json']);
            assertSame(0, $code);
            $report = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
            assertSame(9, $report['approved_keys']);
            assertSame(3, count($report['rows']));
            foreach ($report['rows'] as $row) {
                assertSame(true, $row['review_candidate']);
            }
            [$code, $out] = $this->run($root, $database, ['--review-only']);
            assertSame(0, $code);
            assertStringContains('Approval audit (read-only)', $out);
            assertStringContains('Review candidates are not proven violations.', $out);
            assertStringContains($this->id(4), $out);
            assertSame(false, str_contains($out, $this->id(7)));
        });
    }

    public function testStaleMetadataIsReportedAndMismatchedRepositoryIsRejected(): void
    {
        $this->withFixture(function (string $root, string $database): void {
            $pdo = new PDO('sqlite:' . $database);
            $pdo->exec("UPDATE metadata SET value = 'old-head' WHERE key = 'repository_head'");
            file_put_contents(dirname($database) . '/read_model_stale.json', '{}');
            [$code, $out] = $this->run($root, $database, ['--json']);
            assertSame(0, $code);
            $report = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
            assertSame(2, count($report['warnings']));
            $pdo->exec("UPDATE metadata SET value = '/missing/repository' WHERE key = 'repository_root'");
            [$code, $out, $err] = $this->run($root, $database, ['--json']);
            assertSame(1, $code);
            assertSame('', $out);
            assertStringContains('does not match', $err);
        });
    }

    public function testMissingInputsAndInvalidOptionsDoNotInitializeState(): void
    {
        $this->withFixture(function (string $root, string $database): void {
            $before = $this->snapshot($root);
            foreach ([
                [$root, $root . '/missing.sqlite3', []],
                [$root . '/missing-repository', $database, []],
                [$root, $database, ['--unknown']],
                [$root, $database, ['--database-path=']],
            ] as [$repository, $db, $options]) {
                [$code, $out, $err] = $this->run($repository, $db, $options);
                assertSame(1, $code);
                assertSame('', $out);
                assertStringContains('Error:', $err);
            }
            [$code, $out] = $this->run($root . '/missing', $root . '/missing.sqlite3', ['--help']);
            assertSame(0, $code);
            assertStringContains('./v3 approval audit', $out);
            assertSame($before, $this->snapshot($root));
        });
    }

    public function testEmptyDatabaseAndEnvironmentPaths(): void
    {
        $this->withFixture(function (string $root, string $database): void {
            $pdo = new PDO('sqlite:' . $database);
            $pdo->exec('DELETE FROM profiles');
            $process = proc_open(
                [dirname(__DIR__) . '/v3', 'approval', 'audit', '--json'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                dirname(__DIR__),
                array_merge(getenv(), ['FORUM_REPOSITORY_ROOT' => $root, 'FORUM_DATABASE_PATH' => $database]),
            );
            fclose($pipes[0]);
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            assertSame(0, proc_close($process));
            assertSame('', $err);
            $report = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
            assertSame(0, $report['approved_keys']);
            assertSame([], $report['rows']);
        });
    }

    private function id(int $number): string
    {
        return 'openpgp:' . str_pad(dechex($number), 40, '0', STR_PAD_LEFT);
    }

    private function withFixture(callable $test): void
    {
        // Spaces and URI-special characters exercise safe SQLite URI path encoding.
        $root = sys_get_temp_dir() . '/approval audit #?-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/approval-seeds', 0777, true);
        $database = $root . '/read model.sqlite3';
        try {
            $pdo = new PDO('sqlite:' . $database);
            foreach (ReadModelSchema::statements() as $statement) {
                $pdo->exec($statement);
            }
            $insert = $pdo->prepare('INSERT INTO metadata VALUES (?, ?)');
            foreach (array_merge(ReadModelMetadata::expectedSchemaIdentity(), [
                'repository_root' => $root, 'repository_head' => 'no-git',
            ]) as $key => $value) {
                $insert->execute([$key, $value]);
            }
            file_put_contents($root . '/records/approval-seeds/openpgp-' . substr($this->id(1), 8) . '.txt',
                'Approved-Identity-ID: ' . $this->id(1) . "\nSeed-Reason: test\n\nRoot seed.\n");
            $insert = $pdo->prepare('INSERT INTO profiles
                (identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint,
                 bootstrap_post_id, bootstrap_thread_id, public_key, is_approved, approved_by_identity_id, approved_by_label)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ([
                [1, 'Operator', 'operator', null, 1],
                [2, 'Alice', 'alice', 1, 1],
                [3, 'ALICE', 'alice', 2, 1],
                [4, 'Bob', 'bob', 2, 1],
                [5, 'Bob', 'bob', 2, 1],
                [6, 'Mallory', 'mallory', 2, 1],
                [7, 'Carol', 'carol', 6, 1],
                [8, 'Unknown', 'unknown', 99, 1],
                [9, 'Pending', 'pending', 2, 1],
                [10, 'Pending', 'pending', null, 0],
            ] as [$number, $username, $token, $approver, $approved]) {
                $insert->execute([
                    $this->id($number), 'key-' . $number, $username, $token, $username,
                    substr($this->id($number), 8), 'bootstrap-' . $number, 'bootstrap-' . $number,
                    '', $approved, $approver === null ? null : $this->id($approver),
                    $number === 6 ? 'root' : null,
                ]);
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
            array_merge([dirname(__DIR__) . '/v3', 'approval', 'audit', '--repository-root=' . $root, '--database-path=' . $database], $options),
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
