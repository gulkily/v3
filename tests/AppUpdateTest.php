<?php

declare(strict_types=1);

use ForumRewrite\Deploy\AppUpdate;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\TaskQueue\TaskQueueWorker;

require_once __DIR__ . '/../autoload.php';

final class AppUpdateTest
{
    public function testDeployFastForwardsToOriginMainAndLogsTheAttempt(): void
    {
        [$app, $seed] = $this->fixture();
        $old = $this->head($app);
        $new = $this->pushCommit($seed, 'second');

        $result = (new AppUpdate($app))->deploy('openpgp:test', AppUpdate::MODE_INSTANT);

        assertSame('deployed', $result['outcome']);
        assertSame($old, $result['old_commit']);
        assertSame($new, $result['new_commit']);
        assertSame($new, $this->head($app));
        $latest = (new AppUpdate($app))->latestAttempt();
        assertSame('deployed', $latest['Outcome']);
        assertSame('instant', $latest['Mode']);
        assertSame('openpgp:test', $latest['Actor']);
        assertSame($new, $latest['New-Commit']);
    }

    public function testDeployReportsUpToDateWithoutChanges(): void
    {
        [$app] = $this->fixture();
        $head = $this->head($app);

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('up_to_date', $result['outcome']);
        assertSame($head, $this->head($app));
    }

    public function testInspectNeverContactsTheRemote(): void
    {
        [$app, $seed, $remote] = $this->fixture();
        $this->pushCommit($seed, 'unseen');
        // A remote that cannot be reached proves inspect() does not fetch.
        $this->git($app, ['remote', 'set-url', 'origin', $remote . '-missing']);

        $state = (new AppUpdate($app))->inspect();

        assertSame(0, $state['behind']);
        assertSame('main', $state['branch']);
        assertSame(0, $state['local_changes']);
        assertSame(false, $state['busy']);
    }

    public function testCheckFetchesAndReportsBehindCount(): void
    {
        [$app, $seed] = $this->fixture();
        $this->pushCommit($seed, 'one');
        $this->pushCommit($seed, 'two');
        $head = $this->head($app);

        $result = (new AppUpdate($app))->check();

        assertSame('checked', $result['outcome']);
        assertSame(2, $result['state']['behind']);
        assertTrue($result['state']['last_checked_at'] !== null, 'Expected a last-checked time after fetching.');
        assertSame($head, $this->head($app));
    }

    public function testKeepsLocalChangesToFilesTheUpdateDoesNotTouch(): void
    {
        [$app, $seed] = $this->fixture();
        $new = $this->pushCommit($seed, 'remote');
        file_put_contents($app . '/README.md', "edited on the server\n");

        $state = (new AppUpdate($app))->inspect();
        assertSame(['README.md'], $state['changed_files']);
        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('deployed', $result['outcome']);
        assertSame($new, $this->head($app));
        assertSame("edited on the server\n", file_get_contents($app . '/README.md'));
        assertStringContains('Kept local changes to README.md', $result['message']);
        assertSame('', trim($this->git($app, ['stash', 'list'])));
    }

    public function testKeepsLocalEditsOnOtherLinesOfAFileTheUpdateChanges(): void
    {
        [$app, $seed] = $this->fixture();
        file_put_contents($seed . '/README.md', "one\ntwo\nthree\nfour\nfive\nsix\n");
        $this->git($seed, ['commit', '-q', '-am', 'longer readme']);
        $this->git($seed, ['push', '-q', 'origin', 'main']);
        (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);
        file_put_contents($seed . '/README.md', "one\ntwo\nthree\nfour\nfive\nSIX FROM MAIN\n");
        $this->git($seed, ['commit', '-q', '-am', 'edit last line']);
        $this->git($seed, ['push', '-q', 'origin', 'main']);
        $new = $this->head($seed);
        file_put_contents($app . '/README.md', "ONE ON SERVER\ntwo\nthree\nfour\nfive\nsix\n");

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('deployed', $result['outcome']);
        assertSame($new, $this->head($app));
        assertSame("ONE ON SERVER\ntwo\nthree\nfour\nfive\nSIX FROM MAIN\n", file_get_contents($app . '/README.md'));
        assertSame('', trim($this->git($app, ['stash', 'list'])));
    }

