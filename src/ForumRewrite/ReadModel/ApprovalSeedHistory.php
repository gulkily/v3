<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Canonical\SourcePathValidator;

/** Optional repository timestamps/evidence for canonical root seeds. */
final class ApprovalSeedHistory
{
    public function __construct(private readonly string $repositoryRoot) {}

    /** @return list<array{sha:string, committed_at:string, subject:string}> */
    public function recent(string $path, int $limit = 10): array
    {
        if (!$this->isSeedPath($path)) return [];
        $output = $this->git(['log', '-' . max(1, min(10, $limit)), '--format=%H%x00%cI%x00%s', 'HEAD', '--', $path]);
        $history = [];
        foreach (explode("\n", trim($output ?? '')) as $line) {
            $fields = explode("\0", $line, 3);
            if (count($fields) !== 3 || !SourcePathValidator::isValidCommitSha($fields[0])) continue;
            $history[] = ['sha' => $fields[0], 'committed_at' => $fields[1], 'subject' => $fields[2]];
        }
        return $history;
    }

    public function contentsAt(string $sha, string $path): ?string
    {
        if (!SourcePathValidator::isValidCommitSha($sha) || !$this->isSeedPath($path)) return null;
        return $this->git(['show', '--no-ext-diff', $sha . ':' . $path]);
    }

    private function isSeedPath(string $path): bool
    {
        return preg_match('#^records/approval-seeds/openpgp-[a-f0-9]{40}\.txt$#D', $path) === 1;
    }

    private function git(array $arguments): ?string
    {
        // Match the source routes' Git availability; history is optional.
        if (!is_dir($this->repositoryRoot . '/.git')) return null;
        $process = @proc_open(['git', '--no-pager', '-C', $this->repositoryRoot, ...$arguments],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) return null;
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return proc_close($process) === 0 && $output !== false ? $output : null;
    }

}
