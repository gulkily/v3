<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;

final class LocalAppSmokeTest
{
    private string $databasePath;
    private string $repositoryRoot;

    public function __construct()
    {
        $this->repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';
        $this->databasePath = sys_get_temp_dir() . '/forum-rewrite-smoke-' . bin2hex(random_bytes(6)) . '.sqlite3';
    }

    public function testRebuildCommandCreatesDatabase(): void
    {
        @unlink($this->databasePath);
        $command = sprintf(
            'php %s %s %s',
            escapeshellarg(__DIR__ . '/../scripts/rebuild_read_model.php'),
            escapeshellarg($this->repositoryRoot),
            escapeshellarg($this->databasePath),
        );
        exec($command, $output, $exitCode);

        assertSame(0, $exitCode);
        assertTrue(is_file($this->databasePath));
    }

    public function testApplicationRendersCoreRoutes(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $board = $this->render($application, '/');
        $thread = $this->render($application, '/threads/root-001');
        $post = $this->render($application, '/posts/root-001');
        $instance = $this->render($application, '/instance/');
        $profile = $this->render($application, '/profiles/openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
        $username = $this->render($application, '/user/guest');
        $composeThread = $this->render($application, '/compose/thread');
        $composeReply = $this->render($application, '/compose/reply?thread_id=root-001&parent_id=root-001');
        $account = $this->render($application, '/account/key/');
        $activity = $this->render($application, '/activity/?view=content');
        $llms = $this->render($application, '/llms.txt');

        assertStringContains('Board', $board);
        assertStringContains('Hello world', $thread);
        assertOrdered($thread, 'Post <a href="/posts/root-001">root-001</a>', 'Post <a href="/posts/reply-001">reply-001</a>');
        assertStringContains('First line preview.', $post);
        assertStringContains('Demo instance', $instance);
        assertStringContains('Identity ID:', $profile);
        assertStringContains('Visible username:', $username);
        assertStringContains('Compose Thread', $composeThread);
        assertStringContains('Thread ID:', $composeReply);
        assertStringContains('identity hint cookie', strtolower($account));
        assertStringContains('View: content', $activity);
        assertStringContains('GET /api/list_index', $llms);
    }

    public function testApplicationRendersTextApisAndRss(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $apiIndex = $this->render($application, '/api/');
        $listIndex = $this->render($application, '/api/list_index');
        $thread = $this->render($application, '/api/get_thread?thread_id=root-001');
        $post = $this->render($application, '/api/get_post?post_id=root-001');
        $profile = $this->render($application, '/api/get_profile?profile_slug=openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954');
        $boardRss = $this->render($application, '/?format=rss');
        $threadRss = $this->render($application, '/threads/root-001?format=rss');
        $activityRss = $this->render($application, '/activity/?view=all&format=rss');

        assertStringContains('GET /api/get_thread?thread_id=<id>', $apiIndex);
        assertStringContains("root-001\tHello world\t1", $listIndex);
        assertStringContains('Thread-ID: root-001', $thread);
        assertStringContains('Post-ID: root-001', $post);
        assertStringContains('Profile-Slug: openpgp-0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', $profile);
        assertStringContains('<rss version="2.0">', $boardRss);
        assertStringContains('<title>Hello world</title>', $threadRss);
        assertStringContains('<title>Activity all</title>', $activityRss);
    }

    public function testIdentityHintRouteSetsCookieValue(): void
    {
        @unlink($this->databasePath);
        $application = new Application(
            dirname(__DIR__),
            $this->repositoryRoot,
            $this->databasePath,
        );

        $_COOKIE = [];
        $response = $this->renderMethod($application, 'POST', '/api/set_identity_hint?identity_hint=OpenPGP-Example');
        $account = $this->render($application, '/account/key/');

        assertStringContains('identity_hint=openpgp-example', $response);
        assertSame('openpgp-example', $_COOKIE['identity_hint'] ?? null);
        assertStringContains('openpgp-example', $account);
    }

    private function render(Application $application, string $path): string
    {
        return $this->renderMethod($application, 'GET', $path);
    }

    private function renderMethod(Application $application, string $method, string $path): string
    {
        ob_start();
        $application->handle($method, $path);
        return (string) ob_get_clean();
    }
}

function assertStringContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output contains: ' . $needle);
    }
}

function assertOrdered(string $haystack, string $first, string $second): void
{
    $firstPos = strpos($haystack, $first);
    $secondPos = strpos($haystack, $second);
    if ($firstPos === false || $secondPos === false || $firstPos >= $secondPos) {
        throw new RuntimeException('Failed asserting that output ordering is correct.');
    }
}
