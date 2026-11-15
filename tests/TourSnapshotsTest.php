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

    public function testTourFileParsesSectionsAndExplainsMistakes(): void
    {
        $sections = \ForumRewrite\Tour\TourFile::parse("Schema: tour-v1\n# comment\n\n---\nName: board\nRoute: /\nTitle: A board\n\nFirst line\ncontinues.\n\nSecond paragraph.\n---\nName: key\nRoute: /account/key/\nTitle: No password\n\nOne.\n");
        assertSame(2, count($sections));
        assertSame(['name' => 'board', 'route' => '/', 'title' => 'A board', 'paragraphs' => ['First line continues.', 'Second paragraph.']], $sections[0]);
        assertSame('/account/key/', $sections[1]['route']);

        $refusals = [
            "Name: x\n" => "expected 'Schema: tour-v1'",
            "# no schema\n---\n" => "missing 'Schema: tour-v1'",
            "Schema: tour-v1\n---\nName: a\nRoute: /\n\nBody\n" => "line 3: missing 'Title'",
            "Schema: tour-v1\n---\nName: a\nRoute: /\nTitle: T\nColour: red\n\nBody\n" => "line 6: unknown key 'Colour'",
            "Schema: tour-v1\n---\nName: a\nRoute: /\nTitle: T\n\nBody\n---\nName: a\nRoute: /tags/\nTitle: U\n\nBody\n" => "duplicate Name 'a'",
            "Schema: tour-v1\n---\nName: Big Name\nRoute: /\nTitle: T\n\nBody\n" => 'lowercase letters',
            "Schema: tour-v1\n---\nName: a\nRoute: /tools/\nTitle: T\n\nBody\n" => 'plain public path',
            "Schema: tour-v1\n---\nName: a\nRoute: /\nTitle: T\n\n\n" => 'essay is empty',
        ];
        foreach ($refusals as $text => $message) {
            try {
                \ForumRewrite\Tour\TourFile::parse($text);
                throw new RuntimeException('Expected a refusal: ' . $message);
            } catch (RuntimeException $error) {
                assertStringContains($message, $error->getMessage());
            }
        }
    }

    public function testShippedTourFileIsValid(): void
    {
        $sections = \ForumRewrite\Tour\TourFile::fromFile(dirname(__DIR__) . '/resources/tour.txt');
        assertSame(true, count($sections) >= 5);
    }

    public function testPageHasOneRowPerSectionAndNeverOpensNewTabs(): void
    {
        $sections = \ForumRewrite\Tour\TourFile::fromFile(dirname(__DIR__) . '/resources/tour.txt');
        $html = \ForumRewrite\Tour\TourPage::render(dirname(__DIR__), array_slice($sections, 0, 3), 'Example <Site>');
        assertSame(3, substr_count($html, '<section id="'));
        assertSame(3, substr_count($html, 'data-src="'));
        assertStringContains('data-src="board.html"', $html);
        assertStringContains('<title>Example &lt;Site&gt;</title>', $html);
        assertStringContains('<meta name="robots" content="noindex">', $html);
        foreach (['_blank', 'window.open', 'loading="lazy"'] as $forbidden) {
            assertSame(false, str_contains($html, $forbidden), $forbidden);
        }
    }

    public function testSnapshotsOpenLinksInTheSameTabAndStayUnindexed(): void
    {
        $html = \ForumRewrite\Tour\TourPage::prepareSnapshot('<!DOCTYPE html><html><head lang="en"><meta name="robots" content="index"><title>T</title></head><body><a href="/x">x</a></body></html>');
        assertStringContains('<head lang="en"><meta name="robots" content="noindex"><base target="_top">', $html);
        assertSame(1, substr_count($html, 'name="robots"'));
    }
}