    public function testConflictingLocalEditRollsBackWithTheEditIntact(): void
    {
        [$app, $seed] = $this->fixture();
        file_put_contents($seed . '/README.md', "from main\n");
        $this->git($seed, ['commit', '-q', '-am', 'change readme']);
        $this->git($seed, ['push', '-q', 'origin', 'main']);
        file_put_contents($app . '/README.md', "edited on the server\n");
        $head = $this->head($app);

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('refused', $result['outcome']);
        assertSame('local_changes_conflict', $result['reason']);
        assertStringContains('README.md', $result['message']);
        assertSame($head, $this->head($app));
        assertSame("edited on the server\n", file_get_contents($app . '/README.md'));
        assertSame('', trim($this->git($app, ['stash', 'list'])));
        assertSame('', trim($this->git($app, ['diff', '--name-only', '--diff-filter=U'])));
    }

    public function testKeepsLocalChangesWithoutAGitIdentityConfigured(): void
    {
        [$app, $seed] = $this->fixture();
        $new = $this->pushCommit($seed, 'remote');
        file_put_contents($app . '/README.md', "edited on the server\n");
        $previous = getenv('GIT_CONFIG_GLOBAL');
        // No global identity, and no guessing from the host name: as on a
        // shared host where Git cannot auto-detect an email address.
        putenv('GIT_CONFIG_GLOBAL=/dev/null');
        putenv('GIT_CONFIG_NOSYSTEM=1');
        putenv('GIT_CONFIG_COUNT=1');
        putenv('GIT_CONFIG_KEY_0=user.useConfigOnly');
        putenv('GIT_CONFIG_VALUE_0=true');
        try {
            $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);
        } finally {
            putenv($previous === false ? 'GIT_CONFIG_GLOBAL' : 'GIT_CONFIG_GLOBAL=' . $previous);
            foreach (['GIT_CONFIG_NOSYSTEM', 'GIT_CONFIG_COUNT', 'GIT_CONFIG_KEY_0', 'GIT_CONFIG_VALUE_0'] as $name) {
                putenv($name);
            }
        }

