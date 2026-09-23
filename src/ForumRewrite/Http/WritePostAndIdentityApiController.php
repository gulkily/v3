<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Write\IdentityBootstrapTimingException;
use RuntimeException;

/**
 * Twenty-first Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md):
 * the direct write/prepare API trio - /api/create_thread, /api/create_reply,
 * /api/prepare_thread, /api/prepare_reply, /api/create_prepared_post,
 * /api/prepare_identity, /api/create_identity. All eight handlers were
 * already pure "requestData() -> writer()->x() -> mergeResultTimings() ->
 * send*()" with every dependency already on RouteServices from the
 * write-flow slice, except sendJson() - moved onto RouteServices here
 * (mirroring sendText()/sendHtml()/sendXml() already there) since it's a
 * pure response sender with 69 call sites, not route-specific logic.
 * Needed zero closures.
 */
final class WritePostAndIdentityApiController
{
    public function __construct(
        private readonly RouteServices $routeServices,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     */
    public function createThread(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->routeServices->sendText("method not allowed\n", 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        try {
            $result = $this->routeServices->writer()->createThread($input);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $this->routeServices->sendText(
                "status=ok\npost_id={$result['post_id']}\nthread_id={$result['thread_id']}\ncommit_sha={$result['commit_sha']}\n",
                200,
                $this->routeServices->serverTimingHeaders($result)
            );
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
    public function createReply(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->routeServices->sendText("method not allowed\n", 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
        try {
            $result = $this->routeServices->writer()->createReply($input);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $this->routeServices->sendText(
                "status=ok\npost_id={$result['post_id']}\nthread_id={$result['thread_id']}\ncommit_sha={$result['commit_sha']}\n",
                200,
                $this->routeServices->serverTimingHeaders($result)
            );
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
    public function prepareThread(string $method, array $query): void
    {
        $this->preparePost($method, $query, 'thread');
    }

    /**
     * @param array<string, mixed> $query
     */
    public function prepareReply(string $method, array $query): void
    {
        $this->preparePost($method, $query, 'reply');
    }

    /**
     * @param array<string, mixed> $query
     */
    private function preparePost(string $method, array $query, string $kind): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);

        try {
            $result = $kind === 'reply'
                ? $this->routeServices->writer()->prepareReply($input)
                : $this->routeServices->writer()->prepareThread($input);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $headers = $this->routeServices->serverTimingHeaders($result);
            unset($result['timings']);
            $this->routeServices->sendJson($result, 200, $headers);
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function createPreparedPost(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);

        try {
            $result = $this->routeServices->writer()->createPreparedPost($input);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $headers = $this->routeServices->serverTimingHeaders($result);
            unset($result['timings']);
            $this->routeServices->sendJson($result, 200, $headers);
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function prepareIdentity(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }

        try {
            $phaseStartedAt = hrtime(true);
            $input = $this->routeServices->requestData($query);
            $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
            $result = $this->routeServices->writer()->prepareIdentityBootstrap($input);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $headers = $this->routeServices->serverTimingHeaders($result);
            unset($result['timings']);
            $this->routeServices->sendJson($result, 200, $headers);
        } catch (IdentityBootstrapTimingException $exception) {
            $this->routeServices->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal(array_merge($timings, $exception->timings()), $totalStartedAt)])
            );
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }

    /**
     * @param array<string, mixed> $query
     */
    public function createIdentity(string $method, array $query): void
    {
        $totalStartedAt = hrtime(true);
        $timings = [];
        if ($method !== 'POST') {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'method not allowed'], 405);
            return;
        }

        try {
            $phaseStartedAt = hrtime(true);
            $input = $this->routeServices->requestData($query);
            $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);
            $result = $this->routeServices->writer()->createIdentityBootstrap($input);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $headers = $this->routeServices->serverTimingHeaders($result);
            unset($result['timings']);
            $this->routeServices->sendJson($result, 200, $headers);
        } catch (IdentityBootstrapTimingException $exception) {
            $this->routeServices->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal(array_merge($timings, $exception->timings()), $totalStartedAt)])
            );
        } catch (RuntimeException $exception) {
            $this->routeServices->sendJson(
                ['status' => 'error', 'error' => $exception->getMessage()],
                400,
                $this->routeServices->serverTimingHeaders(['timings' => $this->routeServices->timingsWithTotal($timings, $totalStartedAt)])
            );
        }
    }
}
