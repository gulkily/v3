<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\ThreadSubjectRecordParser;
use ForumRewrite\Write\LocalWriteService;

final class LocalWriteServiceThreadSubjectTest
{
    public function testSetThreadSubjectIfEmptyWritesRecordAndUpdatesReadModel(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment('root-no-subject');
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $recordPathsBefore = glob($repositoryRoot . '/records/thread-subjects/*.txt') ?: [];

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $wroteRecord = $service->setThreadSubjectIfEmpty('root-no-subject', 'Rick Astley - Never Gonna Give You Up');

        assertTrue($wroteRecord);

        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-no-subject');
        assertStringContains('Rick Astley - Never Gonna Give You Up', $threadPage);

        $recordPathsAfter = glob($repositoryRoot . '/records/thread-subjects/*.txt') ?: [];
        $newRecordPaths = array_values(array_diff($recordPathsAfter, $recordPathsBefore));
        assertSame(1, count($newRecordPaths));

        $record = (new ThreadSubjectRecordParser())->parse((string) file_get_contents($newRecordPaths[0]));
        assertSame('root-no-subject', $record->threadId);
        assertSame('set', $record->operation);
        assertSame('Rick Astley - Never Gonna Give You Up', $record->subject);
        assertNullValue($record->authorIdentityId);
    }

    public function testSetThreadSubjectIfEmptyReturnsFalseAndDoesNotWriteWhenSubjectAlreadySet(): void
    {
        [$repositoryRoot, $databasePath, $artifactRoot] = $this->createTempEnvironment(null);
        $application = new Application(dirname(__DIR__), $repositoryRoot, $databasePath, $artifactRoot);
        $this->renderMethod($application, 'GET', '/');

        $recordCountBefore = count(glob($repositoryRoot . '/records/thread-subjects/*.txt') ?: []);

        $service = new LocalWriteService($repositoryRoot, $databasePath, $artifactRoot, new CanonicalRecordRepository($repositoryRoot));
        $wroteRecord = $service->setThreadSubjectIfEmpty('root-001', 'Should Never Be Written');

        assertFalse($wroteRecord);

        $recordCountAfter = count(glob($repositoryRoot . '/records/thread-subjects/*.txt') ?: []);
        assertSame($recordCountBefore, $recordCountAfter);

        $threadPage = $this->renderMethod($application, 'GET', '/threads/root-001');
        assertStringContains('Hello world', $threadPage);
        assertFalse(str_contains($threadPage, 'Should Never Be Written'));
    }

    /**
     * @return array{string,string,string}
     */
    private function createTempEnvironment(?string $noSubjectRootPostId): array
    {
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-thread-subject-write-repo-' . bin2hex(random_bytes(6));
        mkdir($repositoryRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);

        if ($noSubjectRootPostId !== null) {
            file_put_contents(
                $repositoryRoot . '/records/posts/' . $noSubjectRootPostId . '.txt',
                "Post-ID: {$noSubjectRootPostId}\nCreated-At: 2026-04-11T12:00:00Z\nBoard-Tags: general\n\nhttps://www.youtube.com/watch?v=dQw4w9WgXcQ\n"
            );
        }

        $databasePath = sys_get_temp_dir() . '/forum-rewrite-thread-subject-write-db-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-thread-subject-write-public-' . bin2hex(random_bytes(6));
        mkdir($artifactRoot, 0777, true);
        $this->initializeGitRepository($repositoryRoot);

        return [$repositoryRoot, $databasePath, $artifactRoot];
    }

    private function initializeGitRepository(string $repositoryRoot): void
    {
        $this->runCommand($repositoryRoot, 'git init');
        $this->runCommand($repositoryRoot, 'git config user.name "Forum Rewrite"');
        $this->runCommand($repositoryRoot, 'git config user.email "forum-rewrite@example.invalid"');
        $this->runCommand($repositoryRoot, 'git add .');
        $this->runCommand($repositoryRoot, 'git commit -m "Initialize test repository"');
    }

    private function renderMethod(Application $application, string $method, string $path): string
    {
        ob_start();
        $application->handle($method, $path);
        return (string) ob_get_clean();
    }

    private function runCommand(string $workdir, string $command): string
    {
        $output = [];
        $exitCode = 0;
        exec('cd ' . escapeshellarg($workdir) . ' && ' . $command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }

                continue;
            }

            copy($item->getPathname(), $targetPath);
        }
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
                . '.'
            );
        }
    }
}

if (!function_exists('assertTrue')) {
    function assertTrue(bool $condition): void
    {
        if (!$condition) {
            throw new RuntimeException('Failed asserting that condition is true.');
        }
    }
}

if (!function_exists('assertFalse')) {
    function assertFalse(bool $condition): void
    {
        if ($condition) {
            throw new RuntimeException('Failed asserting that condition is false.');
        }
    }
}

if (!function_exists('assertNullValue')) {
    function assertNullValue(mixed $value): void
    {
        if ($value !== null) {
            throw new RuntimeException('Failed asserting that value is null. Got ' . var_export($value, true) . '.');
        }
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that string contains "' . $needle . '".');
        }
    }
}
