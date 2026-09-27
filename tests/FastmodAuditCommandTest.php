<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Scoring\SqliteFastScoreStore;

final class FastmodAuditCommandTest
{
    public function testAuditReportsHistoricalCandidatesAndDoesNotWritePersistentState(): void
    {
        $readPath = sys_get_temp_dir() . '/forum-fastmod-audit-read-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $scorePath = sys_get_temp_dir() . '/forum-fastmod-audit-score-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $exchangePath = sys_get_temp_dir() . '/forum-fastmod-audit-exchange-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $secretsPath = tempnam(sys_get_temp_dir(), 'forum-fastmod-audit-secrets-');
        try {
            $read = new PDO('sqlite:' . $readPath);
            $read->exec('CREATE TABLE posts (post_id TEXT PRIMARY KEY, thread_id TEXT NOT NULL, parent_id TEXT NULL, subject TEXT NULL, body TEXT NOT NULL)');
            $read->exec("INSERT INTO posts VALUES ('post-1', 'post-1', NULL, 'Subject', 'Body')");
            new SqliteFastScoreStore(new PDO('sqlite:' . $scorePath));
            $exchange = new PDO('sqlite:' . $exchangePath);
            $exchange->exec('CREATE TABLE llm_exchanges (call_type TEXT, provider_model TEXT, status TEXT, response_json TEXT)');
            $exchange->prepare('INSERT INTO llm_exchanges VALUES (:call_type, :model, :status, :response)')->execute([
                'call_type' => 'fast_post_score',
                'model' => 'gpt-5-nano',
                'status' => 'completed',
                'response' => json_encode(['decoded' => ['usage' => ['prompt_tokens' => 200, 'completion_tokens' => 50]]]),
            ]);
            file_put_contents($secretsPath, "<?php return " . var_export([
                'FAST_SCORING_DATABASE_PATH' => $scorePath,
                'LLM_EXCHANGE_DATABASE_PATH' => $exchangePath,
                'LLM_PROVIDER' => 'openai',
                'LLM_MODEL' => 'gpt-5-nano',
            ], true) . ';');

            $scoreCountBefore = (int) (new PDO('sqlite:' . $scorePath))->query('SELECT COUNT(*) FROM post_fast_scores')->fetchColumn();
            $workCountBefore = (int) (new PDO('sqlite:' . $scorePath))->query('SELECT COUNT(*) FROM fast_score_work')->fetchColumn();
            $exchangeCountBefore = (int) $exchange->query('SELECT COUNT(*) FROM llm_exchanges')->fetchColumn();
            [$exitCode, $stdout, $stderr] = $this->runCommand(
                dirname(__DIR__),
                'FORUM_SECRETS_PATH=' . escapeshellarg($secretsPath) . ' ./v3 fast-score audit --include-existing --database-path=' . escapeshellarg($readPath),
            );

            assertSame(0, $exitCode);
            assertSame('', $stderr);
            assertStringContains('Fastmod historical audit', $stdout);
            assertStringContains('Selected model: gpt-5-nano', $stdout);
            assertStringContains('Total posts: 1', $stdout);
            assertStringContains('Ready to backfill: 1', $stdout);
            assertStringContains('samples=1', $stdout);
            assertStringContains('assumption=observed_fastmod_usage', $stdout);
            assertStringContains('Audit is read-only: no score, work, task-queue, or exchange records were written.', $stdout);
            assertSame($scoreCountBefore, (int) (new PDO('sqlite:' . $scorePath))->query('SELECT COUNT(*) FROM post_fast_scores')->fetchColumn());
            assertSame($workCountBefore, (int) (new PDO('sqlite:' . $scorePath))->query('SELECT COUNT(*) FROM fast_score_work')->fetchColumn());
            assertSame($exchangeCountBefore, (int) $exchange->query('SELECT COUNT(*) FROM llm_exchanges')->fetchColumn());
        } finally {
            @unlink($readPath);
            @unlink($scorePath);
            @unlink($exchangePath);
            @unlink($secretsPath);
        }
    }

    public function testAuditRequiresExplicitHistoricalScope(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(dirname(__DIR__), './v3 fast-score audit');

        assertSame(1, $exitCode);
        assertSame('', $stdout);
        assertStringContains('error=--include-existing is required for historical audit. Run: ./v3 fast-score audit --include-existing', $stderr);
    }

    public function testInvalidSpaceSeparatedOptionReportsGuidanceWithoutAStackTrace(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(
            dirname(__DIR__),
            './v3 fast-score backfill --include-existing --confirm --max-posts 100',
        );

        assertSame(1, $exitCode);
        assertSame('', $stdout);
        assertStringContains('error=Unknown argument: 100. Use --name=value syntax, for example --max-posts=100.', $stderr);
        assertStringNotContains('Stack trace:', $stderr);
    }

