<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\Support\LocalRepositoryBootstrap;

final class ApprovalSeedCommandTest
{
    private const ID = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testBothSpellingsSeedTheSelectedInstance(): void
    {
        foreach ([['approve'], ['approval', 'seed']] as $command) {
            $this->withFixture(function (string $root, string $db, string $public, string $static) use ($command): void {
                [$code, $out, $err] = $this->runSeed($root, $db, $public, $static, $command);
                assertSame(0, $code, $err);
                assertStringContains('Seeded approval for ' . self::ID, $out);
                $pdo = new PDO('sqlite:' . $db);
                assertSame(1, (int) $pdo->query('SELECT is_approved FROM profiles')->fetchColumn());
                assertSame('root', $pdo->query('SELECT approved_by_label FROM profiles')->fetchColumn());
                assertSame(ReadModelMetadata::repositoryHead($root), ReadModelMetadata::readMetadata($pdo)['repository_head']);
                assertSame('test seed', (new CanonicalRecordRepository($root))->loadApprovalSeed($this->seedPath())->seedReason);
            });
        }
    }

    private function seedPath(): string
    {
        return 'records/approval-seeds/openpgp-' . substr(self::ID, 8) . '.txt';
    }

    private function withFixture(callable $test, bool $git = true): void
    {
        $base = sys_get_temp_dir() . '/approval seed-' . bin2hex(random_bytes(6));
        $root = $base . '/repository';
        $db = $base . '/cache/read.sqlite3';
        $public = $base . '/public';
        $static = $base . '/static';
        mkdir($root, 0777, true);
        mkdir($public);
        mkdir($static);
        try {
            $source = __DIR__ . '/fixtures/parity_minimal_v1';
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $item) {
                $target = $root . '/' . $iterator->getSubPathName();
                $item->isDir() ? mkdir($target, 0777, true) : copy($item->getPathname(), $target);
            }
            unlink($root . '/' . $this->seedPath());
            if ($git) {
                LocalRepositoryBootstrap::initializeGitRepository($root);
            }
            $this->rebuild($root, $db);
            $test($root, $db, $public, $static);
        } finally {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) {
                $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($base);
        }
    }

    private function rebuild(string $root, string $db): void
    {
        (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
    }

    private function runSeed(string $root, string $db, string $public, string $static, array $command = ['approve'], string $reason = 'test seed'): array
    {
        return $this->process([
            dirname(__DIR__) . '/v3', ...$command,
            str_replace(':', '-', strtoupper(self::ID)), $reason, $root, $db,
        ], dirname(__DIR__), array_merge(getenv(), [
            'FORUM_PUBLIC_ARTIFACT_ROOT' => $public,
            'FORUM_STATIC_HTML_ROOT' => $static,
            'FORUM_SITE_ID' => 'chouse',
        ]));
    }

    private function process(array $command, string $cwd, ?array $env = null): array
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }
}
