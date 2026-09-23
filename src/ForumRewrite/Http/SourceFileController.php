<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Canonical\SourcePathValidator;

/**
 * Thirteenth Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md
 * and docs/plans/codebase_cleanup_audit_findings_v1.md): /source/current/*,
 * /source/blob/{commit}/*, and /source/commits/{commit}.
 *
 * sourceCommitDetails() stays on Application and is passed in as a bound
 * closure: it reads through sourceCommitFiles(), which shares a per-request
 * memoization cache (sourceCommitFileManifestCache) with the activity
 * feed's commit-manifest rendering - a real cross-route dependency, not
 * just an unnoticed one, so it wasn't moved. The two validators used
 * throughout (SourcePathValidator) are shared well beyond this route group
 * (the activity feed's source links, the account page's public-key link) -
 * confirmed via call-site count before extracting rather than duplicated.
 */
final class SourceFileController
{
    /**
     * @param \Closure(string): (?string) $sourceCommitDetails
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly string $repositoryRoot,
        private readonly \Closure $sourceCommitDetails,
    ) {
    }

    public function currentFile(string $encodedRelativePath): void
    {
        $relativePath = SourcePathValidator::normalizeRoutePath($encodedRelativePath);
        if ($relativePath === null || !SourcePathValidator::isValidCanonicalPath($relativePath)) {
            $this->routeServices->sendText("Invalid source path\n", 400);
            return;
        }

        $contents = $this->readCurrentFile($relativePath);
        if ($contents === null) {
            $this->routeServices->sendText("Source not found\n", 404);
            return;
        }

        $this->routeServices->sendText($contents, 200);
    }

    public function blob(string $commitSha, string $encodedRelativePath): void
    {
        if (!SourcePathValidator::isValidCommitSha($commitSha)) {
            $this->routeServices->sendText("Invalid source commit\n", 400);
            return;
        }

        $relativePath = SourcePathValidator::normalizeRoutePath($encodedRelativePath);
        if ($relativePath === null || !SourcePathValidator::isValidCanonicalPath($relativePath)) {
            $this->routeServices->sendText("Invalid source path\n", 400);
            return;
        }

        $contents = $this->readBlob($commitSha, $relativePath);
        if ($contents === null) {
            $this->routeServices->sendText("Source not found\n", 404);
            return;
        }

        $this->routeServices->sendText($contents, 200);
    }

    public function commit(string $commitSha): void
    {
        if (!SourcePathValidator::isValidCommitSha($commitSha)) {
            $this->routeServices->sendText("Invalid source commit\n", 400);
            return;
        }

        $details = ($this->sourceCommitDetails)($commitSha);
        if ($details === null) {
            $this->routeServices->sendText("Commit not found\n", 404);
            return;
        }

        $this->routeServices->sendText($details, 200);
    }

    private function readCurrentFile(string $relativePath): ?string
    {
        if (!SourcePathValidator::currentPathExists($this->repositoryRoot, $relativePath)) {
            return null;
        }

        $contents = file_get_contents($this->repositoryRoot . '/' . $relativePath);

        return $contents === false ? null : $contents;
    }

    private function readBlob(string $commitSha, string $relativePath): ?string
    {
        if (!is_dir($this->repositoryRoot . '/.git')) {
            return null;
        }

        $object = $commitSha . ':' . $relativePath;
        $command = sprintf(
            'git -C %s show --no-ext-diff %s 2>/dev/null',
            escapeshellarg($this->repositoryRoot),
            escapeshellarg($object)
        );
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            return null;
        }

        return implode("\n", $output) . "\n";
    }
}