    public function testConfirmedBackfillCreatesABoundedBatchAndEnqueuesTheWorker(): void
    {
        $readPath = sys_get_temp_dir() . '/forum-fastmod-backfill-read-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $scorePath = sys_get_temp_dir() . '/forum-fastmod-backfill-score-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $queuePath = sys_get_temp_dir() . '/forum-fastmod-backfill-queue-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $secretsPath = tempnam(sys_get_temp_dir(), 'forum-fastmod-backfill-secrets-');
        try {
            $read = new PDO('sqlite:' . $readPath);
            $read->exec('CREATE TABLE posts (post_id TEXT PRIMARY KEY, thread_id TEXT NOT NULL, parent_id TEXT NULL, subject TEXT NULL, body TEXT NOT NULL)');
            $read->exec("INSERT INTO posts VALUES ('post-1', 'post-1', NULL, 'Subject', 'Body')");
            file_put_contents($secretsPath, "<?php return " . var_export([
                'FAST_SCORING_DATABASE_PATH' => $scorePath,
                'LLM_PROVIDER' => 'openai',
                'LLM_MODEL' => 'gpt-5-nano',
            ], true) . ';');

            [$missingConfirmCode, $missingConfirmOutput, $missingConfirmError] = $this->runCommand(
                dirname(__DIR__),
                'FORUM_SECRETS_PATH=' . escapeshellarg($secretsPath) . ' ./v3 fast-score backfill --include-existing --max-posts=1 --max-cost-usd=0.01 --database-path=' . escapeshellarg($readPath),
            );
            assertSame(1, $missingConfirmCode);
            assertSame('', $missingConfirmOutput);
            assertStringContains('error=--confirm is required for historical backfill. Run: ./v3 fast-score backfill --include-existing --confirm --max-posts=100 --max-cost-usd=0.10', $missingConfirmError);
            assertSame(false, is_file($scorePath));
            [$exitCode, $stdout, $stderr] = $this->runCommand(
                dirname(__DIR__),
                'FORUM_SECRETS_PATH=' . escapeshellarg($secretsPath) . ' ./v3 fast-score backfill --include-existing --confirm --max-posts=1 --max-cost-usd=0.01 --database-path=' . escapeshellarg($readPath) . ' --queue-database-path=' . escapeshellarg($queuePath),
            );

            assertSame(0, $exitCode);
            assertSame('', $stderr);
            assertStringContains('Fastmod backfill batch created: id=1 requested=1 queued=1', $stdout);
            assertStringContains('estimated budget before every provider attempt', $stdout);
            assertStringContains('Next: ./v3 task-queue run --limit=1 --score-limit=25 --work-limit=250', $stdout);
            assertStringContains('Monitor: ./v3 fast-score status', $stdout);
            assertSame(1, (int) (new PDO('sqlite:' . $scorePath))->query('SELECT COUNT(*) FROM fastmod_backfill_work')->fetchColumn());
            assertSame(1, (int) (new PDO('sqlite:' . $queuePath))->query("SELECT COUNT(*) FROM internal_tasks WHERE type = 'fast_score_sweep' AND status = 'queued'")->fetchColumn());
            [$statusCode, $statusOutput, $statusError] = $this->runCommand(
                dirname(__DIR__),
                'FORUM_SECRETS_PATH=' . escapeshellarg($secretsPath) . ' ./v3 fast-score status --queue-database-path=' . escapeshellarg($queuePath),
            );
            assertSame(0, $statusCode);
            assertSame('', $statusError);
            assertStringContains('Backfill batch 1: status=queued processed=0/1 remaining=1 reserved_estimate_usd=0.000000 cap_usd=0.010000', $statusOutput);
            assertStringContains("Next action\n  Run: ./v3 task-queue run --limit=1 --score-limit=25 --work-limit=250", $statusOutput);
            assertStringContains('Historical backfill is ready for worker processing.', $statusOutput);
            assertStringNotContains("Recent work\n", $statusOutput);
            $workHash = (string) (new PDO('sqlite:' . $scorePath))->query('SELECT content_hash FROM fast_score_work LIMIT 1')->fetchColumn();
            [$verboseCode, $verboseOutput, $verboseError] = $this->runCommand(
                dirname(__DIR__),
                'FORUM_SECRETS_PATH=' . escapeshellarg($secretsPath) . ' ./v3 fast-score status --verbose --queue-database-path=' . escapeshellarg($queuePath),
            );
            assertSame(0, $verboseCode);
            assertSame('', $verboseError);
            assertStringContains("Recent work\n", $verboseOutput);
            assertStringContains(substr($workHash, 0, 12) . '…', $verboseOutput);
            assertStringNotContains($workHash, $verboseOutput);
        } finally {
            @unlink($readPath);
            @unlink($scorePath);
            @unlink($queuePath);
            @unlink($secretsPath);
        }
    }

    /** @return array{0:int, 1:string, 2:string} */
    private function runCommand(string $cwd, string $command): array
    {
        $descriptor = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptor, $pipes, $cwd);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to run command.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), (string) $stdout, (string) $stderr];
    }
}
