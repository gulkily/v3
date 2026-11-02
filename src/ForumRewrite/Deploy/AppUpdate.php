<?php

declare(strict_types=1);

namespace ForumRewrite\Deploy;

use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

/**
 * Operator-triggered update of the application checkout (not the content
 * repository): fetch `origin/main` and fast-forward to it, nothing else.
 *
 * The CLI (`./v3 deploy`), the queued task and the Deploy page's instant
 * action all call deploy(), so they share one lock, one refusal policy and one
 * attempt log. A deploy never merges, resets or stashes: anything other than a
 * clean fast-forward is refused with the checkout left as it was.
 *
 * inspect() is local-only (no network), so pages can show it on every load.
 * check() and deploy() fetch.
 */
final class AppUpdate
{
    public const BRANCH = 'main';
    public const REMOTE = 'origin';

    public const MODE_CLI = 'cli';
    public const MODE_QUEUED = 'queued';
    public const MODE_INSTANT = 'instant';

    private const LOG_ROTATE_BYTES = 262144;

    private string $lockPath;
    private string $logPath;
    private string $queuedPath;

    public function __construct(private readonly string $appRoot, ?string $stateRoot = null)
    {
        $stateRoot ??= $appRoot . '/state';
        $this->lockPath = $stateRoot . '/app_update.lock';
        $this->logPath = $stateRoot . '/logs/app_update.log';
        $this->queuedPath = $stateRoot . '/app_update_queued.txt';
    }

