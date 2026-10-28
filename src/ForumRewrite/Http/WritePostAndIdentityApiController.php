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
        $this->createThreadForAuthoringMode($method, $query, false);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function createQuote(string $method, array $query): void
    {
        $this->createThreadForAuthoringMode($method, $query, true);
    }

    /**
     * @param array<string, mixed> $query
     */
    private function createThreadForAuthoringMode(string $method, array $query, bool $isQdbQuote): void
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
            $result = $isQdbQuote
                ? $this->routeServices->writer()->createQuote($input)
                : $this->routeServices->writer()->createThread($input);
            $this->enqueueFastScore($result);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $this->routeServices->sendText(
                "status=ok\npost_id={$result['post_id']}\nthread_id={$result['thread_id']}\ncommit_sha={$result['commit_sha']}\n"
                    . $this->routeServices->unsignedAttributionLines('post', (string) ($input['author_identity_id'] ?? ''), false),
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
            $this->enqueueFastScore($result);
            $result = $this->routeServices->mergeResultTimings($result, $timings, $totalStartedAt);
            $this->routeServices->sendText(
                "status=ok\npost_id={$result['post_id']}\nthread_id={$result['thread_id']}\ncommit_sha={$result['commit_sha']}\n"
                    . $this->routeServices->unsignedAttributionLines('post', (string) ($input['author_identity_id'] ?? ''), false),
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
        $this->preparePost($method, $query, 'thread', false);
    }

    /**
     * @param array<string, mixed> $query
     */
    public function prepareQuote(string $method, array $query): void
    {
        $this->preparePost($method, $query, 'thread', true);
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
    private function preparePost(string $method, array $query, string $kind, bool $isQdbQuote = false): void
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
                : ($isQdbQuote
                    ? $this->routeServices->writer()->prepareQuote($input)
                    : $this->routeServices->writer()->prepareThread($input));
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

        if (str_starts_with((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'multipart/form-data')
            && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && $_POST === [] && $_FILES === []) {
            $this->routeServices->sendJson(['status' => 'error', 'error' => 'Image request is empty or exceeds the server upload limit. Keep the draft, reselect a file of at most 5 MiB, and ask the operator to check PHP request limits.'], 413);
            return;
        }

        $phaseStartedAt = hrtime(true);
        $input = $this->routeServices->requestData($query);
        $timings['request_data'] = $this->routeServices->elapsedMilliseconds($phaseStartedAt);

        try {
            $imagePath = null;
            if (isset($_FILES['image'])) {
                $file = $_FILES['image'];
                if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                    || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
                    throw new RuntimeException('Image upload failed or exceeds the server request limit; preserve the draft and select the file again.');
                }
                $imagePath = $file['tmp_name'];
                $actual = \ForumRewrite\Images\ImageStore::inspect($imagePath, '');
                $mime = $actual['type'] === 'jpg' ? 'image/jpeg' : 'image/png';
                if (($file['type'] ?? '') !== $mime) {
                    throw new RuntimeException('Uploaded image type does not match its contents. Prepare the image again.');
                }
                if (isset($input['canonical_record_base64'])) {
                    $decoded = base64_decode((string) $input['canonical_record_base64'], true);
                    if ($decoded === false) { throw new RuntimeException('Invalid signed record transport encoding.'); }
                    $input['canonical_record'] = $decoded;
                }
            }
            $result = $this->routeServices->writer()->createPreparedPost($input, $imagePath);
            $this->enqueueFastScore($result);
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

    /** @param array<string, mixed> $result */
    private function enqueueFastScore(array $result): void
    {
        $postId = trim((string) ($result['post_id'] ?? ''));
        if ($postId === '') {
            return;
        }
        try {
            $this->routeServices->enqueueFastScoreForPublishedPost($postId, ($result['fastmod_new_post'] ?? false) === true);
        } catch (\Throwable) {
            error_log('Fastmod enqueue failed after post publication.');
        }
    }

    /** @param array<string, mixed> $query */
    public function prepareProfileName(string $method, array $query): void
    {
        $this->handleWrite($method, $query, fn (array $input): array => $this->routeServices->writer()->prepareProfileName($input));
    }

    /** @param array<string, mixed> $query */
    public function finalizeProfileName(string $method, array $query): void
    {
        $this->handleWrite($method, $query, fn (array $input): array => $this->routeServices->writer()->finalizePreparedProfileName($input));
    }

    /** @param array<string, mixed> $query */
    public function prepareRetraction(string $method, array $query): void
    {
        $this->handleWrite($method, $query, fn (array $input): array => $this->routeServices->writer()->prepareRetraction($input));
    }

    /** @param array<string, mixed> $query */
    public function createPreparedRetraction(string $method, array $query): void
    {
        $this->handleWrite($method, $query, fn (array $input): array => $this->routeServices->writer()->finalizePreparedRetraction($input));
    }

    /**
     * @param array<string, mixed> $query
     * @param callable(array<string, mixed>): array<string, mixed> $write
     */
    private function handleWrite(string $method, array $query, callable $write): void
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
            $result = $write($input);
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
