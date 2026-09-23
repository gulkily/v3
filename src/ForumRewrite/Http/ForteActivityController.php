<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

/**
 * Twenty-fourth Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md
 * and docs/plans/activity_subsystem_extraction_plan_v1.md, "step 2"): the
 * page-shell route handlers for the Forte activity views, now that
 * ActivityService (the data layer) exists. Starting with
 * /api/forte_commit_detail - the smallest of the four - per the sub-plan's
 * recommended order; /forte/activity/ and /api/forte_activity_page are next
 * (they share this same closure list), classic /activity last (it alone
 * needs several more, unrelated to activity data itself).
 */
final class ForteActivityController
{
    /**
     * @param \Closure(): bool $commitsCapabilityAvailable
     * @param \Closure(): void $enqueueReadModelRecovery
     * @param \Closure(): void $sendReadModelCapabilityUnavailable
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $commitsCapabilityAvailable,
        private readonly \Closure $enqueueReadModelRecovery,
        private readonly \Closure $sendReadModelCapabilityUnavailable,
    ) {
    }

    /**
     * A commit's full file manifest is fetched on demand, not pre-rendered
     * for every loaded row the way activity items' detail articles are -
     * some commits touch thousands of files, and only one is ever viewed at
     * a time, so eagerly rendering all of them (as Stage 3's row list does)
     * would recreate the exact page-bloat problem the shared-manifest-block
     * mechanism was built to work around.
     *
     * @param array<string, mixed> $query
     */
    public function commitDetail(array $query): void
    {
        if (!($this->commitsCapabilityAvailable)()) {
            ($this->enqueueReadModelRecovery)();
            ($this->sendReadModelCapabilityUnavailable)();
            return;
        }

        $sha = (string) ($query['sha'] ?? '');
        if (preg_match('/^[0-9a-f]{40}$/', $sha) !== 1) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'invalid sha'], 400);
            return;
        }

        $activityService = $this->routeServices->activityService();
        $files = $activityService->activityCommitManifest($sha);
        if ($files === null) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'commit not found'], 404);
            return;
        }

        $html = $this->routeServices->renderFragment('partials/activity_commit_manifest.php', [
            'files' => $files,
            'commit_sha' => $sha,
            'commit_href' => $activityService->sourceCommitHref($sha) ?? '',
        ]);

        $this->routeServices->sendJson(['status' => 'ok', 'html' => $html], 200);
    }
}