        assertSame('deployed', $result['outcome'], $result['message']);
        assertSame($new, $this->head($app));
        assertSame("edited on the server\n", file_get_contents($app . '/README.md'));
    }

    public function testUntrackedFilesDoNotBlockADeploy(): void
    {
        [$app, $seed] = $this->fixture();
        $new = $this->pushCommit($seed, 'remote');
        file_put_contents($app . '/notes.local', "scratch\n");

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('deployed', $result['outcome']);
        assertSame($new, $this->head($app));
        assertSame("scratch\n", file_get_contents($app . '/notes.local'));
    }

    public function testRefusesOffTheMainBranch(): void
    {
        [$app, $seed] = $this->fixture();
        $this->pushCommit($seed, 'remote');
        $this->git($app, ['checkout', '-q', '-b', 'hotfix']);
        $head = $this->head($app);

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('wrong_branch', $result['reason']);
        assertSame($head, $this->head($app));
        assertSame('hotfix', trim($this->git($app, ['symbolic-ref', '--short', 'HEAD'])));
    }

    public function testRefusesDivergedHistory(): void
    {
        [$app, $seed] = $this->fixture();
        $this->pushCommit($seed, 'remote');
        file_put_contents($app . '/local.txt', "server-only commit\n");
        $this->git($app, ['add', 'local.txt']);
        $this->git($app, ['commit', '-q', '-m', 'local']);
        $head = $this->head($app);

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('refused', $result['outcome']);
        assertSame('diverged', $result['reason']);
        assertSame($head, $this->head($app));
    }

    public function testRefusesWhileAnotherDeployHoldsTheLock(): void
    {
        [$app, $seed] = $this->fixture();
        $this->pushCommit($seed, 'remote');
        $head = $this->head($app);
        @mkdir($app . '/state', 0775, true);
        $lock = fopen($app . '/state/app_update.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $update = new AppUpdate($app);
            assertSame(true, $update->isBusy());
            $result = $update->deploy('cli', AppUpdate::MODE_QUEUED);
            assertSame('busy', $result['reason']);
            assertSame('busy', $update->check()['reason']);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        assertSame($head, $this->head($app));
        assertSame('busy', (new AppUpdate($app))->latestAttempt()['Reason']);
    }

    public function testFetchFailureLeavesCheckoutUnchanged(): void
    {
        [$app, , $remote] = $this->fixture();
        $head = $this->head($app);
        $this->git($app, ['remote', 'set-url', 'origin', $remote . '-missing']);

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI);

        assertSame('failed', $result['outcome']);
        assertSame('fetch_failed', $result['reason']);
        assertSame($head, $this->head($app));
    }

    public function testFetchErrorsDoNotRevealRemoteCredentials(): void
    {
        [$app] = $this->fixture();
        $this->git($app, ['remote', 'set-url', 'origin', 'https://deployer:s3cr3t-token@127.0.0.1:9/repo.git']);

        $result = (new AppUpdate($app))->deploy('cli', AppUpdate::MODE_CLI, 10);

        assertSame('failed', $result['outcome']);
        assertSame(false, str_contains($result['message'], 's3cr3t-token'), $result['message']);
        assertSame(false, str_contains((string) file_get_contents($app . '/state/logs/app_update.log'), 's3cr3t-token'));
    }

    public function testNonCheckoutIsRefused(): void
    {
        $dir = $this->tempDir();
        $result = (new AppUpdate($dir))->deploy('cli', AppUpdate::MODE_CLI);
        assertSame('not_a_checkout', $result['reason']);
        assertSame(false, (new AppUpdate($dir))->inspect()['is_checkout']);
    }

    public function testQueuedDeployCoalescesAndRunsAsTheRequestingOperator(): void
    {
        [$app, $seed] = $this->fixture();
        $new = $this->pushCommit($seed, 'queued');
        $store = $this->store($app);
        $update = new AppUpdate($app);

        $first = $update->queue($store, 'openpgp:operator');
        $second = $update->queue($store, 'openpgp:operator');
        assertSame(true, $first['enqueued']);
        assertSame(false, $second['enqueued']);
        assertSame($first['task_id'], $second['task_id']);
        assertSame('queued', $update->latestAttempt()['Outcome']);

        $summary = $this->worker($store, $app)->run();

        assertSame(1, $summary['completed']);
        assertSame($new, $this->head($app));
        assertSame('completed', $store->findById($first['task_id'])['status']);
        $latest = $update->latestAttempt();
        assertSame('deployed', $latest['Outcome']);
        assertSame('queued', $latest['Mode']);
        assertSame('openpgp:operator', $latest['Actor']);
    }

    public function testQueuedDeployIsClaimedBeforeEarlierQueuedWork(): void
    {
        [$app, $seed] = $this->fixture();
        $new = $this->pushCommit($seed, 'priority');
        $store = $this->store($app);
        $rebuild = $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');
        $reply = $store->enqueue(SqliteTaskQueueStore::AGENT_REPLY, 'reply-1');
        $deploy = (new AppUpdate($app))->queue($store, 'openpgp:operator');
        $rebuilds = 0;

        $this->worker($store, $app, $rebuilds)->run(1);

        assertSame($new, $this->head($app));
        assertSame('completed', $store->findById($deploy['task_id'])['status']);
        assertSame(0, $rebuilds);
        assertSame('queued', $store->findById($rebuild['id'])['status']);
        assertSame('queued', $store->findById($reply['id'])['status']);
    }

    public function testOtherTaskTypesKeepTheirQueuedOrder(): void
    {
        [$app] = $this->fixture();
        $store = $this->store($app);
        $score = $store->enqueue(SqliteTaskQueueStore::FAST_SCORE_SWEEP, 'fast-score');
        $rebuild = $store->enqueue(SqliteTaskQueueStore::REBUILD_READ_MODEL, 'read-model');

        $claimed = $store->claimNext(2);

        assertSame([$score['id'], $rebuild['id']], array_map(static fn (array $t): int => (int) $t['id'], $claimed));
    }

    public function testQueuedDeployRetriesWhenAnInstantDeployHoldsTheLock(): void
    {
        [$app, $seed] = $this->fixture();
        $this->pushCommit($seed, 'busy');
        $store = $this->store($app);
        $queued = (new AppUpdate($app))->queue($store, 'openpgp:operator');
        @mkdir($app . '/state', 0775, true);
        $lock = fopen($app . '/state/app_update.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $this->worker($store, $app)->run();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        assertSame('queued', $store->findById($queued['task_id'])['status']);

        $this->worker($store, $app)->run();

        assertSame('completed', $store->findById($queued['task_id'])['status']);
        assertSame('openpgp:operator', (new AppUpdate($app))->latestAttempt()['Actor']);
    }

    public function testRefusedQueuedDeployFailsWithTheReason(): void
    {
        [$app, $seed] = $this->fixture();
        file_put_contents($seed . '/README.md', "from main\n");
        $this->git($seed, ['commit', '-q', '-am', 'change readme']);
        $this->git($seed, ['push', '-q', 'origin', 'main']);
        file_put_contents($app . '/README.md', "edited\n");
        $store = $this->store($app);
        $queued = (new AppUpdate($app))->queue($store, 'openpgp:operator');

        $this->worker($store, $app)->run();

        $task = $store->findById($queued['task_id']);
        assertSame('failed', $task['status']);
        assertSame('local_changes_conflict', $task['failure_code']);
    }

    private function store(string $app): SqliteTaskQueueStore
    {
        return new SqliteTaskQueueStore(new PDO('sqlite:' . $app . '/state/tasks.sqlite3'));
    }

    private function worker(SqliteTaskQueueStore $store, string $app, int &$rebuilds = 0): TaskQueueWorker
    {
        return new TaskQueueWorker(
            $store,
            static function () use (&$rebuilds): void {
                $rebuilds++;
            },
            runAppUpdate: static fn (): array => (new AppUpdate($app))->runQueued(30),
        );
    }

    public function testCliHelpAndStatusRun(): void
    {
        $root = dirname(__DIR__);
        exec('php ' . escapeshellarg($root . '/scripts/deploy.php') . ' --help 2>&1', $help, $code);
        assertSame(0, $code);
        assertStringContains('Usage: ./v3 deploy', implode("\n", $help));
        exec('php ' . escapeshellarg($root . '/scripts/deploy.php') . ' --bogus 2>&1', $bad, $badCode);
        assertSame(2, $badCode);
    }

    /**
     * @return array{0: string, 1: string, 2: string} app clone, seed clone, bare remote
     */
    private function fixture(): array
    {
        $base = $this->tempDir();
        $remote = $base . '/remote.git';
        $seed = $base . '/seed';
        $app = $base . '/app';
        $this->run(['git', 'init', '-q', '--bare', '-b', 'main', $remote]);
        $this->run(['git', 'init', '-q', '-b', 'main', $seed]);
        file_put_contents($seed . '/README.md', "app\n");
        file_put_contents($seed . '/.gitignore', "state/\n");
        $this->git($seed, ['add', '.']);
        $this->git($seed, ['commit', '-q', '-m', 'initial']);
        $this->git($seed, ['remote', 'add', 'origin', $remote]);
        $this->git($seed, ['push', '-q', 'origin', 'main']);
        $this->run(['git', 'clone', '-q', $remote, $app]);
        mkdir($app . '/state', 0775, true);

        return [$app, $seed, $remote];
    }

    private function pushCommit(string $seed, string $name): string
    {
        file_put_contents($seed . '/' . $name . '.txt', $name . "\n");
        $this->git($seed, ['add', '.']);
        $this->git($seed, ['commit', '-q', '-m', $name]);
        $this->git($seed, ['push', '-q', 'origin', 'main']);

        return $this->head($seed);
    }

    private function head(string $dir): string
    {
        return trim($this->git($dir, ['rev-parse', 'HEAD']));
    }

    /**
     * @param list<string> $args
     */
    private function git(string $dir, array $args): string
    {
        return $this->run(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.invalid', '-C', $dir, ...$args]);
    }

    /**
     * @param list<string> $command
     */
    private function run(array $command): string
    {
        $pipes = [];
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0) {
            throw new RuntimeException(implode(' ', $command) . ' failed: ' . $err);
        }

        return (string) $out;
    }

    private function tempDir(): string
    {
        $dir = sys_get_temp_dir() . '/app-update-test-' . bin2hex(random_bytes(6));
        mkdir($dir, 0777, true);

        return $dir;
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'Failed asserting identical values. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }
}

if (!function_exists('assertTrue')) {
    function assertTrue(bool $condition, string $message = 'Failed asserting that condition is true.'): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output contains: ' . $needle);
        }
    }
}
