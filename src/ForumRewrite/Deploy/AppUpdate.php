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

    /** Commits listed before "and N more"; keeps page loads and polls cheap. */
    public const INCOMING_LIMIT = 20;
    public const HISTORY_LIMIT = 20;

    /** `git stash` needs an identity; servers often have none configured. */
    private const STASH_IDENTITY = ['-c', 'user.name=Forum deploy', '-c', 'user.email=deploy@localhost'];

    private string $lockPath;
    private string $logPath;
    private string $queuedPath;
    private string $historyPath;

    /** @var (\Closure(): array{status: string, message: string})|null */
    private ?\Closure $readModelCheck;

    /**
     * @param (\Closure(): array{status: string, message: string})|null $readModelCheck runs after a
     *        successful update; defaults to {@see self::checkReadModelWithNewCode()}
     */
    public function __construct(private readonly string $appRoot, ?string $stateRoot = null, ?\Closure $readModelCheck = null)
    {
        $this->readModelCheck = $readModelCheck;
        $stateRoot ??= $appRoot . '/state';
        $this->lockPath = $stateRoot . '/app_update.lock';
        $this->logPath = $stateRoot . '/logs/app_update.log';
        $this->queuedPath = $stateRoot . '/app_update_queued.txt';
        $this->historyPath = $stateRoot . '/logs/deploy_history.txt';
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
            'changed_files' => [],
            'remote_commit' => null,
            'behind' => null,
            'ahead' => null,
            'last_checked_at' => null,
            'incoming_commits' => [],
            'incoming_more' => 0,
            'incoming_stat' => null,
            'incoming_local_overlap' => [],
            'busy' => $this->isBusy(),
            'latest' => $this->latestAttempt(),
        ];
        if (!$state['is_checkout']) {
            return $state;
        }
        $state['commit'] = $this->gitValue(['rev-parse', '--verify', 'HEAD']);
        $state['subject'] = $this->gitValue(['log', '-1', '--format=%s']);
        $state['branch'] = $this->gitValue(['symbolic-ref', '--quiet', '--short', 'HEAD']);
        $changed = $this->trackedChangedFiles();
        $state['local_changes'] = $changed === null ? null : count($changed);
        $state['changed_files'] = $changed ?? [];
        $remote = $this->gitValue(['rev-parse', '--verify', '--quiet', 'refs/remotes/' . self::REMOTE . '/' . self::BRANCH]);
        $state['remote_commit'] = $remote;
        if ($remote !== null && $state['commit'] !== null) {
            $counts = $this->gitValue(['rev-list', '--left-right', '--count', 'HEAD...' . $remote]);
            if ($counts !== null && preg_match('/^(\d+)\s+(\d+)$/', $counts, $m) === 1) {
                $state['ahead'] = (int) $m[1];
                $state['behind'] = (int) $m[2];
            }
        }
        if (($state['behind'] ?? 0) > 0) {
            $state = $this->incoming($state, (string) $remote) + $state;
        }
        $state['last_checked_at'] = $this->lastFetchTime();

        return $state;
    }

    /**
     * What a deploy would bring in, from the last fetch: the non-merge commits
     * on origin/main that HEAD lacks (newest first, capped), the size of the
     * change since the merge base, and which locally edited files it also
     * changes (the case that can end in local_changes_conflict).
     *
     * @param array<string, mixed> $state
     * @return array{incoming_commits: list<array{commit: string, subject: string, author: string, date: string}>, incoming_more: int, incoming_stat: ?array{files: int, insertions: int, deletions: int}, incoming_local_overlap: list<string>}
     */
    private function incoming(array $state, string $remote): array
    {
        [$commits, $more] = $this->commitList('HEAD..' . $remote);

        $stat = null;
        $shortstat = $this->gitValue(['diff', '--no-color', '--shortstat', 'HEAD...' . $remote]);
        if ($shortstat !== null && preg_match('/(\d+) files? changed/', $shortstat, $m) === 1) {
            $stat = [
                'files' => (int) $m[1],
                'insertions' => preg_match('/(\d+) insertions?\(\+\)/', $shortstat, $i) === 1 ? (int) $i[1] : 0,
                'deletions' => preg_match('/(\d+) deletions?\(-\)/', $shortstat, $d) === 1 ? (int) $d[1] : 0,
            ];
        }

        $overlap = [];
        if ($state['changed_files'] !== []) {
            $names = $this->git(['diff', '--no-color', '--name-only', 'HEAD...' . $remote], 30);
            if ($names['code'] === 0) {
                $touched = preg_split('/\R/', trim($names['out'])) ?: [];
                $overlap = array_values(array_intersect($state['changed_files'], $touched));
            }
        }

        return [
            'incoming_commits' => $commits,
            'incoming_more' => $more,
            'incoming_stat' => $stat,
            'incoming_local_overlap' => $overlap,
        ];
    }

    /**
     * The non-merge commits in $range, newest first and capped, and how many more there are.
     * Merge commits are left out (they repeat what the merged commits say), so "more" counts
     * non-merge commits too.
     *
     * @return array{0: list<array{commit: string, subject: string, author: string, date: string}>, 1: int}
     */
    private function commitList(string $range): array
    {
        $commits = [];
        $log = $this->git(['log', '--no-color', '--no-merges', '-n', (string) self::INCOMING_LIMIT, '--format=%H%x1f%s%x1f%an%x1f%cI', $range], 30);
        if ($log['code'] === 0) {
            foreach (preg_split('/\R/', trim($log['out'])) ?: [] as $line) {
                $fields = explode("\x1f", $line);
                if (count($fields) === 4 && preg_match('/^[0-9a-f]{40,64}$/', $fields[0]) === 1) {
                    $commits[] = ['commit' => $fields[0], 'subject' => $fields[1], 'author' => $fields[2], 'date' => substr($fields[3], 0, 10)];
                }
            }
        }
        $total = count($commits);
        if ($total === self::INCOMING_LIMIT) {
            $total = (int) ($this->gitValue(['rev-list', '--no-merges', '--count', $range]) ?? $total);
        }

        return [$commits, max(0, $total - count($commits))];
    }

    /**
     * What rolling back to $commit would undo, and whether it crosses a format change that the
     * older code may not handle. Reads local Git state only and changes nothing.
     *
     * @return array{commit: ?string, reason: ?string, message: string, commits: list<array{commit: string, subject: string, author: string, date: string}>, more: int, warnings: list<string>}
     */
    public function rollbackPreview(string $commit): array
    {
        $old = $this->isCheckout() ? $this->gitValue(['rev-parse', '--verify', 'HEAD']) : null;
        $refusal = $old === null ? ['not_a_checkout', 'The application directory is not a Git checkout.'] : $this->rollbackRefusal($commit, $old);
        if (is_array($refusal)) {
            return ['commit' => null, 'reason' => $refusal[0], 'message' => $refusal[1], 'commits' => [], 'more' => 0, 'warnings' => []];
        }
        [$commits, $more] = $this->commitList($refusal . '..' . $old);

        return ['commit' => $refusal, 'reason' => null, 'message' => '', 'commits' => $commits, 'more' => $more, 'warnings' => $this->formatWarnings($refusal, (string) $old)];
    }

    /**
     * @return string|array{0: string, 1: string} the full target commit, or the reason and message of a refusal
     */
    private function rollbackRefusal(string $commit, string $old): string|array
    {
        $target = preg_match('/^[0-9a-f]{7,64}$/', $commit) === 1 ? $this->gitValue(['rev-parse', '--verify', '--quiet', $commit . '^{commit}']) : null;
        if ($target === null || !in_array($target, $this->deployedCommits(), true)) {
            return ['not_deployed_here', 'That version was never deployed here; ./v3 deploy --history lists the ones that were.'];
        }
        if ($target === $old) {
            return ['already_running', 'That version is already running.'];
        }
        if ($this->git(['merge-base', '--is-ancestor', $target, $old], 30)['code'] !== 0) {
            return ['not_behind', 'That version is not behind the running one; deploy to move forward.'];
        }

        return $target;
    }

    /**
     * Older code cannot be expected to read what newer code wrote. These warn; they never block.
     *
     * @return list<string>
     */
    private function formatWarnings(string $target, string $old): array
    {
        $warnings = [];
        $version = function (string $commit): ?string {
            $source = $this->git(['show', $commit . ':src/ForumRewrite/ReadModel/ReadModelMetadata.php'], 30);

            return $source['code'] === 0 && preg_match("/SCHEMA_VERSION\\s*=\\s*'([^']+)'/", $source['out'], $m) === 1 ? $m[1] : null;
        };
        [$from, $to] = [$version($old), $version($target)];
        if ($from !== $to) {
            $warnings[] = 'The site data format changes (' . ($from ?? '?') . ' back to ' . ($to ?? '?') . '). The site rebuilds its data after rolling back, and again when you deploy forward.';
        }
        $changed = static fn (array $result): bool => $result['code'] === 0 && trim($result['out']) !== '';
        if ($changed($this->git(['diff', '--name-only', $target, $old, '--', 'src/ForumRewrite/Canonical'], 30))) {
            $warnings[] = 'The code that reads records changed. The older version may not show records written since.';
        }
        if ($changed($this->git(['diff', '--name-only', $target, $old, '--', 'docs/specs'], 30))) {
            $warnings[] = 'Format specs in docs/specs/ changed.';
        }

        return $warnings;
    }

    /**
     * Fetches, then reports inspect(). Refuses while a deploy holds the lock.
     *
     * @return array<string, mixed>
     */
    public function check(int $timeoutSeconds = 60, ?int $maxAgeSeconds = null): array
    {
        if (!$this->isCheckout()) {
            return ['outcome' => 'refused', 'reason' => 'not_a_checkout', 'message' => 'The application directory is not a Git checkout.', 'state' => $this->inspect()];
        }
        // A caller that checks often (the header prompt on every page) accepts a recent fetch,
        // so most page loads read local Git state instead of contacting GitHub.
        if ($maxAgeSeconds !== null) {
            $fetchedAt = $this->lastFetchTimestamp();
            if ($fetchedAt !== null && time() - $fetchedAt < $maxAgeSeconds) {
                return ['outcome' => 'checked', 'reason' => 'recent', 'message' => 'Checked recently.', 'state' => $this->inspect()];
            }
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

        return $this->record($this->runDeploy($timeoutSeconds), 'deployed', 'deploy', $actor, $mode, $startedAt);
    }

    /**
     * Return the checkout to a commit it has run before (one listed by {@see self::history()} and
     * behind HEAD). Never contacts the remote. Local edits are kept as in a deploy; a refusal
     * leaves the checkout as it was. The next deploy fast-forwards to origin/main again.
     *
     * @return array{attempt_id: string, outcome: string, reason: ?string, message: string, old_commit: ?string, new_commit: ?string, mode: string, actor: string}
     */
    public function rollback(string $commit, string $actor, string $mode, int $timeoutSeconds = 60): array
    {
        $startedAt = gmdate('Y-m-d\TH:i:s\Z');

        return $this->record($this->runRollback($commit, $timeoutSeconds), 'rolled_back', 'rollback', $actor, $mode, $startedAt);
    }

    /**
     * Checks the read model with the code now in place, then logs the attempt and, when the
     * commit changed, the history entry.
     *
     * @param array{outcome: string, reason: ?string, message: string, old_commit: ?string, new_commit: ?string} $result
     * @return array{attempt_id: string, outcome: string, reason: ?string, message: string, old_commit: ?string, new_commit: ?string, mode: string, actor: string}
     */
    private function record(array $result, string $successOutcome, string $action, string $actor, string $mode, string $startedAt): array
    {
        $readModel = null;
        if ($result['outcome'] === $successOutcome) {
            // The code on disk changed; the code that was running cannot know its read-model
            // schema, so a short process of the code now in place checks it and queues a rebuild
            // when it differs.
            $check = $this->readModelCheck !== null ? ($this->readModelCheck)() : $this->checkReadModelWithNewCode();
            $readModel = $check['status'];
            if ($check['message'] !== '') {
                $result['message'] .= ' ' . $check['message'];
            }
        }
        $attempt = [
            'attempt_id' => gmdate('YmdHis') . '-' . bin2hex(random_bytes(4)),
            'outcome' => $result['outcome'],
            'reason' => $result['reason'],
            'message' => $result['message'],
            'old_commit' => $result['old_commit'],
            'new_commit' => $result['new_commit'],
            'mode' => $mode,
            'actor' => $actor,
            'read_model' => $readModel,
        ];
        // Seed the history before this attempt reaches the log, or the seed would count it twice.
        $this->seedHistory();
        $finishedAt = gmdate('Y-m-d\TH:i:s\Z');
        $this->appendAttempt($attempt + ['started_at' => $startedAt, 'finished_at' => $finishedAt]);
        if ($result['outcome'] === $successOutcome) {
            $this->appendHistory($action, $finishedAt, $mode, $actor, (string) $result['old_commit'], (string) $result['new_commit']);
        }

        return $attempt;
    }

    /**
     * Every commit the history names, as a deploy's old or new commit: the versions a rollback
     * may return to.
     *
     * @return list<string>
     */
    public function deployedCommits(): array
    {
        $commits = [];
        foreach ($this->history(PHP_INT_MAX) as $entry) {
            $commits[$entry['new_commit']] = true;
            if (preg_match('/^[0-9a-f]{40,64}$/', $entry['old_commit']) === 1) {
                $commits[$entry['old_commit']] = true;
            }
        }

        return array_map('strval', array_keys($commits));
    }

    /**
     * The versions this checkout has run, newest first: one entry per deploy that changed the
     * commit. Kept in state/logs/deploy_history.txt, which is never rotated.
     *
     * @return list<array{deployed_at: string, action: string, mode: string, actor: string, old_commit: string, new_commit: string, subject: string}>
     */
    public function history(int $limit = self::HISTORY_LIMIT): array
    {
        $this->seedHistory();
        $entries = [];
        foreach (array_reverse(self::blocks(is_file($this->historyPath) ? (string) @file_get_contents($this->historyPath) : '')) as $fields) {
            if (preg_match('/^[0-9a-f]{40,64}$/', $fields['New-Commit'] ?? '') !== 1) {
                continue;
            }
            $entries[] = [
                'deployed_at' => $fields['Deployed-At'] ?? '-',
                'action' => $fields['Action'] ?? 'deploy',
                'mode' => $fields['Mode'] ?? '-',
                'actor' => $fields['Actor'] ?? '-',
                'old_commit' => $fields['Old-Commit'] ?? '-',
                'new_commit' => $fields['New-Commit'],
                'subject' => ($fields['Subject'] ?? '-') === '-' ? '' : $fields['Subject'],
            ];
            if (count($entries) === $limit) {
                break;
            }
        }

        return $entries;
    }

    private function appendHistory(string $action, string $at, string $mode, string $actor, string $old, string $new): void
    {
        $this->seedHistory();
        @file_put_contents($this->historyPath, $this->historyBlock($action, $at, $mode, $actor, $old, $new), FILE_APPEND | LOCK_EX);
    }

    private function historyBlock(string $action, string $at, string $mode, string $actor, string $old, string $new): string
    {
        $fields = [
            'Deployed-At' => $at, 'Action' => $action, 'Mode' => $mode, 'Actor' => $actor,
            'Old-Commit' => $old, 'New-Commit' => $new,
            'Subject' => (string) $this->gitValue(['log', '-1', '--format=%s', $new, '--']),
        ];
        $lines = [];
        foreach ($fields as $label => $value) {
            $value = trim(str_replace(["\r", "\n"], ' ', $value));
            $lines[] = $label . ': ' . ($value === '' ? '-' : $value);
        }

        return implode("\n", $lines) . "\n\n";
    }

    /**
     * The first time the history is used, fill it from the deploys the attempt log still holds,
     * so the list does not start empty on a site that has deployed before.
     */
    private function seedHistory(): void
    {
        if (is_file($this->historyPath)) {
            return;
        }
        $dir = dirname($this->historyPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $seed = '';
        foreach ([$this->logPath . '.1', $this->logPath] as $path) {
            foreach (self::blocks(is_file($path) ? (string) @file_get_contents($path) : '') as $fields) {
                $old = $fields['Old-Commit'] ?? '';
                $new = $fields['New-Commit'] ?? '';
                if (($fields['Outcome'] ?? '') !== 'deployed' || $old === $new
                    || preg_match('/^[0-9a-f]{40,64}$/', $old) !== 1 || preg_match('/^[0-9a-f]{40,64}$/', $new) !== 1) {
                    continue;
                }
                $seed .= $this->historyBlock('deploy', $fields['Finished-At'] ?? '-', $fields['Mode'] ?? '-', $fields['Actor'] ?? '-', $old, $new);
            }
        }
        @file_put_contents($this->historyPath, $seed, LOCK_EX);
    }

    /**
     * Parses the `Key: value` blocks, separated by blank lines, of the attempt log and the history.
     *
     * @return list<array<string, string>>
     */
    private static function blocks(string $raw): array
    {
        $blocks = [];
        foreach (preg_split("/\n\n+/", trim($raw)) ?: [] as $block) {
            $fields = [];
            foreach (explode("\n", $block) as $line) {
                $pos = strpos($line, ': ');
                if ($pos !== false) {
                    $fields[substr($line, 0, $pos)] = substr($line, $pos + 2);
                }
            }
            if ($fields !== []) {
                $blocks[] = $fields;
            }
        }

        return $blocks;
    }

    /**
     * @return array<string, string>|null
     */
    public function latestAttempt(): ?array
    {
        $blocks = self::blocks(is_file($this->logPath) ? (string) @file_get_contents($this->logPath) : '');

        return $blocks === [] ? null : $blocks[count($blocks) - 1];
    }

    /**
     * Runs `scripts/task_queue.php check-read-model` with the code just deployed. It reads only
     * the read model's metadata, so it adds well under a second to a deploy.
     *
     * @return array{status: string, message: string} status: current, queued or unknown
     */
    private function checkReadModelWithNewCode(): array
    {
        $script = $this->appRoot . '/scripts/task_queue.php';
        if (!is_file($script)) {
            return ['status' => 'unknown', 'message' => ''];
        }
        $run = $this->run([\ForumRewrite\Support\PhpCli::binary(), $script, 'check-read-model'], 30);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $run['out']))));
        $last = $lines === [] ? '' : $lines[count($lines) - 1];
        if ($run['code'] === 0 && preg_match('/^Read model: (current|queued)\b/', $last, $m) === 1) {
            return ['status' => $m[1], 'message' => $m[1] === 'queued' ? $last : ''];
        }

        return ['status' => 'unknown', 'message' => 'Could not check the read model; if pages show "Back in a moment", run ./v3 rebuild.'];
    }

    /**
     * The `./v3 deploy --status` lines for the latest attempt, including whether that deploy
     * found the read model current or queued a rebuild.
     *
     * @param array<string, string>|null $latest from {@see self::latestAttempt()}
     * @return list<string>
     */
    public static function latestAttemptLines(?array $latest): array
    {
        if ($latest === null) {
            return ['Latest-Attempt: none'];
        }
        $lines = ['Latest-Attempt: ' . ($latest['Finished-At'] ?? '-') . ' ' . ($latest['Outcome'] ?? '-') . ' (' . ($latest['Mode'] ?? '-') . ')'];
        if (($latest['Read-Model'] ?? '-') !== '-') {
            $lines[] = 'Latest-Read-Model: ' . $latest['Read-Model'];
        }

        return $lines;
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
            $changed = $this->trackedChangedFiles();
            if ($changed === null) {
                return $refuse('git_failed', 'Could not read the working tree status.', $old, 'failed');
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
            $failure = $this->moveKeepingLocalChanges($old, $changed, function () use ($remote, $timeoutSeconds): ?array {
                $merge = $this->git(['merge', '--ff-only', '--quiet', $remote], $timeoutSeconds);
                if ($merge['code'] !== 0 || $this->gitValue(['rev-parse', '--verify', 'HEAD']) !== $remote) {
                    return ['reason' => 'merge_failed', 'message' => 'The fast-forward did not complete: ' . $this->summarize($merge['err'])];
                }

                return null;
            }, 'update', 'merge them over SSH (git stash, git pull, git stash pop)');
            if ($failure !== null) {
                return $failure;
            }
            $new = (string) $this->gitValue(['rev-parse', '--verify', 'HEAD']);
            $kept = $changed === [] ? '' : ' Kept local changes to ' . implode(', ', $changed) . '.';

            return ['outcome' => 'deployed', 'reason' => null, 'message' => 'Updated to ' . substr($new, 0, 12) . '.' . $kept, 'old_commit' => $old, 'new_commit' => $new];
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * @return array{outcome: string, reason: ?string, message: string, old_commit: ?string, new_commit: ?string}
     */
    private function runRollback(string $commit, int $timeoutSeconds): array
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
            if ($branch !== self::BRANCH || $old === null) {
                return $refuse('wrong_branch', 'The checkout is on ' . ($branch ?? 'a detached HEAD') . ', not ' . self::BRANCH . '.', $old);
            }
            $target = $this->rollbackRefusal($commit, $old);
            if (is_array($target)) {
                return $refuse($target[0], $target[1], $old);
            }
            $changed = $this->trackedChangedFiles();
            if ($changed === null) {
                return $refuse('git_failed', 'Could not read the working tree status.', $old, 'failed');
            }
            $failure = $this->moveKeepingLocalChanges($old, $changed, function () use ($target, $timeoutSeconds): ?array {
                $reset = $this->git(['reset', '--hard', '--quiet', $target], $timeoutSeconds);
                if ($reset['code'] !== 0 || $this->gitValue(['rev-parse', '--verify', 'HEAD']) !== $target) {
                    return ['reason' => 'reset_failed', 'message' => 'The rollback did not complete: ' . $this->summarize($reset['err'])];
                }

                return null;
            }, 'older version', 'roll back over SSH (git stash, git reset --hard ' . substr($target, 0, 12) . ', git stash pop)');
            if ($failure !== null) {
                return $failure;
            }
            $kept = $changed === [] ? '' : ' Kept local changes to ' . implode(', ', $changed) . '.';

            return ['outcome' => 'rolled_back', 'reason' => null, 'message' => 'Rolled back to ' . substr($target, 0, 12) . '.' . $kept, 'old_commit' => $old, 'new_commit' => $target];
        } finally {
            $this->releaseLock($lock);
        }
    }

    /**
     * Moves HEAD with $move while keeping local edits to tracked files (typically
     * public/.htaccess): they are set aside, the move applied, and the edits put back, as an
     * operator would with `git stash`, the move, `git stash pop`. When the edits do not apply
     * at the new commit, the checkout returns to $old with the edits intact.
     *
     * @param list<string> $changed from {@see self::trackedChangedFiles()}
     * @param \Closure(): ?array{reason: string, message: string} $move null on success
     * @return array{outcome: string, reason: ?string, message: string, old_commit: ?string, new_commit: ?string}|null null on success
     */
    private function moveKeepingLocalChanges(string $old, array $changed, \Closure $move, string $what, string $overSsh): ?array
    {
        $stashed = false;
        if ($changed !== []) {
            $stash = $this->git([...self::STASH_IDENTITY, 'stash', 'push', '--quiet', '-m', 'deploy ' . gmdate('Y-m-d\TH:i:s\Z') . ': local changes kept'], 30);
            if ($stash['code'] !== 0) {
                return ['outcome' => 'failed', 'reason' => 'stash_failed', 'message' => 'Could not set aside local changes: ' . $this->summarize($stash['err']), 'old_commit' => $old, 'new_commit' => $old];
            }
            $stashed = true;
        }
        $failure = $move();
        if ($failure !== null) {
            $restored = !$stashed || $this->restoreAt($old);
            $message = $failure['message'];
            if (!$restored) {
                $message .= ' Local changes are saved in `git stash`; run `git stash pop` over SSH.';
            }

            return ['outcome' => 'failed', 'reason' => $failure['reason'], 'message' => $message, 'old_commit' => $old, 'new_commit' => $this->gitValue(['rev-parse', '--verify', 'HEAD'])];
        }
        if ($stashed) {
            $apply = $this->git(['stash', 'apply', '--quiet'], 30);
            if ($apply['code'] !== 0 || $this->hasUnmergedPaths()) {
                // The move and the local edits touch the same lines: go back to the previous
                // commit with the edits intact.
                if (!$this->restoreAt($old)) {
                    return ['outcome' => 'failed', 'reason' => 'stash_restore_failed', 'message' => 'The ' . $what . ' conflicts with local changes to ' . implode(', ', $changed) . ', and they could not be put back automatically. They are saved in `git stash`; resolve over SSH.', 'old_commit' => $old, 'new_commit' => $this->gitValue(['rev-parse', '--verify', 'HEAD'])];
                }

                return ['outcome' => 'refused', 'reason' => 'local_changes_conflict', 'message' => 'The ' . $what . ' changes the same lines as local edits to ' . implode(', ', $changed) . '. Nothing was changed; ' . $overSsh . '.', 'old_commit' => $old, 'new_commit' => $old];
            }
            $this->git(['stash', 'drop', '--quiet'], 30);
        }

        return null;
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

    /**
     * Paths of tracked files with local changes (staged or not); untracked
     * files are not included and are never touched by a deploy.
     *
     * @return list<string>|null
     */
    private function trackedChangedFiles(): ?array
    {
        $result = $this->git(['status', '--porcelain', '--untracked-files=no'], 30);
        if ($result['code'] !== 0) {
            return null;
        }
        $files = [];
        foreach (explode("\n", $result['out']) as $line) {
            if (strlen($line) > 3) {
                $path = substr($line, 3);
                $arrow = strpos($path, ' -> ');
                $files[] = $arrow === false ? $path : substr($path, $arrow + 4);
            }
        }

        return $files;
    }

    private function hasUnmergedPaths(): bool
    {
        $result = $this->git(['diff', '--name-only', '--diff-filter=U'], 30);

        return $result['code'] !== 0 || trim($result['out']) !== '';
    }

    /**
     * Return to $commit and put the stashed local changes back on it, where
     * they came from, so they apply cleanly.
     */
    private function restoreAt(string $commit): bool
    {
        if ($this->git(['reset', '--hard', '--quiet', $commit], 30)['code'] !== 0) {
            return false;
        }

        return $this->git(['stash', 'pop', '--quiet'], 30)['code'] === 0;
    }

    private function lastFetchTime(): ?string
    {
        $mtime = $this->lastFetchTimestamp();

        return $mtime === null ? null : gmdate('Y-m-d\TH:i:s\Z', $mtime);
    }

    private function lastFetchTimestamp(): ?int
    {
        $gitDir = $this->gitValue(['rev-parse', '--absolute-git-dir']);
        if ($gitDir === null) {
            return null;
        }
        clearstatcache(true, $gitDir . '/FETCH_HEAD');
        $mtime = @filemtime($gitDir . '/FETCH_HEAD');

        return $mtime === false ? null : $mtime;
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
            'read_model' => 'Read-Model',
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
        return $this->run(['git', '-C', $this->appRoot, ...$args], $timeoutSeconds);
    }

    /**
     * @param list<string> $command
     * @return array{code: int, out: string, err: string}
     */
    private function run(array $command, int $timeoutSeconds): array
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
        $process = @proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->appRoot, $env);
        if (!is_resource($process)) {
            return ['code' => 127, 'out' => '', 'err' => 'Could not start ' . $command[0] . '.'];
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
