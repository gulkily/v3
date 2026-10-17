<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordParseException;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\SourcePathValidator;
use ForumRewrite\ReadModel\ProfileRepository;

/** Readable evidence for existing repository-owned root approval seeds. */
final class RootApprovalPageController
{
    public function __construct(private readonly RouteServices $services, private readonly string $repositoryRoot) {}

    public function show(string $keySlug): void
    {
        $keySlug = rawurldecode($keySlug);
        if (preg_match('/^openpgp-([a-f0-9]{40})$/D', $keySlug, $match) !== 1) {
            $this->unavailable();
            return;
        }
        $path = CanonicalPathResolver::approvalSeed($match[1]);
        if (!SourcePathValidator::currentPathExists($this->repositoryRoot, $path)
            || !is_readable($this->repositoryRoot . '/' . $path)) {
            $this->unavailable();
            return;
        }
        try {
            $seed = (new CanonicalRecordRepository($this->repositoryRoot))->loadApprovalSeed($path);
        } catch (CanonicalRecordParseException) {
            $this->unavailable();
            return;
        }
        $history = $this->repositoryHistory($path);
        $latestSha = $history[0]['sha'] ?? '';
        $committed = $latestSha !== '' ? $this->git(['show', '--no-ext-diff', $latestSha . ':' . $path]) : null;
        $current = @file_get_contents($this->repositoryRoot . '/' . $path);
        $matchingCommit = $committed !== null && $current !== false && $committed === $current ? $latestSha : '';
        $this->services->sendHtml($this->services->renderPageTemplate('root_approval.php', [
            'seed' => $seed,
            'keySlug' => $keySlug,
            'profile' => ProfileRepository::byIdentityId($this->services->pdo(), $seed->approvedIdentityId),
            'sourcePath' => $path,
            'sourceHref' => $this->services->activityService()->sourcePathHref($path, ''),
            'repositoryHistory' => $history,
            'matchingCommit' => $matchingCommit,
            'currentVersionDiffers' => $committed !== null && $current !== false && $committed !== $current,
            'committedSourceHref' => $matchingCommit !== '' ? $this->services->activityService()->sourcePathHref($path, $matchingCommit) : '',
            'commitHref' => $matchingCommit !== '' ? $this->services->activityService()->sourceCommitHref($matchingCommit) : '',
        ], 'Root approval', 'profiles'), 200);
    }

    /** @return list<array{sha:string, committed_at:string, subject:string, href:string}> */
    private function repositoryHistory(string $path): array
    {
        $output = $this->git(['log', '-10', '--format=%H%x00%cI%x00%s', 'HEAD', '--', $path]);
        $history = [];
        foreach (explode("\n", trim($output ?? '')) as $line) {
            $fields = explode("\0", $line, 3);
            if (count($fields) !== 3 || !SourcePathValidator::isValidCommitSha($fields[0])) continue;
            $history[] = ['sha' => $fields[0], 'committed_at' => $fields[1], 'subject' => $fields[2],
                'href' => $this->services->activityService()->sourceCommitHref($fields[0])];
        }
        return $history;
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

    private function unavailable(): void
    {
        $this->services->sendHtml($this->services->renderMessagePage('Root approval unavailable',
            'Root approval unavailable', 'No valid root approval record is available for this key.', 'profiles'), 404);
    }
}