    /**
     * Queues a deploy for the task worker. Repeated requests while one is
     * outstanding coalesce into it (the task queue's deduplication).
     *
     * @return array{enqueued: bool, task_id: int}
     */
    public function queue(SqliteTaskQueueStore $store, string $actor): array
    {
        $task = $store->enqueue(SqliteTaskQueueStore::APP_UPDATE, self::REMOTE . '/' . self::BRANCH, 3);
        $enqueued = ($task['enqueued'] ?? false) === true;
        if ($enqueued) {
            $dir = dirname($this->queuedPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($this->queuedPath, 'Requested-By: ' . $actor . "
Task-ID: " . (int) $task['id'] . "
Requested-At: " . gmdate('Y-m-d\TH:i:s\Z') . "
", LOCK_EX);
            $this->appendAttempt([
                'attempt_id' => gmdate('YmdHis') . '-' . bin2hex(random_bytes(4)),
                'started_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'mode' => self::MODE_QUEUED,
                'actor' => $actor,
                'outcome' => 'queued',
                'message' => 'Waiting for the task worker (task ' . (int) $task['id'] . ').',
            ]);
        }

        return ['enqueued' => $enqueued, 'task_id' => (int) $task['id']];
    }

    /**
     * Runs the queued deploy as the operator who requested it.
     *
     * @return array<string, mixed>
     */
    public function runQueued(int $timeoutSeconds = 120): array
    {
        $actor = 'queued';
        $raw = is_file($this->queuedPath) ? (string) @file_get_contents($this->queuedPath) : '';
        if (preg_match('/^Requested-By: (.+)$/m', $raw, $m) === 1) {
            $actor = trim($m[1]);
        }
        $attempt = $this->deploy($actor, self::MODE_QUEUED, $timeoutSeconds);
        if ($attempt['reason'] !== 'busy') {
            @unlink($this->queuedPath);
        }

        return $attempt;
    }

    public static function forProject(string $projectRoot): self
    {
        return new self($projectRoot);
    }

    /**
     * Local state only; never contacts the remote.
     *
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $state = [
            'is_checkout' => $this->isCheckout(),
            'commit' => null,
            'subject' => null,
            'branch' => null,
            'local_changes' => null,
            'remote_commit' => null,
            'behind' => null,
            'ahead' => null,
            'last_checked_at' => null,
            'busy' => $this->isBusy(),
            'latest' => $this->latestAttempt(),
        ];
        if (!$state['is_checkout']) {
            return $state;
        }
        $state['commit'] = $this->gitValue(['rev-parse', '--verify', 'HEAD']);
        $state['subject'] = $this->gitValue(['log', '-1', '--format=%s']);
        $state['branch'] = $this->gitValue(['symbolic-ref', '--quiet', '--short', 'HEAD']);
        $state['local_changes'] = $this->trackedChangeCount();
        $remote = $this->gitValue(['rev-parse', '--verify', '--quiet', 'refs/remotes/' . self::REMOTE . '/' . self::BRANCH]);
        $state['remote_commit'] = $remote;
        if ($remote !== null && $state['commit'] !== null) {
            $counts = $this->gitValue(['rev-list', '--left-right', '--count', 'HEAD...' . $remote]);
            if ($counts !== null && preg_match('/^(\d+)\s+(\d+)$/', $counts, $m) === 1) {
                $state['ahead'] = (int) $m[1];
                $state['behind'] = (int) $m[2];
            }
        }
        $state['last_checked_at'] = $this->lastFetchTime();

        return $state;
    }

    /**
     * Fetches, then reports inspect(). Refuses while a deploy holds the lock.
     *
     * @return array<string, mixed>
     */
    public function check(int $timeoutSeconds = 60): array
    {
        if (!$this->isCheckout()) {
            return ['outcome' => 'refused', 'reason' => 'not_a_checkout', 'message' => 'The application directory is not a Git checkout.', 'state' => $this->inspect()];
        }
        $lock = $this->acquireLock();
        if ($lock === null) {
            return ['outcome' => 'refused', 'reason' => 'busy', 'message' => 'A deploy is running; try again when it finishes.', 'state' => $this->inspect()];
        }
        try {
            $fetch = $this->fetch($timeoutSeconds);
        } finally {
            $this->releaseLock($lock);
        }
        if ($fetch !== null) {
            return ['outcome' => 'failed'] + $fetch + ['state' => $this->inspect()];
        }

        return ['outcome' => 'checked', 'reason' => null, 'message' => 'Checked GitHub.', 'state' => $this->inspect()];
    }

    /**
     * Fetch and fast-forward. Every call, including refusals, appends one
     * attempt to the log.
     *
     * @return array{attempt_id: string, outcome: string, reason: ?string, message: string, old_commit: ?string, new_commit: ?string, mode: string, actor: string}
     */
    public function deploy(string $actor, string $mode, int $timeoutSeconds = 60): array
    {
        $startedAt = gmdate('Y-m-d\TH:i:s\Z');
        $result = $this->runDeploy($timeoutSeconds);
        $attempt = [
            'attempt_id' => gmdate('YmdHis') . '-' . bin2hex(random_bytes(4)),
            'outcome' => $result['outcome'],
            'reason' => $result['reason'],
            'message' => $result['message'],
            'old_commit' => $result['old_commit'],
            'new_commit' => $result['new_commit'],
            'mode' => $mode,
            'actor' => $actor,
        ];
        $this->appendAttempt($attempt + ['started_at' => $startedAt, 'finished_at' => gmdate('Y-m-d\TH:i:s\Z')]);

        return $attempt;
    }

    /**
     * @return array<string, string>|null
     */
    public function latestAttempt(): ?array
    {
        $raw = is_file($this->logPath) ? (string) @file_get_contents($this->logPath) : '';
        if (trim($raw) === '') {
            return null;
        }
        $blocks = preg_split("/\n\n+/", trim($raw)) ?: [];
        $fields = [];
        foreach (explode("\n", (string) end($blocks)) as $line) {
            $pos = strpos($line, ': ');
            if ($pos !== false) {
                $fields[substr($line, 0, $pos)] = substr($line, $pos + 2);
            }
        }

        return $fields === [] ? null : $fields;
    }

    public function isBusy(): bool
    {
        $lock = $this->acquireLock();
        if ($lock === null) {
            return true;
        }
        $this->releaseLock($lock);

        return false;
    }

    /**
     * @return array{outcome: string, reason: ?string, message: string, old_commit: ?string, new_commit: ?string}
     */
    private function runDeploy(int $timeoutSeconds): array
    {
        $refuse = static fn (string $reason, string $message, ?string $old = null, string $outcome = 'refused'): array
            => ['outcome' => $outcome, 'reason' => $reason, 'message' => $message, 'old_commit' => $old, 'new_commit' => $old];

        if (!$this->isCheckout()) {
            return $refuse('not_a_checkout', 'The application directory is not a Git checkout.');
        }
        $lock = $this->acquireLock();
        if ($lock === null) {
            return $refuse('busy', 'Another deploy is running.', $this->gitValue(['rev-parse', '--verify', 'HEAD']));
        }
        try {
            $old = $this->gitValue(['rev-parse', '--verify', 'HEAD']);
            $branch = $this->gitValue(['symbolic-ref', '--quiet', '--short', 'HEAD']);
            if ($branch !== self::BRANCH) {
                return $refuse('wrong_branch', 'The checkout is on ' . ($branch ?? 'a detached HEAD') . ', not ' . self::BRANCH . '.', $old);
            }
            $changes = $this->trackedChangeCount();
            if ($changes === null) {
                return $refuse('git_failed', 'Could not read the working tree status.', $old, 'failed');
            }
            if ($changes > 0) {
                return $refuse('local_changes', $changes . ' tracked file' . ($changes === 1 ? ' has' : 's have') . ' local changes on the server.', $old);
            }
            $fetch = $this->fetch($timeoutSeconds);
            if ($fetch !== null) {
                return $refuse($fetch['reason'], $fetch['message'], $old, 'failed');
            }
            $remote = $this->gitValue(['rev-parse', '--verify', 'refs/remotes/' . self::REMOTE . '/' . self::BRANCH]);
            if ($remote === null) {
                return $refuse('no_remote_branch', self::REMOTE . '/' . self::BRANCH . ' was not found after fetching.', $old, 'failed');
            }
            if ($remote === $old) {
                return ['outcome' => 'up_to_date', 'reason' => null, 'message' => 'Already at ' . self::REMOTE . '/' . self::BRANCH . '.', 'old_commit' => $old, 'new_commit' => $old];
            }
            if ($this->git(['merge-base', '--is-ancestor', 'HEAD', $remote], 30)['code'] !== 0) {
                return $refuse('diverged', 'The server has commits that are not on ' . self::REMOTE . '/' . self::BRANCH . '; resolve over SSH.', $old);
            }
            $merge = $this->git(['merge', '--ff-only', '--quiet', $remote], $timeoutSeconds);
            $new = $this->gitValue(['rev-parse', '--verify', 'HEAD']);
            if ($merge['code'] !== 0 || $new !== $remote) {
                return ['outcome' => 'failed', 'reason' => 'merge_failed', 'message' => 'The fast-forward did not complete: ' . $this->summarize($merge['err']), 'old_commit' => $old, 'new_commit' => $new];
            }

            return ['outcome' => 'deployed', 'reason' => null, 'message' => 'Updated to ' . substr($new, 0, 12) . '.', 'old_commit' => $old, 'new_commit' => $new];
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * @return array{reason: string, message: string}|null null on success
     */
    private function fetch(int $timeoutSeconds): ?array
    {
        $result = $this->git(['fetch', '--quiet', '--no-tags', self::REMOTE, '+refs/heads/' . self::BRANCH . ':refs/remotes/' . self::REMOTE . '/' . self::BRANCH], $timeoutSeconds);
        if ($result['code'] === 124) {
            return ['reason' => 'fetch_timeout', 'message' => 'Fetching from GitHub took longer than ' . $timeoutSeconds . ' seconds.'];
        }
        if ($result['code'] !== 0) {
            return ['reason' => 'fetch_failed', 'message' => 'Could not fetch from GitHub: ' . $this->summarize($result['err'])];
        }

        return null;
    }

    private function isCheckout(): bool
    {
        return is_dir($this->appRoot . '/.git') || is_file($this->appRoot . '/.git');
    }

    private function trackedChangeCount(): ?int
    {
        $result = $this->git(['status', '--porcelain', '--untracked-files=no'], 30);
        if ($result['code'] !== 0) {
            return null;
        }
        $lines = array_filter(explode("\n", $result['out']), static fn (string $l): bool => trim($l) !== '');

        return count($lines);
    }

    private function lastFetchTime(): ?string
    {
        $gitDir = $this->gitValue(['rev-parse', '--absolute-git-dir']);
        if ($gitDir === null) {
            return null;
        }
        $mtime = @filemtime($gitDir . '/FETCH_HEAD');

        return $mtime === false ? null : gmdate('Y-m-d\TH:i:s\Z', $mtime);
    }

    /**
     * @param array<string, ?string> $attempt
     */
    private function appendAttempt(array $attempt): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_file($this->logPath) && (int) @filesize($this->logPath) > self::LOG_ROTATE_BYTES) {
            @rename($this->logPath, $this->logPath . '.1');
        }
        $labels = [
            'attempt_id' => 'Attempt-ID', 'started_at' => 'Started-At', 'finished_at' => 'Finished-At',
            'mode' => 'Mode', 'actor' => 'Actor', 'outcome' => 'Outcome', 'reason' => 'Reason',
            'old_commit' => 'Old-Commit', 'new_commit' => 'New-Commit', 'message' => 'Message',
        ];
        $lines = [];
        foreach ($labels as $key => $label) {
            $value = str_replace(["\r", "\n"], ' ', (string) ($attempt[$key] ?? ''));
            $lines[] = $label . ': ' . ($value === '' ? '-' : $value);
        }
        @file_put_contents($this->logPath, implode("\n", $lines) . "\n\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * @return resource|null
     */
    private function acquireLock()
    {
        $dir = dirname($this->lockPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $handle = @fopen($this->lockPath, 'c');
        if ($handle === false) {
            return null;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private function releaseLock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * @param list<string> $args
     */
    private function gitValue(array $args): ?string
    {
        $result = $this->git($args, 30);
        $value = trim($result['out']);

        return $result['code'] === 0 && $value !== '' ? $value : null;
    }

    /**
     * Git errors can include remote URLs with credentials; keep one line and
     * strip any user:password@ part.
     */
    private function summarize(string $stderr): string
    {
        $line = trim((string) strtok(trim($stderr) === '' ? 'no details' : trim($stderr), "\n"));
        $line = (string) preg_replace('#(\w+://)[^/@\s]+@#', '$1', $line);

        return mb_substr($line, 0, 200);
    }

    /**
     * @param list<string> $args
     * @return array{code: int, out: string, err: string}
     */
    private function git(array $args, int $timeoutSeconds): array
    {
        $env = getenv();
        foreach (['GIT_DIR', 'GIT_WORK_TREE', 'GIT_COMMON_DIR', 'GIT_INDEX_FILE', 'GIT_OBJECT_DIRECTORY', 'GIT_ALTERNATE_OBJECT_DIRECTORIES', 'GIT_NAMESPACE', 'GIT_PREFIX'] as $key) {
            unset($env[$key]);
        }
        $env['GIT_TERMINAL_PROMPT'] = '0';
        $env['GIT_EDITOR'] = 'true';
        $env['GIT_MERGE_AUTOEDIT'] = 'no';
        $env['LC_ALL'] = 'C';
        $pipes = [];
        $process = @proc_open(['git', '-C', $this->appRoot, ...$args], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($process)) {
            return ['code' => 127, 'out' => '', 'err' => 'Could not start Git.'];
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = $err = '';
        $start = microtime(true);
        $code = null;
        while (true) {
            $out .= (string) stream_get_contents($pipes[1]);
            $err .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                $code = $status['exitcode'];
                break;
            }
            if (microtime(true) - $start > $timeoutSeconds) {
                proc_terminate($process, 9);
                $code = 124;
                break;
            }
            usleep(10000);
        }
        $out .= (string) stream_get_contents($pipes[1]);
        $err .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $closed = proc_close($process);
        if ($code === -1) {
            $code = $closed;
        }

        return ['code' => (int) $code, 'out' => $out, 'err' => $err];
    }
}
