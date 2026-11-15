<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/autoload.php';

final class TourSnapshotsTest
{
    public function testTourRoutesArePlainPublicPaths(): void
    {
        foreach (['/', '/agent/', '/calendar', '/threads/root-001', '/account/key/'] as $route) {
            assertSame(true, \ForumRewrite\Tour\TourRoute::valid($route), $route);
        }
        foreach (['', 'agent/', '/?x=1', '/threads/../tools/', '//evil', '/api/version', '/internal/high-traffic/refresh', '/tools/', '/assets/tour/index.html', '/a b'] as $route) {
            assertSame(false, \ForumRewrite\Tour\TourRoute::valid($route), $route);
        }
    }

    public function testTourWorkerRendersLoggedOutPagesWithoutTheRefreshHint(): void
    {
        $root = sys_get_temp_dir() . '/v3-tour-render-' . bin2hex(random_bytes(6));
        mkdir($root);
        $fixture = __DIR__ . '/fixtures/parity_minimal_v1';
        try {
            \ForumRewrite\Host\HighTrafficRenderInput::build(\ForumRewrite\Host\HighTrafficPublication::capture($fixture), $root . '/read.sqlite3');
            $render = static function (string $route) use ($root, $fixture): array {
                $command = [PHP_BINARY, dirname(__DIR__) . '/scripts/tour_render.php', dirname(__DIR__), $fixture, $root . '/read.sqlite3', $root . '/assets', $route, \ForumRewrite\SiteProfileRegistry::active()['name']];
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv(), ['VISITOR_STATISTICS_DATABASE_PATH' => $root . '/visits.sqlite3']));
                fclose($pipes[0]);
                $html = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                return [proc_close($process), $html, $errors];
            };

            // Routes outside the high-traffic allowlist render for the tour.
            foreach (['/agent/' => 'Prompts', '/calendar' => 'Calendar', '/account/key/' => 'Account', '/backup/' => 'Backup'] as $route => $title) {
                [$status, $html, $errors] = $render($route);
                assertSame(0, $status, $route . ': ' . $errors);
                assertStringContains('<title>' . $title, $html);
                assertSame(false, str_contains($html, 'high_traffic'), $route . ' must not carry the refresh hint');
            }

            // A page a logged-out visitor cannot see is refused, not snapshotted.
            [$status, $html] = $render('/unread');
            assertSame(true, $status !== 0);
            assertSame('', $html);

            [$status, , $errors] = $render('/tools/');
            assertSame(1, $status);
            assertStringContains('listed public route', $errors);
        } finally {
            \ForumRewrite\Host\HighTrafficCache::removeTemporaryTree($root);
        }
    }
}
