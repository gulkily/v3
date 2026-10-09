<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ProfileRegressionContract.php';
require_once __DIR__ . '/Support/ProfileRegressionFixture.php';

use ForumRewrite\Application;

final class QdbExperienceRoutingTest
{
    public function testRegisteredProfilesHonorTheSelectedExperienceRoutes(): void
    {
        $application = $this->application();
        $experienceRoutes = ['/latest', '/latest/1', '/top', '/top/1', '/leetness', '/leetness/1', '/add', '/random', '/search?search=fixture', '/?latest=1', '/?top=1', '/?leetness', '/?add', '/?random', '/?search=fixture'];

        foreach (ProfileRegressionContract::all() as $profileId => $contract) {
            putenv('FORUM_SITE_ID=' . $profileId);
            try {
                foreach ($experienceRoutes as $requestUri) {
                    $expectedStatus = $contract['experience'] === 'qdb' ? 200 : 404;
                    assertSame($expectedStatus, $this->statusFor($application, $requestUri), "Unexpected {$contract['experience']} route status for {$requestUri}");
                }
            } finally {
                putenv('FORUM_SITE_ID');
            }
        }
    }

    public function testFourthProfileFixtureUsesTheSameExperienceRouteMatrix(): void
    {
        ProfileRegressionFixture::withFourthProfile(fn (): mixed => $this->testRegisteredProfilesHonorTheSelectedExperienceRoutes());
    }

    public function testQdbAddFormUsesTheQuoteAuthoringOperation(): void
    {
        putenv('FORUM_SITE_ID=qdb');
        try {
            $page = $this->render($this->application(), '/add');
        } finally {
            putenv('FORUM_SITE_ID');
        }

        assertStringContains('action="/add"', $page);
        assertStringContains('data-authoring-operation="quote"', $page);
    }

    private function application(): Application
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-qdb-routes-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        $this->runCommand($repositoryRoot, 'git init');
        $this->runCommand($repositoryRoot, 'git config user.name "Forum Rewrite"');
        $this->runCommand($repositoryRoot, 'git config user.email "forum-rewrite@example.invalid"');
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Initialize test repository"');

        return new Application(
            dirname(__DIR__),
            $repositoryRoot,
            sys_get_temp_dir() . '/forum-rewrite-qdb-routes-db-' . bin2hex(random_bytes(6)) . '.sqlite3',
        );
    }

    private function statusFor(Application $application, string $requestUri): int
    {
        ob_start();
        $application->handle('GET', $requestUri);
        ob_end_clean();

        return http_response_code();
    }

    private function render(Application $application, string $requestUri): string
    {
        ob_start();
        $application->handle('GET', $requestUri);

        return (string) ob_get_clean();
    }

    private function copyDirectory(string $source, string $target): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $destination = $target . '/' . substr($item->getPathname(), strlen($source) + 1);
            if ($item->isDir()) {
                mkdir($destination, 0777, true);
            } else {
                copy($item->getPathname(), $destination);
            }
        }
    }

    private function runCommand(string $cwd, string $command): void
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to run command: ' . $command);
        }
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        assertSame(0, proc_close($process));
    }
}
