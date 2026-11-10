<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Deploy\AppUpdate;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\Tools\ToolsPageSupport;
use PDO;

/**
 * `/tools/deploy/`: update the application checkout from the site.
 *
 * Opening the page never contacts GitHub: it shows AppUpdate::inspect(), the
 * latest queued deploy task and the worker heartbeat. The controls render only
 * for a session-authenticated root-approved operator; this is a security gate,
 * so it is decided on the server like the other operator tool pages.
 */
final class DeployController
{
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly AppUpdate $update,
        private readonly string $queueDatabasePath,
        private readonly ?string $sourceRepositoryUrl = null,
    ) {
    }

    public function render(?string $notice = null): string
    {
        $canDeploy = $this->routeServices->securityReviewAllowed();

        return $this->routeServices->renderPageTemplate(
            'deploy.php',
            [
                'toolNavOptions' => ToolsPageSupport::navOptions('deploy'),
                'canDeploy' => $canDeploy,
                'deployState' => $this->state(),
                'deployNotice' => $notice,
                'commitLinkBase' => $this->commitLinkBase(),
            ],
            'Deploy',
            'tools',
            $canDeploy ? ['/assets/deploy_control.js'] : [],
        );
    }

    /**
     * POST /tools/deploy/ with `action` = status | check | queue | now.
     * The page script sends JSON with `X-Requested-With: ForumDeploy` and gets
     * JSON back; a plain form post (no script) gets the page with a notice.
     */
    public function submit(array $query): void
    {
        $wantsJson = self::scriptHeaderPresent();
        $headers = ['Cache-Control: no-store'];
        if (!$this->sameOrigin()) {
            $this->respond($wantsJson, 403, ['status' => 'error', 'error' => 'Same-origin request required.'], $headers);
            return;
        }
        if (!$this->routeServices->securityReviewAllowed()) {
            $this->respond($wantsJson, 403, ['status' => 'error', 'error' => 'Deploying requires a signed-in root-approved operator.'], $headers);
            return;
        }
        $input = $this->routeServices->requestData($query);
        $action = (string) ($input['action'] ?? '');
        $actor = $this->routeServices->viewerSessions()->identityId();

        $result = match ($action) {
            'status' => ['outcome' => 'status', 'message' => ''],
            'check' => $this->update->check(self::INSTANT_TIMEOUT_SECONDS),
            'queue' => $this->queue($actor),
            'now' => $this->deployNow($actor),
            default => null,
        };
        if ($result === null) {
            $this->respond($wantsJson, 400, ['status' => 'error', 'error' => 'Unknown deploy action.'], $headers);
            return;
        }
        unset($result['state']);
        $this->respond($wantsJson, 200, ['status' => 'ok', 'action' => $action, 'result' => $result, 'state' => $this->publicState()], $headers);
    }

    /**
     * A request from the page script, which reads only JSON. The front controller uses this to
     * answer failures that happen before this controller runs (busy, site data updating) in
     * JSON too, rather than with the visitor's HTML page.
     */
    public static function isScriptRequest(string $method, string $requestUri): bool
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';

        return $method === 'POST' && ($path === '/tools/deploy/' || $path === '/tools/deploy') && self::scriptHeaderPresent();
    }

    private static function scriptHeaderPresent(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'ForumDeploy';
    }

    private const INSTANT_TIMEOUT_SECONDS = 60;

    /**
     * @return array<string, mixed>
     */
    private function queue(string $actor): array
    {
        try {
            $directory = dirname($this->queueDatabasePath);
            if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new \RuntimeException('Task queue directory is not writable.');
            }
            $queued = $this->update->queue(new SqliteTaskQueueStore(new PDO('sqlite:' . $this->queueDatabasePath)), $actor);
        } catch (\Throwable $throwable) {
            return ['outcome' => 'failed', 'reason' => 'queue_unavailable', 'message' => 'Could not queue the deploy: ' . $throwable->getMessage()];
        }

        return ['outcome' => 'queued', 'reason' => null, 'task_id' => $queued['task_id'],
            'message' => $queued['enqueued'] ? 'Queued for the task worker.' : 'A deploy is already queued.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function deployNow(string $actor): array
    {
        // Finish the pull even if the phone drops the connection; the result
        // is in the attempt log either way.
        ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::INSTANT_TIMEOUT_SECONDS * 2 + 30);
        }

        return $this->update->deploy($actor, AppUpdate::MODE_INSTANT, self::INSTANT_TIMEOUT_SECONDS);
    }

    /**
     * @return array<string, mixed>
     */
    private function publicState(): array
    {
        $state = $this->state();

        return [
            'commit' => $state['commit'],
            'behind' => $state['behind'],
            'last_checked_at' => $state['last_checked_at'],
            'busy' => $state['busy'],
            'worker' => $state['worker'],
            'task_status' => $state['task']['status'] ?? null,
            'rebuild_status' => $state['rebuild']['status'] ?? null,
            'rebuild_progress' => $state['rebuild']['progress'] ?? null,
            'latest' => $state['latest'],
        ];
    }

    private function sameOrigin(): bool
    {
        return SameOriginRequest::check();
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $headers
     */
    private function respond(bool $json, int $status, array $payload, array $headers): void
    {
        if ($json) {
            $this->routeServices->sendJson($payload, $status, $headers);
            return;
        }
        $notice = $payload['status'] === 'ok'
            ? (string) ($payload['result']['message'] ?? '')
            : (string) ($payload['error'] ?? 'Request refused.');
        $this->routeServices->sendHtml($this->render($notice), $status, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $state = $this->update->inspect();
        $state['worker'] = 'not_observed';
        $state['worker_last_completed_at'] = null;
        $state['task'] = null;
        $state['rebuild'] = null;
        $store = $this->existingStore();
        if ($store !== null) {
            try {
                $heartbeat = $store->executorHeartbeatStatus();
                $state['worker'] = $heartbeat['status'];
                $state['worker_last_completed_at'] = $heartbeat['last_completed_at'];
                $state['task'] = $store->latestByType(SqliteTaskQueueStore::APP_UPDATE);
                $state['rebuild'] = $this->rebuildState($store);
            } catch (\Throwable) {
                $state['worker'] = 'unavailable';
            }
        }

        return $state;
    }

    /**
     * Incoming commits link to the source repository only on GitHub, whose
     * /commit/<sha> and /compare/<a>...<b> paths are known; other hosts get
     * plain text rather than guessed URLs.
     */
    private function commitLinkBase(): ?string
    {
        $url = $this->sourceRepositoryUrl;
        if ($url === null || strtolower((string) parse_url($url, PHP_URL_HOST)) !== 'github.com') {
            return null;
        }

        return preg_replace('/\.git$/', '', $url);
    }

    /**
     * The latest read-model rebuild task, with its newest progress line, so the page can follow
     * a rebuild a deploy queued.
     *
     * @return array{status: string, progress: ?string, requested_at: ?string}|null
     */
    private function rebuildState(SqliteTaskQueueStore $store): ?array
    {
        $task = $store->latestByType(SqliteTaskQueueStore::REBUILD_READ_MODEL);
        if ($task === null) {
            return null;
        }
        $progress = null;
        foreach ($store->recentTaskProgress(50) as $event) {
            if ((int) $event['task_id'] === (int) $task['id']) {
                $progress = (string) $event['message'];
                break;
            }
        }

        return ['status' => (string) $task['status'], 'progress' => $progress, 'requested_at' => $task['requested_at'] ?? null];
    }

    private function existingStore(): ?SqliteTaskQueueStore
    {
        if (!is_file($this->queueDatabasePath)) {
            return null;
        }
        try {
            return new SqliteTaskQueueStore(new PDO('sqlite:' . $this->queueDatabasePath));
        } catch (\Throwable) {
            return null;
        }
    }
}
