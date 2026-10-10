<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use Closure;
use ForumRewrite\Support\ExecutionLock;
use RuntimeException;

/** Durable additive merge. The publisher runs under the same lock as normal forum writes. */
final class ContentImportRunner
{
    private readonly string $stateRoot;

    public function __construct(
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly ?Closure $checkpoint = null,
    ) {
        if (!is_dir($repositoryRoot . '/.git') || is_link($repositoryRoot . '/.git')) {
            throw new RuntimeException('Destination must be a Git checkout with a local .git directory.');
        }
        $this->stateRoot = $repositoryRoot . '/.git/instance-import';
    }

    public function run(?string $source, callable $publisher, bool $dryRun = false, string $sourceLabel = '', array $archiveExclusions = []): array
    {
        return (new ExecutionLock(dirname($this->databasePath) . '/forum-rewrite.lock'))->withExclusiveLock(function () use ($source, $publisher, $dryRun, $sourceLabel, $archiveExclusions): array {
            // A repository lock also serializes invocations with different database overrides.
            return (new ExecutionLock($this->repositoryRoot . '/.git/instance-import.lock'))->withExclusiveLock(function () use ($source, $publisher, $dryRun, $sourceLabel, $archiveExclusions): array {
                $pending = $this->stateRoot . '/pending.json';
                if ($dryRun) {
                    if ($source === null) {
                        throw new RuntimeException('Preview requires a source.');
                    }
                    return $this->report((new ContentImportPlanner())->plan($source, $this->repositoryRoot), $sourceLabel, $archiveExclusions, true);
                }
                if (is_file($pending)) {
                    if ($source !== null) {
                        throw new RuntimeException('An interrupted import needs recovery. Run import-instance --resume with the same destination options first.');
                    }
                    $run = json_decode((string) file_get_contents($pending), true, 512, JSON_THROW_ON_ERROR);
                    if (($run['repository'] ?? null) !== $this->repositoryRoot || ($run['database'] ?? null) !== $this->databasePath) {
                        throw new RuntimeException('Recovery destination does not match the saved import.');
                    }
                } else {
                    if ($source === null) {
                        throw new RuntimeException('No interrupted import to resume.');
                    }
                    $this->assertClean([]);
                    $entries = (new ContentImportPlanner())->plan($source, $this->repositoryRoot);
                    $id = gmdate('YmdTHis') . '-' . bin2hex(random_bytes(6));
                    $directory = $this->stateRoot . '/' . $id;
                    $this->directory($directory);
                    $writes = [];
                    foreach ($entries as $path => $entry) {
                        if (in_array($entry['state'], ['import', 'conflict'], true)) {
                            $saved = $directory . '/' . $path;
                            $this->directory(dirname($saved));
                            if (!copy($source . '/' . $path, $saved) || hash_file('sha256', $saved) !== $entry['hash']) {
                                throw new RuntimeException('Source changed while preparing import: ' . $path);
                            }
                        }
                        if ($entry['state'] === 'import') {
                            $writes[$path] = $entry['hash'];
                        }
                    }
                    $run = [
                        'id' => $id, 'repository' => $this->repositoryRoot, 'database' => $this->databasePath,
                        'base_head' => trim($this->git(['rev-parse', 'HEAD'])), 'phase' => 'prepared',
                        'writes' => $writes, 'report' => $this->report($entries, $sourceLabel, $archiveExclusions),
                    ];
                    $run['report']['review_path'] = $directory;
                    $this->save($pending, $run);
                }
                $directory = $this->stateRoot . '/' . $run['id'];
                $message = 'Import instance content (' . $run['id'] . ')';
                if ($run['phase'] === 'prepared') {
                    $head = trim($this->git(['rev-parse', 'HEAD']));
                    if ($head !== $run['base_head']) {
                        // Recover the narrow crash window after commit but before journal update.
                        if (trim($this->git(['log', '-1', '--format=%s'])) !== $message
                            || trim($this->git(['rev-parse', 'HEAD^'])) !== $run['base_head']) {
                            throw new RuntimeException('Repository changed during interrupted import; inspect saved run before recovery.');
                        }
                        $this->verifyWrites($run['writes']);
                    } else {
                        $this->assertClean($run['writes']);
                        foreach ($run['writes'] as $path => $hash) {
                            $target = $this->repositoryRoot . '/' . $path;
                            $this->assertSafeParents($path);
                            if (file_exists($target) || is_link($target)) {
                                if (is_link($target) || !is_file($target) || hash_file('sha256', $target) !== $hash) {
                                    throw new RuntimeException('Import-owned file diverged; preserving it: ' . $path);
                                }
                            } else {
                                $saved = $directory . '/' . $path;
                                if (!is_file($saved) || hash_file('sha256', $saved) !== $hash) {
                                    throw new RuntimeException('Recovery payload is missing or changed: ' . $path);
                                }
                                $this->directory(dirname($target));
                                $temporary = $this->stateRoot . '/copy.tmp';
                                if (!copy($saved, $temporary) || !rename($temporary, $target)) {
                                    throw new RuntimeException('Unable to install import record: ' . $path);
                                }
                            }
                            $this->at('copied', $path);
                        }
                        foreach (array_chunk(array_keys($run['writes']), 100) as $paths) {
                            $this->git(['add', '--', ...$paths]);
                        }
                        $this->at('staged');
                        if ($run['writes'] !== []) {
                            $this->git(['commit', '-m', $message]);
                        }
                        $this->at('committed');
                    }
                    $run['commit'] = trim($this->git(['rev-parse', 'HEAD']));
                    $run['phase'] = 'publication';
                    $this->save($pending, $run);
                }
                $this->assertClean([]);
                $this->verifyWrites($run['writes']);
                $this->git(['merge-base', '--is-ancestor', $run['commit'], 'HEAD']);
                // Publication is required even on duplicate-only runs and interrupted retries.
                $publisher();
                $this->at('published');
                $run['phase'] = 'complete';
                $run['report']['commit'] = $run['commit'];
                $this->save($directory . '/report.json', $run['report']);
                if (!unlink($pending)) {
                    throw new RuntimeException('Published successfully, but recovery journal could not be cleared.');
                }
                return $run['report'];
            });
        });
    }

