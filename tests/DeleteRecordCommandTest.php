<?php

declare(strict_types=1);

use ForumRewrite\Support\LocalRepositoryBootstrap;

/**
 * Operator output of `./v3 delete-record <post-id>`: step lines on stderr while it
 * runs, -q/--quiet keeping only errors and the final line, --json leaving stderr empty.
 */
final class DeleteRecordCommandTest
{
    public function testInspectAndDeletePrintStepsInOrderOnStderr(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            [$status, $stdout, $stderr] = $this->run([$root, $database, $artifacts, '--inspect'], 'root-001');
            assertSame(0, $status);
            assertStringContains('source_path: records/posts/', $stdout);
            $this->assertLinesInOrder($stderr, ['] Reading post root-001', '] Finding labels for thread root-001']);
            assertTrue(preg_match('/^Inspected root-001 in \d+\.\d\ds$/m', $stderr) === 1, $stderr);

            [$status, $stdout, $stderr] = $this->run([$root, $database, $artifacts], 'root-001');
            assertSame(0, $status, $stderr);
            assertStringContains('Source label cleanup accepted: root-001', $stdout);
            $this->assertLinesInOrder($stderr, [
                '] Taking read-model lock',
                '] Reading post root-001',
                '] Finding labels for thread root-001',
                '] Committing removal',
                '] Clearing generated pages',
                '] Rebuilding read model',
                '] Checking result',
            ]);
            assertTrue(preg_match('/^Deleted root-001 in \d+\.\d\ds$/m', $stderr) === 1, $stderr);
            assertSame(false, is_file($root . '/records/posts/root-001.txt'));
        } finally {
            $this->remove($project);
        }
    }

    public function testQuietKeepsOnlyTheFinalLineAndJsonKeepsStderrEmpty(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            [$status, , $stderr] = $this->run([$root, $database, $artifacts, '--inspect', '-q'], 'root-001');
            assertSame(0, $status);
            assertTrue(preg_match('/\A^Inspected root-001 in \d+\.\d\ds\n\z/m', $stderr) === 1, $stderr);

            [$status, $stdout, $stderr] = $this->run([$root, $database, $artifacts, '--inspect', '--json'], 'root-001');
            assertSame(0, $status);
            assertSame('', $stderr);
            assertSame('records/posts/root-001.txt', json_decode($stdout, true)['source_path']);

            [$status, , $stderr] = $this->run([$root, $database, $artifacts, '--quiet'], 'root-001');
            assertSame(0, $status, $stderr);
            assertTrue(preg_match('/\A^Deleted root-001 in \d+\.\d\ds\n\z/m', $stderr) === 1, $stderr);
        } finally {
            $this->remove($project);
        }
    }

    public function testQuietStillReportsErrors(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            [$status, , $stderr] = $this->run([$root, $database, $artifacts, '--inspect', '-q'], 'no-such-post');
            assertTrue($status !== 0);
            assertStringContains('Source evidence unavailable', $stderr);
        } finally {
            $this->remove($project);
        }
    }

    /** @return array{int,string,string} */
    private function run(array $arguments, string $id): array
    {
        $command = implode(' ', array_map('escapeshellarg', [dirname(__DIR__) . '/v3', 'delete-record', $id, ...$arguments]));
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), (string) $stdout, (string) $stderr];
    }

    private function assertLinesInOrder(string $output, array $fragments): void
    {
        $offset = 0;
        foreach ($fragments as $fragment) {
            $position = strpos($output, $fragment, $offset);
            assertTrue($position !== false, "Missing or out of order: {$fragment}\n{$output}");
            $offset = $position + strlen($fragment);
        }
    }

    /** @return array{string,string,string,string} */
    private function environment(): array
    {
        $project = sys_get_temp_dir() . '/forum-rewrite-delete-record-' . bin2hex(random_bytes(6));
        $fixture = $project . '/tests/fixtures/parity_minimal_v1';
        mkdir($fixture, 0777, true);
        $this->copy(__DIR__ . '/fixtures/parity_minimal_v1', $fixture);
        LocalRepositoryBootstrap::initializeLocalRepository($project, $project . '/state/local_repository');
        mkdir($project . '/public', 0777, true);

        return [$project, $project . '/state/local_repository', $project . '/state/cache/post_index.sqlite3', $project . '/public'];
    }

    private function copy(string $source, string $destination): void
    {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $target = $destination . '/' . $items->getSubPathName();
            $item->isDir() ? (is_dir($target) || mkdir($target, 0777, true)) : copy($item->getPathname(), $target);
        }
    }

    private function remove(string $path): void
    {
        exec('rm -rf ' . escapeshellarg($path));
    }
}
