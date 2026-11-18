<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Import\InstanceImportRequests;
use ForumRewrite\Import\LastImportSummary;
use ForumRewrite\Support\LocalDevelopmentImport;

/**
 * The local start page (`/` and `/local/` under `./v3 start`). Its import card queues the same
 * `instance_import` task as Tools → Import and shows the same progress; the guard here is the
 * local one (LocalDevelopmentImport::authorize), not an operator session.
 */
final class LocalDevelopmentPageController
{
    /** Recorded as Requested-By: a fresh clone has no signed-in identity. */
    public const REQUESTER = 'local-development';

    private const ACTIONS = ['import', 'resume', 'status', 'start_worker'];

    public function __construct(
        private readonly RouteServices $routes,
        private readonly LocalDevelopmentImport $import,
        private readonly InstanceImportRequests $requests,
        private readonly LastImportSummary $lastImport,
    ) {}

    /**
     * POST `/local/` with `token` and `action` = import | resume | status | start_worker. The
     * page script sends JSON with `X-Requested-With: ForumImport` and gets JSON back; a plain
     * form post is answered with a redirect to the page, or the page with the refusal.
     */
    public function handle(string $method): void
    {
        $headers = ['Cache-Control: no-store, private', 'X-Robots-Tag: noindex'];
        if ($method === 'GET' || $method === 'HEAD') {
            $this->routes->sendHtml($this->render(null), 200, $headers);
            return;
        }
        if ($method !== 'POST') {
            $this->routes->sendHtml($this->render('Use the import form below.'), 405, [...$headers, 'Allow: GET, POST']);
            return;
        }
        $wantsJson = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'ForumImport';
        $input = $this->routes->requestData([]);
        if (!$this->import->authorize($_SERVER, $input['token'] ?? null)) {
            $this->refuse($wantsJson, 403, 'Import was not started. Reload this page and try again from this local address.', $input, $headers);
            return;
        }
        $action = is_string($input['action'] ?? null) ? $input['action'] : '';
        if (!in_array($action, self::ACTIONS, true)) {
            $this->refuse($wantsJson, 400, 'Unknown import action.', $input, $headers);
            return;
        }
        $result = $this->requests->perform($action, is_string($input['source'] ?? null) ? $input['source'] : '', self::REQUESTER);
        if ($wantsJson) {
            $this->routes->sendJson($result['payload'], $result['code'], $headers);
            return;
        }
        if ($result['code'] !== 200) {
            $this->refuse(false, $result['code'], (string) ($result['payload']['error'] ?? 'Request refused.'), $input, $headers);
            return;
        }
        // The page reads the queued import's state; a reload never repeats the request.
        $this->routes->sendRedirect('/local/', 'Import queued.', 303, $headers, 'board');
    }

    /**
     * @param array<string, mixed> $input
     * @param list<string> $headers
     */
    private function refuse(bool $json, int $status, string $error, array $input, array $headers): void
    {
        if ($json) {
            $this->routes->sendJson(['status' => 'error', 'error' => $error], $status, $headers);
            return;
        }
        $this->routes->sendHtml($this->render($error, is_string($input['source'] ?? null) ? $input['source'] : null), $status, $headers);
    }

    private function render(?string $notice, ?string $postedSource = null): string
    {
        $state = $this->requests->state();
        // The source to offer: the interrupted import's, the one just typed, the last one tried, then a known instance.
        $source = ($state['pending']['source'] ?? '') !== '' ? $state['pending']['source']
            : ($postedSource ?? (($state['task']['source'] ?? '') !== '' ? $state['task']['source']
                : ($state['known'][0]['url'] ?? '')));

        $log = $this->requests->latestLog();
        $result = InstanceImportRequests::resultFromLog((string) $log);

        return $this->routes->renderPageTemplate('local_development.php', [
            'source' => $source, 'token' => $this->import->token(), 'importState' => $state,
            'notice' => $notice, 'log' => $log, 'result' => $result,
            // A refused request from the Change source form reopens it with what was typed.
            'changeOpen' => $notice !== null && $postedSource !== null,
            // The newest finished import, from the page or the terminal; null on a copy that never imported.
            'last' => $this->lastImport->read($result['run_id']),
        ], 'Local development', 'board', ['/assets/import_control.js', '/assets/openpgp_loader.js', '/assets/local_message_pull.js']);
    }
}