    private function report(array $entries, string $source, array $excluded, bool $preview = false): array
    {
        $counts = array_fill_keys(['import', 'duplicate', 'conflict', 'invalid', 'unsupported', 'excluded'], 0);
        foreach ($entries as $entry) {
            $counts[$entry['state']]++;
        }
        return ['source' => $source, 'destination' => $this->repositoryRoot, 'preview' => $preview,
            'status' => $counts['conflict'] + $counts['invalid'] + $counts['unsupported'] > 0 ? 'partial' : 'complete',
            'counts' => $counts, 'excluded_archive_categories' => $excluded, 'entries' => $entries];
    }

    private function assertClean(array $owned): void
    {
        foreach (explode("\0", $this->git(['status', '--porcelain=v1', '-z', '--untracked-files=all'])) as $line) {
            if ($line === '') {
                continue;
            }
            $path = substr($line, 3);
            if (!isset($owned[$path]) || str_contains(substr($line, 0, 2), 'R') || str_contains(substr($line, 0, 2), 'C')) {
                throw new RuntimeException('Destination has unrelated pending changes; preserve or commit them before importing: ' . $path);
            }
        }
    }

    private function verifyWrites(array $writes): void
    {
        foreach ($writes as $path => $hash) {
            $this->assertSafeParents($path);
            $full = $this->repositoryRoot . '/' . $path;
            if (is_link($full) || !is_file($full) || hash_file('sha256', $full) !== $hash) {
                throw new RuntimeException('Import-owned record changed; automatic recovery stopped: ' . $path);
            }
        }
    }

    private function assertSafeParents(string $path): void
    {
        $parent = $this->repositoryRoot;
        foreach (explode('/', dirname($path)) as $part) {
            $parent .= '/' . $part;
            if (is_link($parent) || (file_exists($parent) && !is_dir($parent))) {
                throw new RuntimeException('Unsafe destination directory: ' . $parent);
            }
        }
    }

    private function git(array $arguments): string
    {
        $process = proc_open(['git', '-C', $this->repositoryRoot, ...$arguments], [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start Git.');
        }
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException('Git ' . $arguments[0] . ' failed: ' . trim($error));
        }
        return $output;
    }

    private function directory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true)) {
            throw new RuntimeException('Unable to create import directory.');
        }
    }

    private function save(string $path, array $data): void
    {
        $temporary = $path . '.tmp';
        if (file_put_contents($temporary, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false
            || !rename($temporary, $path)) {
            throw new RuntimeException('Unable to persist import recovery state.');
        }
    }

    private function at(string $phase, string $path = ''): void
    {
        if ($this->checkpoint !== null) {
            ($this->checkpoint)($phase, $path);
        }
    }
}
