<?php

declare(strict_types=1);

namespace ForumRewrite\Checkouts;

use RuntimeException;

final class CheckoutManager
{
    private string $registry;
    private string $state;

    public function __construct(?string $registry = null, ?string $state = null)
    {
        $home = getenv('HOME') ?: '';
        if ($home === '' && ($registry === null || $state === null)) {
            throw new RuntimeException('HOME is unavailable; pass --registry and --state-dir.');
        }
        $this->registry = $registry ?? (getenv('XDG_CONFIG_HOME') ?: "$home/.config") . '/v3/checkouts.json';
        $this->state = $state ?? (getenv('XDG_STATE_HOME') ?: "$home/.local/state") . '/v3/checkouts';
    }

    public function run(string $command, ?string $argument, array $options): array
    {
        $this->directory($this->state);
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
            foreach (['path', 'common', 'source', 'remote', 'target', 'role'] as $field) {
                if (!is_string($entry[$field] ?? null) || $entry[$field] === '') {
                    throw new RuntimeException('Invalid registry entry; restore or remove the entry.');
                }
            }
        }
        return $data;
    }

    private function git(string $path, array $args, bool $allowFailure = false): array
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
            if (microtime(true) - $start > 30) {
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
        return ['path' => (string) realpath($path), 'common' => (string) realpath($common), 'source' => $this->source($url)];
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
        foreach (['path', 'common', 'source'] as $field) {
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

    private function inspect(array $entry): array
    {
        $path = $entry['path'];
        $before = $this->snapshot($entry);
        if ($before['busy']) {
            return ['action' => 'busy', 'reason' => 'Git operation in progress; finish or abort it, then preview again.', 'snapshot' => $before];
        }
        // Fetch just the registered branch; do not fetch tags or unrelated remotes.
        $this->git($path, ['fetch', '--no-tags', '--no-recurse-submodules', $entry['remote'], '+refs/heads/' . $entry['target'] . ':' . $this->tracking($entry)]);
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
        $this->writeJson($historyPath, ['baseline' => $ahead ? $after['target'] : ($baseline ?? $after['head']), 'blocked' => false]);
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

    private function preview(array $options): array
    {
        $registry = $this->readRegistry();
        $rows = [];
        $seen = [];
        $selected = isset($options['path']) ? (realpath($options['path']) ?: $options['path']) : null;
        foreach ($registry['checkouts'] as $entry) {
            $row = $entry;
            if (($selected !== null && $selected !== $entry['path']) || ($entry['role'] !== 'development' && $selected === null)) {
                $row += ['action' => 'excluded', 'reason' => 'Not selected; test/server roles require --path.'];
            } elseif (isset($seen[$entry['common']])) {
                $row += ['action' => 'excluded', 'reason' => 'Shared Git storage already selected.'];
            } else {
                $seen[$entry['common']] = true;
                try {
                    $row += $this->inspect($entry);
                } catch (\Throwable $error) {
                    $row += ['action' => 'unavailable', 'reason' => $error->getMessage()];
                }
            }
            $rows[] = $row;
        }
        if ($selected !== null && !array_filter($rows, fn ($row) => $row['path'] === $selected)) {
            throw new RuntimeException('Selected path is not registered; use checkouts list or add.');
        }
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
                $mergeArgs = $row['action'] === 'fast-forward'
                    ? ['merge', '--ff-only', '--no-edit', $current['target']]
                    : ['merge', '--no-ff', '--no-edit', $current['target']];
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
                $this->writeJson($this->evidencePath($row), ['baseline' => $current['target'], 'blocked' => false]);
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
        $journal['complete'] = true;
        $journal['exit_code'] = $this->attention($journal['checkouts']) ? 2 : 0;
        $journal['notice'] = 'Only selected checked-out branches were considered. Other branches, stashes and linked worktrees stay untouched. Run a fresh preview after recovery.';
        $this->writeJson($journalPath, $journal);
        return $journal;
    }

    private function attention(array $rows): bool
    {
        foreach ($rows as $row) {
            if (!in_array($row['action'], ['current', 'ahead', 'fast-forward', 'merge', 'excluded', 'updated'], true)) {
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
        $this->writeJson($this->registry, $registry);
        return ['command' => 'remove', 'path' => $path];
    }
}
