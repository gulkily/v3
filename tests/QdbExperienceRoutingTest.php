<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;

final class QdbExperienceRoutingTest
{
    public function testClassicQdbRoutesRemainAvailableForTheQdbProfile(): void
    {
        $application = $this->application();
        putenv('FORUM_SITE_ID=qdb');
        try {
            foreach (['/', '/latest', '/latest/1', '/top', '/top/1', '/leetness', '/add', '/random', '/search?search=fixture', '/?latest=1', '/?top=1', '/?leetness', '/?add', '/?random', '/?search=fixture'] as $requestUri) {
                assertSame(200, $this->statusFor($application, $requestUri), 'Expected QDB route to succeed: ' . $requestUri);
            }
        } finally {
            putenv('FORUM_SITE_ID');
        }
    }

    public function testQdbOnlyPathsAndQueryRoutesAreRejectedOutsideQdb(): void
    {
        foreach (['zenmemes', 'chouse'] as $profile) {
            $application = $this->application();
            putenv('FORUM_SITE_ID=' . $profile);
            try {
                foreach (['/latest', '/top', '/leetness', '/add', '/random', '/search', '/?latest=1', '/?top=1', '/?leetness', '/?add', '/?random', '/?search=fixture'] as $requestUri) {
                    assertSame(404, $this->statusFor($application, $requestUri), "Expected {$profile} to reject {$requestUri}");
                }
            } finally {
                putenv('FORUM_SITE_ID');
            }
        }
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
