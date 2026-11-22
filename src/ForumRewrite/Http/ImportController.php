<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Import\InstanceImportRequests;
use ForumRewrite\Tools\ToolsPageSupport;

/**
 * `/tools/import/`: import another instance's public content from the site.
 *
 * Modeled on Tools → Deploy. The page and its state render only for a session-authenticated
 * root-approved operator, decided on the server. Imports run in the task worker through
 * InstanceImportTask; the page starts them and shows their progress and the last result.
 * Queueing and state reading are in InstanceImportRequests, shared with the local start page.
 */
final class ImportController
{
    /** Seconds after a launch with the task still queued before the page says it didn't start. */
    public const START_TIMEOUT_SECONDS = InstanceImportRequests::START_TIMEOUT_SECONDS;

    /**
     * @param (\Closure():string)|null $launchWorker starts one detached worker; returns launched|unavailable|failed
     * @param (\Closure():int)|null $clock
     */
    private readonly InstanceImportRequests $requests;

    public function __construct(
        private readonly RouteServices $routeServices,
        string $repositoryRoot,
        string $queueDatabasePath,
        ?\Closure $launchWorker = null,
        ?\Closure $clock = null,
    ) {
        $this->requests = new InstanceImportRequests($repositoryRoot, $queueDatabasePath, $launchWorker, $clock);
    }

    public function render(?string $notice = null): string
    {
        $canImport = $this->routeServices->securityReviewAllowed();

        return $this->routeServices->renderPageTemplate(
            'tools_import.php',
            [
                'toolNavOptions' => ToolsPageSupport::navOptions('import'),
                'canImport' => $canImport,
                'importState' => $canImport ? $this->state() : null,
                'importNotice' => $notice,
            ],
            'Import',
            'tools',
            $canImport ? ['/assets/import_control.js'] : [],
        );
    }

    /**
     * POST /tools/import/ with `action` = status | import | resume | cancel; `import` takes
     * `source`. The page script sends JSON with `X-Requested-With: ForumImport` and gets JSON
     * back; a plain form post (no script) gets the page with a notice.
     */
    public function submit(array $query): void
    {
        $wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'ForumImport';
        $headers = ['Cache-Control: no-store'];
        if (!SameOriginRequest::check()) {
            $this->respond($wantsJson, 403, ['status' => 'error', 'error' => 'Same-origin request required.'], $headers);
            return;
        }
        if (!$this->routeServices->securityReviewAllowed()) {
            $this->respond($wantsJson, 403, ['status' => 'error', 'error' => 'Importing requires a signed-in root-approved operator.'], $headers);
            return;
        }
        $input = $this->routeServices->requestData($query);
        $result = $this->requests->perform(
            (string) ($input['action'] ?? ''),
            (string) ($input['source'] ?? ''),
            $this->routeServices->viewerSessions()->identityId(),
        );
        $this->respond($wantsJson, $result['code'], $result['payload'], $headers);
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
        $notice = $payload['status'] === 'ok' ? (string) ($payload['message'] ?? '') : (string) ($payload['error'] ?? 'Request refused.');
        $this->routeServices->sendHtml($this->render($notice), $status, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        return $this->requests->state();
    }
}
