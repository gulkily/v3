<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

require __DIR__ . '/../autoload.php';

final class PresentationProfileMatrixTest
{
    public function testProfilesRenderTheirRegisteredPresentationSelections(): void
    {
        $application = $this->application();
        $matrix = [
            'zenmemes' => [
                'boardPath' => '/?view=all&sort=newest',
                'boardMarker' => 'class="card thread-card"',
                'navigationMarker' => '>Board</a>',
                'composePath' => '/compose/thread',
                'composeMarker' => '<h1>Compose Thread</h1>',
                'aboutHackable' => false,
                'brandedTheme' => null,
            ],
            'chouse' => [
                'boardPath' => '/?view=all&sort=newest',
                'boardMarker' => 'class="card thread-card"',
                'navigationMarker' => '>Board</a>',
                'composePath' => '/compose/thread',
                'composeMarker' => '<h1>Compose Thread</h1>',
                'aboutHackable' => true,
                'brandedTheme' => 'chouse',
            ],
            'qdb' => [
                'boardPath' => '/latest',
                'boardMarker' => 'class="card post-card quote-card"',
                'navigationMarker' => '>Welcome</a>',
                'composePath' => '/add',
                'composeMarker' => 'class="stack qdb-add-page"',
                'aboutHackable' => false,
                'brandedTheme' => 'qdb',
            ],
        ];

        foreach ($matrix as $profileId => $expected) {
            putenv('FORUM_SITE_ID=' . $profileId);
            try {
                $board = $this->render($application, $expected['boardPath']);
                $about = $this->render($application, '/about/');
                $compose = $this->render($application, $expected['composePath']);

                $this->assertContains($profileId, 'board card', $expected['boardMarker'], $board);
                $this->assertContains($profileId, 'navigation', $expected['navigationMarker'], $board);
                $this->assertContains($profileId, 'compose surface', $expected['composeMarker'], $compose);
                assertSame($expected['aboutHackable'], str_contains($about, 'Hackable by design'));
                assertSame($expected['brandedTheme'] !== null, str_contains($board, 'data-theme-option="' . ($expected['brandedTheme'] ?? 'no-branded-theme') . '"'));
            } finally {
                putenv('FORUM_SITE_ID');
            }
        }
    }

    private function application(): Application
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-presentation-matrix-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
        $this->runCommand($repositoryRoot, 'git init');
        $this->runCommand($repositoryRoot, 'git config user.name "Forum Rewrite"');
        $this->runCommand($repositoryRoot, 'git config user.email "forum-rewrite@example.invalid"');
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Initialize test repository"');
        file_put_contents(
            $repositoryRoot . '/records/posts/thread-presentation-matrix.txt',
            "Post-ID: thread-presentation-matrix\nCreated-At: 2026-10-06T00:00:00Z\nBoard-Tags: general\nSubject: Presentation matrix\n\nVisible matrix thread.\n",
        );
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Add presentation matrix thread"');

        $databasePath = sys_get_temp_dir() . '/forum-rewrite-presentation-matrix-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        (new ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot)))->rebuild();
        $threadCount = (int) (new \PDO('sqlite:' . $databasePath))->query('SELECT COUNT(*) FROM threads')->fetchColumn();
        if ($threadCount === 0) {
            throw new RuntimeException('Presentation matrix read model has no threads.');
        }

        return new Application(
            dirname(__DIR__),
            $repositoryRoot,
            $databasePath,
        );
    }

    private function render(Application $application, string $requestUri): string
    {
        ob_start();
        $application->handle('GET', $requestUri);

        return (string) ob_get_clean();
    }

    private function assertContains(string $profileId, string $surface, string $expected, string $html): void
    {
        if (!str_contains($html, $expected)) {
            throw new RuntimeException("{$profileId} {$surface} did not contain {$expected}.");
        }
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
