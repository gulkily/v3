<?php

declare(strict_types=1);

namespace ForumRewrite\Checkouts;

use RuntimeException;

final class CheckoutManager
{
    private string $registry;
    private string $state;
    private ?float $deadline = null;
    private ?string $workerToken = null;

    public function __construct(?string $registry = null, ?string $state = null)
    {
        $home = getenv('HOME') ?: '';
        if ($home === '' && ($registry === null || $state === null)) {
            throw new RuntimeException('HOME is unavailable; pass --registry and --state-dir.');
        }
        $this->registry = $registry ?? (getenv('XDG_CONFIG_HOME') ?: "$home/.config") . '/v3/checkouts.json';
        $this->state = $state ?? (getenv('XDG_STATE_HOME') ?: "$home/.local/state") . '/v3/checkouts';
        foreach (['registry', 'state'] as $property) {
            if (!str_starts_with($this->$property, '/')) {
                $this->$property = getcwd() . '/' . $this->$property;
            }
        }
    }

    public function run(string $command, ?string $argument, array $options): array
    {
        $this->directory($this->state);
        if ((fileperms($this->state) & 0077) !== 0) {
            throw new RuntimeException('State directory must be private; use a dedicated directory with mode 0700.');
        }
        if ($command === 'overview') {
            return $this->overview($options);
        }
        if ($command === '__refresh') {
            return $this->backgroundRefresh($argument, $options);
        }
        $lock = fopen($this->state . '/registry.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another checkout command is running; retry when it finishes.');
        }
        try {
            return match ($command) {
                'add' => $this->add($argument, $options),
                'list' => ['command' => 'list', 'checkouts' => $this->readRegistry()['checkouts']],
                'remove' => $this->remove($argument),
                'preview' => $this->preview($options),
                'refresh' => $this->refresh($options),
                'apply' => $this->apply($argument, $options),
                default => throw new RuntimeException('Unknown checkouts command; run ./v3 checkouts --help.'),
            };
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function directory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Cannot create local checkout storage.');
        }
        if (!is_writable($path)) {
            throw new RuntimeException('Checkout storage is not writable.');
        }
    }

    private function readJson(string $path): array
    {
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new RuntimeException('Invalid local checkout file; restore it or select a different registry.');
        }
        return $data;
    }

    private function writeJson(string $path, array $data): void
    {
        if ($this->workerToken !== null && ($this->refreshStatus()['token'] ?? null) !== $this->workerToken) {
            throw new RuntimeException('Refresh ownership changed; authoritative write rejected.');
        }
        $this->directory(dirname($path));
        $temp = tempnam(dirname($path), '.checkouts-');
        if ($temp === false) {
            throw new RuntimeException('Cannot save checkout state.');
        }
        try {
            chmod($temp, 0600);
            if (file_put_contents($temp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n") === false || !rename($temp, $path)) {
                throw new RuntimeException('Cannot save checkout state.');
            }
        } finally {
            if (is_file($temp)) {
                unlink($temp);
            }
        }
    }

    private function readRegistry(): array
    {
        if (!is_file($this->registry)) {
            return ['version' => 1, 'checkouts' => []];
        }
        $data = $this->readJson($this->registry);
        if (($data['version'] ?? null) !== 1 || !is_array($data['checkouts'] ?? null)) {
            throw new RuntimeException('Unsupported registry; restore the registry or use --registry.');
        }
        foreach ($data['checkouts'] as $entry) {
            foreach (['path', 'common', 'source', 'storage', 'worktree', 'remote', 'target', 'role'] as $field) {
                if (!is_string($entry[$field] ?? null) || $entry[$field] === '') {
                    throw new RuntimeException('Invalid registry entry; restore or remove the entry.');
                }
            }
        }
        return $data;
    }

    private function gitEnvironment(): array
    {
        $env = getenv();
        foreach (array_keys($env) as $key) {
            if (in_array($key, ['GIT_DIR', 'GIT_WORK_TREE', 'GIT_COMMON_DIR', 'GIT_INDEX_FILE', 'GIT_OBJECT_DIRECTORY', 'GIT_ALTERNATE_OBJECT_DIRECTORIES', 'GIT_NAMESPACE', 'GIT_PREFIX', 'GIT_SHALLOW_FILE', 'GIT_QUARANTINE_PATH'], true)) {
                unset($env[$key]);
            }
        }
        $env['GIT_TERMINAL_PROMPT'] = '0';
        $env['GIT_EDITOR'] = 'true';
        $env['GIT_MERGE_AUTOEDIT'] = 'no';
        $env['LC_ALL'] = 'C';
        return $env;
    }

    private function git(string $path, array $args, bool $allowFailure = false): array
    {
        if ($this->deadline !== null && microtime(true) >= $this->deadline) {
            throw new RuntimeException('Refresh deadline exceeded; retry with checkouts refresh.');
        }
        $env = $this->gitEnvironment();
        $pipes = [];
        $process = proc_open(['git', '-C', $path, ...$args], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start Git.');
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = $err = '';
        $start = microtime(true);
        do {
            $out .= stream_get_contents($pipes[1]);
            $err .= stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (microtime(true) - $start > 30 || ($this->deadline !== null && microtime(true) >= $this->deadline)) {
                proc_terminate($process, 9);
                // A killed merge/hook may leave locks or Git operation state: never replay it.
                $status['exitcode'] = 124;
                break;
            }
            usleep(10000);
        } while (true);
        $out .= stream_get_contents($pipes[1]);
        $err .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $code = $status['exitcode'];
        if ($code !== 0 && !$allowFailure) {
            // Remote/hook stderr can contain credentials; do not expose it.
            throw new RuntimeException($code === 124 ? 'Git timed out; inspect repository operation state before retrying.' : 'Git command failed; check repository, remote access and Git configuration.');
        }
        return [$code, trim($out)];
    }

    private function source(string $url): string
    {
        if (preg_match('~^https?://~i', $url)) {
            $parts = parse_url($url);
            if (!isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                throw new RuntimeException('Use a credential-free remote URL; configure Git credentials separately.');
            }
            return strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '') . preg_replace('~(?:\.git)?/?$~', '', $parts['path'] ?? '');
        }
        if (str_contains($url, '://')) {
            $parts = parse_url($url);
            if ($parts === false || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
                throw new RuntimeException('Use a credential-free remote URL; configure Git credentials separately.');
            }
        }
        // SSH aliases and local remotes intentionally remain exact identities.
        return $url;
    }

    private function identity(string $path, string $remote): array
    {
        if (!is_dir($path)) {
            throw new RuntimeException('Directory missing; restore it or remove/re-add its registry entry.');
        }
        $root = $this->git($path, ['rev-parse', '--show-toplevel'])[1];
        if (realpath($root) !== realpath($path)) {
            throw new RuntimeException('Register the checkout root directory.');
        }
        $common = $this->git($path, ['rev-parse', '--path-format=absolute', '--git-common-dir'])[1];
        $url = $this->git($path, ['remote', 'get-url', $remote])[1];
        clearstatcache(true, $path);
        clearstatcache(true, $common);
        $worktreeStat = stat($path);
        $storageStat = stat($common);
        if ($worktreeStat === false || $storageStat === false) {
            throw new RuntimeException('Checkout storage disappeared; restore or remove/re-add registration.');
        }
        return ['path' => (string) realpath($path), 'common' => (string) realpath($common), 'source' => $this->source($url),
            'storage' => $storageStat['dev'] . ':' . $storageStat['ino'],
            'worktree' => $worktreeStat['dev'] . ':' . $worktreeStat['ino']];
    }

    private function add(?string $path, array $options): array
    {
        if ($path === null || realpath($path) === false) {
            throw new RuntimeException('add requires an existing checkout root path.');
        }
        $remote = $options['remote'] ?? 'origin';
        $target = $options['target'] ?? 'main';
        $role = $options['role'] ?? 'development';
        if (!in_array($role, ['development', 'test', 'server'], true) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]*$/', $remote)) {
            throw new RuntimeException('Invalid --remote or --role (development, test, server).');
        }
        $this->git($path, ['check-ref-format', 'refs/heads/' . $target]);
        $entry = $this->identity($path, $remote) + ['remote' => $remote, 'target' => $target, 'role' => $role];
        $registry = $this->readRegistry();
        foreach ($registry['checkouts'] as $existing) {
            if ($existing['path'] === $entry['path'] || $existing['common'] === $entry['common']) {
                throw new RuntimeException('Checkout or shared Git storage already registered: ' . $existing['path']);
            }
        }
        $registry['checkouts'][] = $entry;
        $registry['revision'] = bin2hex(random_bytes(12));
        $this->writeJson($this->registry, $registry);
        return ['command' => 'add', 'checkouts' => [$entry]];
    }

    private function ancestor(string $path, string $old, string $new): bool
    {
        [$code] = $this->git($path, ['merge-base', '--is-ancestor', $old, $new], true);
        if (!in_array($code, [0, 1], true)) {
            throw new RuntimeException('Cannot establish ancestry; reconcile history before retrying.');
        }
        return $code === 0;
    }

    private function operation(string $path): bool
    {
        foreach (['MERGE_HEAD', 'CHERRY_PICK_HEAD', 'REVERT_HEAD', 'rebase-merge', 'rebase-apply', 'sequencer', 'index.lock', 'HEAD.lock'] as $marker) {
            $location = $this->git($path, ['rev-parse', '--path-format=absolute', '--git-path', $marker])[1];
            if (file_exists($location)) {
                return true;
            }
        }
        return false;
    }

    private function tracking(array $entry): string
    {
        return 'refs/remotes/' . $entry['remote'] . '/' . $entry['target'];
    }

    private function revision(string $path, string $ref): ?string
    {
        [$code, $oid] = $this->git($path, ['rev-parse', '--verify', $ref . '^{commit}'], true);
        return $code === 0 ? $oid : null;
    }

    private function snapshot(array $entry): array
    {
        $identity = $this->identity($entry['path'], $entry['remote']);
        foreach (['path', 'common', 'source', 'storage', 'worktree'] as $field) {
            if ($identity[$field] !== $entry[$field]) {
                throw new RuntimeException('Registered source or checkout identity changed; review and remove/re-add the entry.');
            }
        }
        return $identity + [
            'branch' => $this->git($entry['path'], ['symbolic-ref', '--quiet', '--short', 'HEAD'], true)[1],
            'head' => $this->revision($entry['path'], 'HEAD'),
            'target' => $this->revision($entry['path'], $this->tracking($entry)),
            'dirty' => $this->git($entry['path'], ['status', '--porcelain=v1', '--untracked-files=all'])[1] !== '',
            'busy' => $this->operation($entry['path']),
        ];
    }

    private function evidencePath(array $entry): string
    {
        return $this->state . '/history-' . hash('sha256', $entry['common'] . "\n" . $entry['source'] . "\n" . $this->tracking($entry)) . '.json';
    }

    private function inspect(array $entry, ?array $before = null): array
    {
        $path = $entry['path'];
        $alreadyFetched = $before !== null;
        $before ??= $this->snapshot($entry);
        if ($before['busy']) {
            return ['action' => 'busy', 'reason' => 'Git operation in progress; finish or abort it, then preview again.', 'snapshot' => $before];
        }
        // Fetch just the registered branch; do not fetch tags or unrelated remotes.
        if (!$alreadyFetched) {
            $this->git($path, $this->fetchArguments($entry));
        }
        $after = $this->snapshot($entry);
        foreach (['head', 'branch', 'dirty'] as $field) {
            if ($before[$field] !== $after[$field]) {
                return ['action' => 'stale', 'reason' => 'Checkout changed during fetch; pause coding sessions and preview again.', 'snapshot' => $after];
            }
        }
        if ($after['head'] === null || $after['target'] === null) {
            throw new RuntimeException('Missing head or update target; restore the branch or correct registration.');
        }
        $historyPath = $this->evidencePath($entry);
        $history = is_file($historyPath) ? $this->readJson($historyPath) : [];
        $baseline = $history['baseline'] ?? null;
        $sourceRewritten = ($history['blocked'] ?? false)
            || ($before['target'] !== null && !$this->ancestor($path, $before['target'], $after['target']))
            || (isset($history['observed']) && !$this->ancestor($path, $history['observed'], $after['target']))
            || ($baseline !== null && !$this->ancestor($path, $baseline, $after['target']));
        if ($sourceRewritten) {
            $this->writeJson($historyPath, ['baseline' => $baseline, 'blocked' => true, 'observed' => $after['target']]);
            return ['action' => 'migration-required', 'reason' => 'Upstream history changed non-forward; follow the rewrite migration runbook. Review recovery before remove/re-add enrollment.', 'snapshot' => $after];
        }
        $behind = $this->ancestor($path, $after['head'], $after['target']);
        $ahead = $this->ancestor($path, $after['target'], $after['head']);
        $proven = $baseline !== null && $this->ancestor($path, $baseline, $after['head']);
        if (!$behind && !$ahead && !$proven) {
            return ['action' => 'migration-required', 'reason' => 'Divergent history has no validated baseline; reconcile with current source before enrollment. Shared ancestry alone is insufficient.', 'snapshot' => $after];
        }
        // Advance evidence only where the branch also descends from the fetched target.
        // Keep an older baseline for a diverged feature until its merge completes.
        $this->writeJson($historyPath, ['baseline' => $ahead ? $after['target'] : ($baseline ?? $after['head']), 'observed' => $after['target'], 'blocked' => false]);
        if ($after['busy']) {
            $action = 'busy'; $reason = 'Git operation in progress; finish or abort it.';
        } elseif ($after['dirty']) {
            $action = 'dirty'; $reason = 'Uncommitted or untracked work; preserve or finish it, then preview again.';
        } elseif ($after['branch'] === '') {
            $action = 'excluded'; $reason = 'Detached HEAD; select a retained branch manually before previewing.';
        } elseif ($after['head'] === $after['target']) {
            $action = 'current'; $reason = 'Already current.';
        } elseif ($behind) {
            $action = 'fast-forward'; $reason = 'Fast-forward to fetched source.';
        } elseif ($ahead) {
            $action = 'ahead'; $reason = 'Locally ahead; local commits preserved.';
        } elseif ($after['branch'] === $entry['target']) {
            $action = 'diverged'; $reason = 'Main branch diverged; reconcile local commits manually (main updates are fast-forward only).';
        } else {
            $action = 'merge'; $reason = 'Merge fetched source into the feature branch.';
        }
        return ['action' => $action, 'reason' => $reason, 'snapshot' => $after];
    }

    private function cacheKey(array $registry, array $options): string
    {
        return hash('sha256', json_encode($registry, JSON_THROW_ON_ERROR) . "\n" . ($options['path'] ?? ''));
    }

    private function cachePath(string $key): string
    {
        return $this->state . '/overview-' . $key . '.json';
    }

    private function readCache(string $key): ?array
    {
        if (!is_file($this->cachePath($key))) {
            return null;
        }
        try {
            $cache = $this->readJson($this->cachePath($key));
            if (($cache['version'] ?? null) !== 1 || ($cache['key'] ?? null) !== $key
                || !is_int($cache['refreshed_at'] ?? null) || !is_array($cache['checkouts'] ?? null)) {
                return null;
            }
            if (($cache['observed_at'] ?? null) !== null && !is_int($cache['observed_at'])) {
                return null;
            }
            foreach ($cache['checkouts'] as $row) {
                if (!is_array($row) || !is_string($row['path'] ?? null)
                    || !is_string($row['action'] ?? null) || !is_string($row['reason'] ?? null)
                    || (isset($row['observed_at']) && !is_int($row['observed_at']))
                    || (isset($row['snapshot']) && (!is_array($row['snapshot']) || !is_string($row['snapshot']['branch'] ?? null)))
                    || (isset($row['last_error']) && !is_string($row['last_error']))) {
                    return null;
                }
            }
            return $cache;
        } catch (\Throwable) {
            return null;
        }
    }

    private function overview(array $options): array
    {
        $registry = $this->readRegistry();
        if (isset($options['path'])) {
            $options['path'] = realpath($options['path']) ?: $options['path'];
            if (!array_filter($registry['checkouts'], fn ($entry) => $entry['path'] === $options['path'])) {
                throw new RuntimeException('Selected path is not registered; use checkouts list or add.');
            }
        }
        $key = $this->cacheKey($registry, $options);
        $cache = $this->readCache($key);
        $stored = [];
        foreach ($cache['checkouts'] ?? [] as $row) {
            if (is_array($row) && is_string($row['path'] ?? null) && is_string($row['action'] ?? null)) {
                $stored[$row['path']] = $row;
            }
        }
        $rows = [];
        foreach ($registry['checkouts'] as $entry) {
            if ((isset($options['path']) && $entry['path'] !== $options['path'])
                || (!isset($options['path']) && $entry['role'] !== 'development')) {
                $rows[] = $entry + ['action' => 'excluded', 'reason' => 'Not selected; test/server roles require --path.'];
            } else {
                $rows[] = $stored[$entry['path']] ?? ($entry + [
                    'action' => 'unchecked', 'reason' => 'No valid cached observation; run ./v3 checkouts refresh.',
                    'observed_at' => null,
                ]);
            }
        }
        $refresh = $rows === [] ? ['state' => 'idle'] : $this->scheduleRefresh($key, $cache, $options);
        return ['command' => 'overview', 'cached' => true, 'cache_key' => $key, 'checkouts' => $rows,
            'observed_at' => $cache['observed_at'] ?? null,
            'age_seconds' => isset($cache['observed_at']) ? max(0, time() - $cache['observed_at']) : null,
            'refresh' => $refresh,
            'notice' => $rows === [] ? 'No clones registered. Use ./v3 checkouts add <path>.' : 'Cached observations; use preview for a fresh update plan.',
            'exit_code' => $this->attention($rows) || in_array($refresh['state'], ['failed', 'deferred'], true) ? 2 : 0];
    }

    private function fetchArguments(array $entry): array
    {
        return ['fetch', '--no-tags', '--no-recurse-submodules', $entry['remote'], '+refs/heads/' . $entry['target'] . ':' . $this->tracking($entry)];
    }

    private function collect(array $registry, array $options): array
    {
        $this->deadline ??= microtime(true) + 120;
        $rows = [];
        $seen = [];
        $prepared = [];
        $tasks = [];
        $selected = isset($options['path']) ? (realpath($options['path']) ?: $options['path']) : null;
        if ($selected !== null && !array_filter($registry['checkouts'], fn ($entry) => $entry['path'] === $selected)) {
            throw new RuntimeException('Selected path is not registered; use checkouts list or add.');
        }
        foreach ($registry['checkouts'] as $index => $entry) {
            $rows[$index] = $entry;
            if (($selected !== null && $selected !== $entry['path']) || ($entry['role'] !== 'development' && $selected === null)) {
                $rows[$index] += ['action' => 'excluded', 'reason' => 'Not selected; test/server roles require --path.'];
            } elseif (isset($seen[$entry['common']])) {
                $rows[$index] += ['action' => 'excluded', 'reason' => 'Shared Git storage already selected.'];
            } else {
                $seen[$entry['common']] = true;
                try {
                    $before = $this->snapshot($entry);
                    if ($before['busy']) {
                        $rows[$index] += ['action' => 'busy', 'reason' => 'Git operation in progress; finish or abort it, then preview again.', 'snapshot' => $before, 'observed_at' => time()];
                    } else {
                        $prepared[$index] = $before;
                        $tasks[$index] = ['path' => $entry['path'], 'args' => $this->fetchArguments($entry)];
                    }
                } catch (\Throwable $error) {
                    $rows[$index] += ['action' => 'unavailable', 'reason' => $error->getMessage()];
                }
            }
        }
        $results = (new CheckoutFetchPool())->fetch($tasks, $this->gitEnvironment(), $this->deadline);
        foreach ($prepared as $index => $before) {
            try {
                if ($results[$index] !== 0) {
                    throw new RuntimeException($results[$index] === 124
                        ? 'Git fetch timed out; inspect processes/locks, then retry refresh.'
                        : 'Git fetch failed; check remote access/configuration, then retry refresh.');
                }
                $rows[$index] += $this->inspect($registry['checkouts'][$index], $before);
                $rows[$index]['observed_at'] = time();
            } catch (\Throwable $error) {
                $rows[$index] += ['action' => 'unavailable', 'reason' => $error->getMessage()];
            }
        }
        return array_values($rows);
    }

    private function refreshStatus(): array
    {
        try {
            return is_file($this->state . '/refresh.json') ? $this->readJson($this->state . '/refresh.json') : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function scheduleRefresh(string $key, ?array $cache, array $options): array
    {
        if ($options['no-refresh'] ?? false) {
            return ['state' => 'disabled', 'reason' => 'Automatic refresh disabled for this invocation.'];
        }
        if ($cache !== null && time() - $cache['refreshed_at'] < 30) {
            return ($cache['refresh']['state'] ?? '') === 'failed' ? $cache['refresh']
                : ['state' => 'fresh', 'reason' => 'Cached observations; use preview before applying.'];
        }
        $lock = fopen($this->state . '/refresh.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            $status = $this->refreshStatus();
            return ['state' => ($status['key'] ?? null) === $key ? 'running' : 'busy',
                'reason' => 'Refresh already active; retry later or use checkouts refresh.'];
        }
        try {
            $previous = $this->refreshStatus();
            $elapsed = time() - ($previous['requested_at'] ?? 0);
            if (($previous['state'] ?? '') === 'queued' && $elapsed < 10) {
                return ['state' => 'queued', 'reason' => 'Refresh launch queued.'];
            }
            if (($previous['state'] ?? '') === 'queued' && $elapsed >= 10) {
                $previous['state'] = 'failed';
                $previous['reason'] = 'Refresh did not start; run ./v3 checkouts refresh to diagnose.';
                $previous['requested_at'] = time();
                $this->writeJson($this->state . '/refresh.json', $previous);
                return $previous;
            }
            if (in_array($previous['state'] ?? '', ['failed', 'deferred'], true) && $elapsed < 5) {
                return $previous;
            }
            $status = ['state' => 'queued', 'key' => $key, 'token' => bin2hex(random_bytes(12)), 'requested_at' => time()];
            $this->writeJson($this->state . '/refresh.json', $status);
            $script = dirname(__DIR__, 3) . '/scripts/checkouts.php';
            if (!(new CheckoutRefreshLauncher())->launch($script, $this->registry, $this->state, $status['token'], $options['path'] ?? null)) {
                $status['state'] = 'failed';
                $status['reason'] = 'Detached refresh unavailable; run ./v3 checkouts refresh to diagnose.';
                $this->writeJson($this->state . '/refresh.json', $status);
            }
            return $status;
        } catch (\Throwable) {
            return ['state' => 'failed', 'reason' => 'Cannot schedule refresh; check private state permissions and run checkouts refresh.'];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function writeWorkerStatus(array $status): void
    {
        if (($this->refreshStatus()['token'] ?? null) === $this->workerToken) {
            $this->writeJson($this->state . '/refresh.json', $status);
        }
    }

    private function backgroundRefresh(?string $token, array $options): array
    {
        if ($token === null || !preg_match('/^[a-f0-9]{24}$/D', $token)
            || !function_exists('pcntl_async_signals') || !function_exists('posix_setsid') || posix_setsid() < 0) {
            throw new RuntimeException('Invalid or unsupported detached worker; use checkouts refresh.');
        }
        $lock = fopen($this->state . '/refresh.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            return ['command' => '__refresh', 'exit_code' => 2];
        }
        $status = $this->refreshStatus();
        if (($status['token'] ?? null) !== $token || ($status['state'] ?? '') !== 'queued') {
            fclose($lock);
            return ['command' => '__refresh', 'exit_code' => 2];
        }
        $this->workerToken = $token;
        $status['state'] = 'running';
        $status['pid'] = getmypid();
        $status['started_at'] = time();
        $this->writeWorkerStatus($status);
        pcntl_async_signals(true);
        $cancel = function () use (&$status): void {
            $status['state'] = 'failed';
            $status['reason'] = 'Refresh interrupted or timed out; run ./v3 checkouts refresh.';
            $status['requested_at'] = time();
            try {
                $this->writeWorkerStatus($status);
            } finally {
                // The worker created its own session; never signal the caller's process group.
                if (posix_getpgrp() === getmypid()) {
                    posix_kill(-getmypid(), SIGKILL);
                }
                exit(1);
            }
        };
        foreach ([SIGTERM, SIGINT, SIGALRM] as $signal) {
            pcntl_signal($signal, $cancel);
        }
        pcntl_alarm(120);
        try {
            $registry = $this->readRegistry();
            if ($this->cacheKey($registry, $options) !== $status['key']) {
                throw new RuntimeException('Registry changed before refresh; run a fresh overview.');
            }
            $result = $this->run('refresh', null, $options);
            $status['state'] = $result['refresh']['state'];
            $status['reason'] = $result['refresh']['reason'];
        } catch (\Throwable $error) {
            $status['state'] = str_starts_with($error->getMessage(), 'Another checkout command') ? 'deferred' : 'failed';
            $status['reason'] = $error->getMessage() . ' Retry ./v3 checkouts refresh.';
        } finally {
            pcntl_alarm(0);
            $status['finished_at'] = time();
            $status['requested_at'] = time();
            $this->writeWorkerStatus($status);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return ['command' => '__refresh', 'exit_code' => 0];
    }

    private function refresh(array $options): array
    {
        $this->deadline = microtime(true) + 120;
        $registry = $this->readRegistry();
        if (isset($options['path'])) {
            $options['path'] = realpath($options['path']) ?: $options['path'];
        }
        $cache = $this->publishCache($registry, $options, $this->collect($registry, $options));
        return ['command' => 'refresh', 'checkouts' => $cache['checkouts'], 'observed_at' => $cache['observed_at'],
            'refresh' => $cache['refresh'], 'notice' => 'Observations refreshed; use preview for an actionable update plan.',
            'exit_code' => $cache['refresh']['state'] === 'failed' || $this->attention($cache['checkouts']) ? 2 : 0];
    }

    private function publishCache(array $registry, array $options, array $rows): array
    {
        if ($this->workerToken !== null && ($this->refreshStatus()['token'] ?? null) !== $this->workerToken) {
            throw new RuntimeException('Refresh ownership changed; stale results discarded.');
        }
        $key = $this->cacheKey($registry, $options);
        $cache = $this->readCache($key);
        $previous = [];
        foreach ($cache['checkouts'] ?? [] as $row) {
            $previous[$row['path']] = $row;
        }
        $failed = false;
        foreach ($rows as &$row) {
            if (in_array($row['action'], ['unavailable', 'stale'], true)) {
                $failed = true;
                if (isset($previous[$row['path']]['observed_at'])) {
                    $error = $row['reason'];
                    $row = $previous[$row['path']];
                    $row['last_error'] = $error;
                    $row['error_at'] = time();
                } else {
                    $row['observed_at'] = null;
                }
            } else {
                $row['observed_at'] = $row['action'] === 'excluded' ? null : ($row['observed_at'] ?? time());
            }
        }
        unset($row);
        if ($key !== $this->cacheKey($this->readRegistry(), $options)) {
            throw new RuntimeException('Registry changed during refresh; results discarded. Run checkouts refresh again.');
        }
        $times = array_filter(array_column($rows, 'observed_at'), 'is_int');
        $refresh = ['state' => $failed ? 'failed' : 'complete', 'reason' => $failed ? 'Some observations failed; previous good rows retained. Run checkouts refresh to retry.' : 'Refresh complete.'];
        $cache = ['version' => 1, 'key' => $key, 'observed_at' => $times === [] ? null : min($times), 'refreshed_at' => time(), 'checkouts' => $rows, 'refresh' => $refresh];
        $this->writeJson($this->cachePath($key), $cache);
        $files = glob($this->state . '/overview-*.json') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, 8) as $file) {
            if ($file !== $this->cachePath($key)) {
                unlink($file);
            }
        }
        return $cache;
    }

    private function invalidateOverview(string $path): void
    {
        foreach (glob($this->state . '/overview-*.json') ?: [] as $file) {
            try {
                $cache = $this->readJson($file);
            } catch (\Throwable) {
                continue;
            }
            $changed = false;
            foreach ($cache['checkouts'] ?? [] as $index => $row) {
                if (($row['path'] ?? '') === $path && ($row['action'] ?? '') !== 'excluded') {
                    $cache['checkouts'][$index]['action'] = 'unchecked';
                    $cache['checkouts'][$index]['reason'] = 'Update attempted; refresh observations.';
                    $cache['checkouts'][$index]['observed_at'] = null;
                    unset($cache['checkouts'][$index]['snapshot'], $cache['checkouts'][$index]['last_error']);
                    $changed = true;
                }
            }
            if ($changed) {
                $cache['observed_at'] = null;
                $cache['refreshed_at'] = 0;
                $cache['refresh'] = ['state' => 'invalidated', 'reason' => 'Update attempted; refresh observations.'];
                $this->writeJson($file, $cache);
            }
        }
    }

    private function preview(array $options): array
    {
        if (isset($options['path'])) {
            $options['path'] = realpath($options['path']) ?: $options['path'];
        }
        $registry = $this->readRegistry();
        if ($registry['checkouts'] === []) {
            throw new RuntimeException('No clones registered; use ./v3 checkouts add <path>.');
        }
        $rows = $this->collect($registry, $options);
        $this->publishCache($registry, $options, $rows);
        $id = bin2hex(random_bytes(12));
        $plan = ['version' => 1, 'id' => $id, 'registry' => hash('sha256', json_encode($registry, JSON_THROW_ON_ERROR)),
            'created' => gmdate('c'), 'checkouts' => $rows];
        $this->directory($this->state . '/previews');
        $this->writeJson($this->state . '/previews/' . $id . '.json', ['plan' => $plan, 'signature' => $this->signature($plan)]);
        return ['command' => 'preview', 'preview_id' => $id, 'checkouts' => $rows,
            'notice' => 'Fetched selected sources; no checked-out files changed. Pause coding/server sessions before apply.',
            'exit_code' => $this->attention($rows) ? 2 : 0];
    }

    private function apply(?string $id, array $options): array
    {
        if (!($options['sessions-paused'] ?? false)) {
            throw new RuntimeException('Pause coding/server sessions, then apply with --sessions-paused. Clean status does not prove idle sessions.');
        }
        $plan = $this->loadPreview($id);
        $registry = $this->readRegistry();
        if (!hash_equals($plan['registry'], hash('sha256', json_encode($registry, JSON_THROW_ON_ERROR)))) {
            throw new RuntimeException('Registry changed; run a fresh preview.');
        }
        $journalPath = $this->state . '/previews/' . $id . '.result.json';
        if (is_file($journalPath)) {
            throw new RuntimeException('Preview already attempted or interrupted; inspect the prior result and Git status, then create a fresh preview. Result: ' . $journalPath);
        }
        $journal = ['command' => 'apply', 'preview_id' => $id, 'complete' => false, 'checkouts' => []];
        $this->writeJson($journalPath, $journal);
        foreach ($plan['checkouts'] as $row) {
            if (!in_array($row['action'], ['fast-forward', 'merge'], true)) {
                $journal['checkouts'][] = $row;
                $this->writeJson($journalPath, $journal);
                continue;
            }
            $lock = null;
            try {
                $lock = fopen($this->state . '/git-' . hash('sha256', $row['common']) . '.lock', 'c');
                if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
                    throw new RuntimeException('Checkout tool lock busy; retry with a new preview.');
                }
                $current = $this->snapshot($row);
                if ($current !== $row['snapshot'] || $current['dirty'] || $current['busy']) {
                    throw new RuntimeException('Checkout/source changed after preview; pause sessions and create a fresh preview.');
                }
                $history = $this->readJson($this->evidencePath($row));
                if (($history['blocked'] ?? true) || !isset($history['baseline'])
                    || !$this->ancestor($row['path'], $history['baseline'], $current['target'])) {
                    throw new RuntimeException('History evidence changed; inspect migration status and preview again.');
                }
                if ($row['action'] === 'merge' && ($current['branch'] === $row['target']
                    || !$this->ancestor($row['path'], $history['baseline'], $current['head']))) {
                    throw new RuntimeException('Feature merge history is no longer proven; preview again.');
                }
                if ($row['action'] === 'fast-forward' && !$this->ancestor($row['path'], $current['head'], $current['target'])) {
                    throw new RuntimeException('Fast-forward no longer valid; preview again.');
                }
                $index = count($journal['checkouts']);
                $attempt = $row;
                $attempt['action'] = 'attempting';
                $attempt['reason'] = 'Update started; if interrupted inspect Git status before a fresh preview.';
                $journal['checkouts'][$index] = $attempt;
                $this->writeJson($journalPath, $journal);
                $this->invalidateOverview($row['path']);
                $mergeArgs = $row['action'] === 'fast-forward'
                    ? ['-c', 'submodule.recurse=false', 'merge', '--ff-only', '--no-overwrite-ignore', '--no-edit', $current['target']]
                    : ['-c', 'submodule.recurse=false', 'merge', '--no-ff', '--no-overwrite-ignore', '--no-edit', $current['target']];
                [$code] = $this->git($row['path'], $mergeArgs, true);
                if ($code !== 0) {
                    if ($this->git($row['path'], ['ls-files', '--unmerged'], true)[1] !== '') {
                        $row['action'] = 'conflict';
                        $prefix = 'git -C ' . escapeshellarg($row['path']);
                        $row['reason'] = 'Resolve files, then ' . $prefix . ' merge --continue; or cancel with '
                            . $prefix . ' merge --abort. Then run a fresh preview.';
                        $journal['checkouts'][$index] = $row;
                        $this->writeJson($journalPath, $journal);
                        continue;
                    }
                    throw new RuntimeException('Git update failed or timed out; inspect Git status, identity, hooks and operation state. If a merge is pending, finish it or use git merge --abort; then create a fresh preview.');
                }
                $after = $this->snapshot($row);
                if ($after['branch'] !== $current['branch'] || $after['dirty'] || $after['busy']
                    || !$this->ancestor($row['path'], $current['head'], $after['head'])
                    || !$this->ancestor($row['path'], $current['target'], $after['head'])) {
                    throw new RuntimeException('Post-update verification needs attention; inspect Git status and hooks. HEAD may have advanced; use a fresh preview after recovery.');
                }
                $row['reason'] = ($row['action'] === 'fast-forward' ? 'Fast-forward' : 'Feature merge')
                    . ' completed; HEAD ' . substr($after['head'], 0, 12) . '.';
                $row['action'] = 'updated';
                $this->writeJson($this->evidencePath($row), ['baseline' => $current['target'], 'observed' => $current['target'], 'blocked' => false]);
                $row['head_after'] = $this->revision($row['path'], 'HEAD');
                $journal['checkouts'][$index] = $row;
            } catch (\Throwable $error) {
                $row['action'] = 'failed';
                $row['reason'] = $error->getMessage();
                if (isset($index) && ($journal['checkouts'][$index]['action'] ?? '') === 'attempting') {
                    $journal['checkouts'][$index] = $row;
                } else {
                    $journal['checkouts'][] = $row;
                }
            } finally {
                if (is_resource($lock)) {
                    flock($lock, LOCK_UN);
                    fclose($lock);
                }
                unset($index);
            }
            $this->writeJson($journalPath, $journal);
        }
        $journal['result_file'] = $journalPath;
        $journal['complete'] = true;
        $journal['exit_code'] = $this->attention($journal['checkouts']) ? 2 : 0;
        $journal['notice'] = 'Only selected checked-out branches were considered. Other branches, stashes and linked worktrees stay untouched. Run a fresh preview after recovery.';
        $this->writeJson($journalPath, $journal);
        return $journal;
    }

    private function attention(array $rows): bool
    {
        foreach ($rows as $row) {
            if (isset($row['last_error']) || !in_array($row['action'], ['current', 'ahead', 'fast-forward', 'merge', 'excluded', 'updated'], true)) {
                return true;
            }
        }
        return false;
    }

    private function signature(array $plan): string
    {
        $path = $this->state . '/preview.key';
        if (!is_file($path)) {
            $this->writeJson($path, ['key' => bin2hex(random_bytes(32))]);
        }
        $key = $this->readJson($path)['key'] ?? '';
        if (!is_string($key) || strlen($key) !== 64) {
            throw new RuntimeException('Preview key is invalid; restore state or use fresh state and preview again.');
        }
        return hash_hmac('sha256', json_encode($plan, JSON_THROW_ON_ERROR), $key);
    }

    private function loadPreview(?string $id): array
    {
        if ($id === null || !preg_match('/^[a-f0-9]{24}$/D', $id)) {
            throw new RuntimeException('apply requires a preview ID; run checkouts preview first.');
        }
        $path = $this->state . '/previews/' . $id . '.json';
        if (!is_file($path)) {
            throw new RuntimeException('Preview not found; use the same state directory and run a fresh preview.');
        }
        $envelope = $this->readJson($path);
        $plan = $envelope['plan'] ?? [];
        if (($plan['version'] ?? null) !== 1 || ($plan['id'] ?? null) !== $id
            || !is_string($envelope['signature'] ?? null)
            || !hash_equals($this->signature($plan), $envelope['signature'])) {
            throw new RuntimeException('Preview is damaged or changed; run a fresh preview.');
        }
        return $plan;
    }

    private function remove(?string $path): array
    {
        if ($path === null) {
            throw new RuntimeException('remove requires the registered absolute path.');
        }
        $path = realpath($path) ?: $path;
        $registry = $this->readRegistry();
        $before = count($registry['checkouts']);
        foreach ($registry['checkouts'] as $entry) {
            if ($entry['path'] === $path && is_file($this->evidencePath($entry))) {
                if (!rename($this->evidencePath($entry), $this->evidencePath($entry) . '.retired-' . bin2hex(random_bytes(6)))) {
                    throw new RuntimeException('Cannot archive enrollment evidence; registration was not removed.');
                }
            }
        }
        $registry['checkouts'] = array_values(array_filter($registry['checkouts'], fn ($entry) => $entry['path'] !== $path));
        if (count($registry['checkouts']) === $before) {
            throw new RuntimeException('Path is not registered; use checkouts list.');
        }
        $registry['revision'] = bin2hex(random_bytes(12));
        $this->writeJson($this->registry, $registry);
        return ['command' => 'remove', 'path' => $path];
    }
}
