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

    public function testRefreshWritesSnapshotsKeepsOldOnFailureAndRunsOnce(): void
    {
        $root = sys_get_temp_dir() . '/v3-tour-refresh-' . bin2hex(random_bytes(6));
        mkdir($root . '/repo', 0777, true);
        exec('cp -R ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1/records') . ' ' . escapeshellarg($root . '/repo/records'));
        \ForumRewrite\Host\HighTrafficRenderInput::build(\ForumRewrite\Host\HighTrafficPublication::capture($root . '/repo'), $root . '/read.sqlite3');
        file_put_contents($root . '/tour.txt', "Schema: tour-v1\n---\nName: board\nRoute: /\nTitle: Board\n\nThe board.\n---\nName: gone\nRoute: /threads/no-such-thread\nTitle: Gone\n\nMissing.\n---\nName: private\nRoute: /messages\nTitle: Private\n\nNot for logged-out visitors.\n");
        $snapshots = new \ForumRewrite\Tour\TourSnapshots(dirname(__DIR__), $root . '/repo', $root . '/read.sqlite3', $root . '/public', null, $root . '/tour.txt');
        $state = dirname(__DIR__) . '/state/tour/' . substr(hash('sha256', realpath($root . '/repo')), 0, 16);
        $previous = getenv('VISITOR_STATISTICS_DATABASE_PATH');
        putenv('VISITOR_STATISTICS_DATABASE_PATH=' . $root . '/visits.sqlite3');
        try {
            assertSame(true, $snapshots->needsRefresh());
            mkdir($root . '/public/assets/tour', 0777, true);
            file_put_contents($root . '/public/assets/tour/private.html', 'previous snapshot');
            file_put_contents($root . '/public/assets/tour/gone.html', 'stale snapshot');

            $result = $snapshots->refresh();
            assertSame('refreshed_with_failures', $result['outcome']);
            assertSame('ok', $result['routes']['board']);
            assertSame('not found (left out)', $result['routes']['gone']);
            assertStringContains('failed (kept previous)', $result['routes']['private']);
            assertSame(2, $result['shown']);

            $board = (string) file_get_contents($root . '/public/assets/tour/board.html');
            assertStringContains('<base target="_top">', $board);
            assertStringContains('content="noindex"', $board);
            assertSame('previous snapshot', file_get_contents($root . '/public/assets/tour/private.html'));
            assertSame(false, is_file($root . '/public/assets/tour/gone.html'));
            $page = (string) file_get_contents($root . '/public/assets/tour/index.html');
            assertStringContains('data-src="board.html"', $page);
            assertStringContains('data-src="private.html"', $page);
            assertSame(false, str_contains($page, 'gone.html'));

            $status = $snapshots->status();
            assertSame('idle', $status['State']);
            assertSame('refreshed_with_failures', $status['Outcome']);
            assertSame('ok', $status['Route-board']);
            assertSame(false, $snapshots->needsRefresh());

            $lock = fopen($state . '/refresh.lock', 'c');
            flock($lock, LOCK_EX);
            try {
                assertSame(['outcome' => 'already_running'], $snapshots->refresh());
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }

            touch($root . '/tour.txt', time() + 5);
            assertSame(true, $snapshots->needsRefresh());
        } finally {
            putenv($previous === false ? 'VISITOR_STATISTICS_DATABASE_PATH' : 'VISITOR_STATISTICS_DATABASE_PATH=' . $previous);
            \ForumRewrite\Host\HighTrafficCache::removeTemporaryTree($root);
            \ForumRewrite\Host\HighTrafficCache::removeTemporaryTree($state);
        }
    }

    public function testLauncherStartsOnlyWhenDueAndFailuresRetryHourly(): void
    {
        $root = sys_get_temp_dir() . '/v3-tour-due-' . bin2hex(random_bytes(6));
        mkdir($root . '/public/assets/tour', 0777, true);
        mkdir($root . '/repo', 0777, true);
        file_put_contents($root . '/tour.txt', "Schema: tour-v1\n---\nName: board\nRoute: /\nTitle: Board\n\nThe board.\n");
        $snapshots = new \ForumRewrite\Tour\TourSnapshots($root . '/project', $root . '/repo', $root . '/read.sqlite3', $root . '/public', null, $root . '/tour.txt');
        $state = $root . '/project/state/tour/' . substr(hash('sha256', realpath($root . '/repo')), 0, 16);
        $started = [];
        $launcher = new \ForumRewrite\Tour\TourRefreshLauncher($root . '/project', static function (array $arguments) use (&$started): bool {
            $started[] = $arguments;
            return true;
        });
        $writeStatus = static function (string $finished, string $board) use ($state): void {
            @mkdir($state, 0777, true);
            file_put_contents($state . '/status.txt', "Schema: tour-status-v1\nState: idle\nFinished: {$finished}\nOutcome: refreshed_with_failures\nRoute-board: {$board}\n");
        };
        try {
            assertSame('started', $launcher->startIfDue($snapshots));
            assertSame('refresh', end($started[0]));

            file_put_contents($root . '/public/assets/tour/index.html', 'page');
            touch($root . '/tour.txt', time() - 60);
            $writeStatus(gmdate('Y-m-d\TH:i:s\Z'), 'ok');
            file_put_contents($root . '/public/assets/tour/board.html', 'snapshot');
            assertSame('current', $launcher->startIfDue($snapshots));

            unlink($root . '/public/assets/tour/board.html');
            $writeStatus(gmdate('Y-m-d\TH:i:s\Z'), 'failed (left out): renderer timed out');
            assertSame('current', $launcher->startIfDue($snapshots), 'a fresh failure waits');
            $writeStatus(gmdate('Y-m-d\TH:i:s\Z', time() - 3700), 'failed (left out): renderer timed out');
            assertSame('started', $launcher->startIfDue($snapshots), 'an hour-old failure is retried');
            assertSame(2, count($started));
        } finally {
            \ForumRewrite\Host\HighTrafficCache::removeTemporaryTree($root);
        }
    }

    public function testOperationsRefreshNeedsASameOriginOperatorSession(): void
    {
        $operator = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
        $same = ['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_ORIGIN' => 'http://forum.test'];
        $repository = sys_get_temp_dir() . '/v3-tour-ops-' . bin2hex(random_bytes(6));
        mkdir($repository);
        $state = dirname(__DIR__) . '/state/tour/' . substr(hash('sha256', realpath($repository)), 0, 16);
        mkdir($state, 0777, true);
        file_put_contents($state . '/status.txt', "Schema: tour-status-v1\nState: running\nStarted: 2026-11-15T20:00:00Z\n");
        $lock = fopen($state . '/refresh.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            foreach ([
                'signed out' => [null, $same, 'requires a signed-in root-approved operator'],
                'non-operator session' => ['openpgp:' . str_repeat('ab', 20), $same, 'requires a signed-in root-approved operator'],
                'cross-site' => [$operator, ['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'https://evil.test'], 'Same-origin request required'],
            ] as $name => [$identity, $server, $message]) {
                $page = $this->operationsRequest('POST', $identity, $server, $repository);
                assertStringContains($message, $page, $name);
                assertSame(false, str_contains($page, 'Refresh started'), $name);
            }
            // A refresh is running (this test holds its lock), so the operator's press starts nothing new.
            $page = $this->operationsRequest('POST', $operator, $same, $repository);
            assertStringContains('A refresh is already running.', $page);
            assertStringContains('Refreshing&hellip;', $page);

            assertStringContains('value="refresh-tour" disabled', $this->operationsRequest('GET', null, [], $repository, $operator));
            assertSame(false, str_contains($this->operationsRequest('GET', null, [], $repository), 'refresh-tour'));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            \ForumRewrite\Host\HighTrafficCache::removeTemporaryTree($state);
            @rmdir($repository);
        }
    }

    /** One request per process, as on a server. */
    private function operationsRequest(string $method, ?string $identity, array $server, string $tourRepository, ?string $hint = null): string
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';
[$method, $identity, $server, $tourRepository, $hint] = json_decode($argv[3], true);
putenv('FORUM_REPOSITORY_ROOT=' . $tourRepository);
$databasePath = sys_get_temp_dir() . '/tour-ops-' . bin2hex(random_bytes(6)) . '.sqlite3';
$_COOKIE = [];
if ($identity !== null) TestViewerSession::signIn($identity);
if ($hint !== null) $_COOKIE['identity_hint'] = $hint;
$_SERVER = array_merge($_SERVER, ['HTTP_HOST' => 'forum.test'], $server);
$_POST = $method === 'POST' ? ['action' => 'refresh-tour'] : [];
$app = new \ForumRewrite\Application($argv[1], $argv[2], $databasePath);
ob_start();
$app->handle($method, '/tools/operations/');
echo "\n---PAGE---\n" . ob_get_clean();
@unlink($databasePath);
PHP;
        $output = (string) shell_exec(sprintf(
            'php -r %s %s %s %s 2>&1',
            escapeshellarg($script),
            escapeshellarg(dirname(__DIR__)),
            escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'),
            escapeshellarg((string) json_encode([$method, $identity, $server, $tourRepository, $hint])),
        ));
        return substr($output, (int) strrpos($output, "---PAGE---\n") + 11);
    }

    public function testRetractionSweepRetiresTourSnapshotsOlderThanTheRetraction(): void
    {
        $public = sys_get_temp_dir() . '/v3-tour-sweep-' . bin2hex(random_bytes(6));
        mkdir($public . '/assets/tour', 0777, true);
        file_put_contents($public . '/assets/site.css', 'body {}');
        foreach (['index', 'board', 'calendar'] as $name) {
            file_put_contents($public . '/assets/tour/' . $name . '.html', $name);
            touch($public . '/assets/tour/' . $name . '.html', time() - 600);
        }
        touch($public . '/assets/tour/calendar.html', time());
        $previous = getenv('FORUM_PUBLIC_ARTIFACT_ROOT');
        putenv('FORUM_PUBLIC_ARTIFACT_ROOT=' . $public);
        try {
            $sweep = \ForumRewrite\Retraction\RetractionSweepFactory::create(dirname(__DIR__), $public . '/repo', $public . '/static', $public . '/exchanges.sqlite3', $public . '/sweep.json');
            $roots = (new ReflectionProperty($sweep, 'artifactRoots'))->getValue($sweep);
            assertSame(true, in_array($public . '/assets/tour', $roots, true));

            $result = \ForumRewrite\Retraction\ArtifactSweep::run($roots, time() - 300);
            assertSame(2, $result['cleaned']);
            assertSame(false, is_file($public . '/assets/tour/board.html'));
            assertSame(false, is_file($public . '/assets/tour/index.html'));
            assertSame(true, is_file($public . '/assets/tour/calendar.html'), 'newer than the retraction');
            assertSame(true, is_file($public . '/assets/site.css'));
        } finally {
            putenv($previous === false ? 'FORUM_PUBLIC_ARTIFACT_ROOT' : 'FORUM_PUBLIC_ARTIFACT_ROOT=' . $previous);
            \ForumRewrite\Host\HighTrafficCache::removeTemporaryTree($public);
        }
    }
}
