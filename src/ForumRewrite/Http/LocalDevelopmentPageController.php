<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\Support\LocalDevelopmentImport;
use Throwable;

final class LocalDevelopmentPageController
{
    public function __construct(private readonly RouteServices $routes, private readonly LocalDevelopmentImport $import) {}

    public function handle(string $method): void
    {
        $source = $this->import->pendingSource() ?? (is_string($_POST['source'] ?? null) ? $_POST['source'] : 'https://zenmemes.com');
        $result = null;
        $error = null;
        $status = 200;
        if ($method === 'POST') {
            if (!$this->import->authorize($_SERVER, $_POST['token'] ?? null)) {
                $error = 'Import was not started. Reload this page and try again from this local address.';
                $status = 403;
            } else {
                // The CLI supervisor bounds the run, including cleanup after a deadline.
                ignore_user_abort(true);
                set_time_limit(330);
                try {
                    $result = $this->import->run($source, ($_POST['action'] ?? '') === 'resume');
                    if ($result['code'] !== 0) {
                        $error = $result['code'] === 2
                            ? 'Some content needs review or images could not be copied. Check import details. Local content is available below.'
                            : 'Import did not finish. Check import details, then retry or resume below.';
                        if (preg_match('/^Import failed: (.+)$/m', $result['output'], $match)) $error = $match[1] . ' Retry or resume below.';
                    }
                } catch (Throwable $exception) { $error = $exception->getMessage(); }
            }
        } elseif ($method !== 'GET') {
            header('Allow: GET, POST');
            $status = 405;
            $error = 'Use the import form below.';
        }
        $this->routes->sendHtml($this->routes->renderPageTemplate('local_development.php', [
            'source' => $source, 'token' => $this->import->token(), 'pending' => $this->import->pending(),
            'result' => $result, 'error' => $error,
        ], 'Local development', 'board', ['/assets/local_development.js']), $status, [
            'Cache-Control: no-store, private', 'X-Robots-Tag: noindex',
        ]);
    }
}
