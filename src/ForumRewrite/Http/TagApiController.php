<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use RuntimeException;

/**
 * Sixteenth Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md
 * and docs/plans/codebase_cleanup_audit_findings_v1.md): /api/apply_thread_tag
 * and /api/apply_post_tag, the first pair pulled out of the `/api` group's
 * auth/write bulk (previously deliberately deferred as a whole - most of
 * that group is far more entangled than this pair turned out to be). Both
 * routes needed only what RouteServices already had from the write-flow
 * slice (writer(), requestData(), the timing helpers, sendText()) plus one
 * closure for the viewer-identity lookup, so they were cheap once that
 * infrastructure existed - the same "shared layer built, slice gets cheap"
 * pattern as TagsPageController and ComposeAndAccountKeyController.
 *
 * resolveViewerProfileFromIdentityHint() stays on Application (shared well
 * beyond this pair) and is passed in as a bound closure.
 */
final class TagApiController
{
    /**
     * @param \Closure(): (array<string, mixed>|null) $resolveViewerProfileFromIdentityHint
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly \Closure $resolveViewerProfileFromIdentityHint,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function applyThreadTag(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->routeServices->sendText("method not allowed\n", 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        $timings['viewer_profile'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        if ($viewerProfile === null) {
            $this->routeServices->sendText(
                "error=You must set an identity hint before applying a tag.\n",
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        $input['author_identity_id'] = (string) $viewerProfile['identity_id'];

        try {
            $result = $this->routeServices->writer()->applyThreadTag($input);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $response = "status=ok\n"
                . "thread_id={$result['thread_id']}\n"
                . "tag={$result['tag']}\n"
                . "score_total={$result['score_total']}\n"
                . "viewer_identity_id={$result['author_identity_id']}\n"
                . "viewer_is_approved={$result['viewer_is_approved']}\n"
                . "wrote_record={$result['wrote_record']}\n";
            if (isset($result['commit_sha'])) {
                $response .= "commit_sha={$result['commit_sha']}\n";
            }

            $this->routeServices->sendText($response, 200, $this->routeServices->serverTimingHeaders($result));
        } catch (RuntimeException $exception) {
            $this->routeServices->sendText(
                "error=" . $exception->getMessage() . "\n",
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function applyPostTag(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->routeServices->sendText("method not allowed\n", 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $viewerProfile = ($this->resolveViewerProfileFromIdentityHint)();
        $timings['viewer_profile'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        if ($viewerProfile === null) {
            $this->routeServices->sendText(
                "error=You must set an identity hint before applying a tag.\n",
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        $input['author_identity_id'] = (string) $viewerProfile['identity_id'];

        try {
            $result = $this->routeServices->writer()->applyPostTag($input);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $response = "status=ok\n"
                . "post_id={$result['post_id']}\n"
                . "thread_id={$result['thread_id']}\n"
                . "tag={$result['tag']}\n"
                . "post_score_total={$result['post_score_total']}\n"
                . "approved_flag_count={$result['approved_flag_count']}\n"
                . "is_hidden={$result['is_hidden']}\n"
                . "viewer_identity_id={$result['author_identity_id']}\n"
                . "viewer_is_approved={$result['viewer_is_approved']}\n"
                . "wrote_record={$result['wrote_record']}\n";
            if (isset($result['commit_sha'])) {
                $response .= "commit_sha={$result['commit_sha']}\n";
            }

            $this->routeServices->sendText($response, 200, $this->routeServices->serverTimingHeaders($result));
        } catch (RuntimeException $exception) {
            $this->routeServices->sendText(
                "error=" . $exception->getMessage() . "\n",
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }
}
