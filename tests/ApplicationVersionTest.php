<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\ReadModel\ReadModelMetadata;

require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

final class ApplicationVersionTest
{
    public function testContentCommitsLeaveCodeVersionStableAndCodeCommitsChangeIt(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        $code = $fixture->root . '/code';
        mkdir($code);
        symlink(dirname(__DIR__) . '/templates', $code . '/templates');
        symlink(dirname(__DIR__) . '/public', $code . '/public');
        $previous = getenv('FORUM_NAV_CACHED_COPY_ENABLED');
        putenv('FORUM_NAV_CACHED_COPY_ENABLED=true');
        try {
            $this->git($code, 'init', '-b', 'main');
            $this->git($code, 'commit', '--allow-empty', '-m', 'Code initial');
            $initial = $this->git($code, 'rev-parse', 'HEAD');
            assertSame($initial . "\n", $this->get($fixture, $code, '/api/version'));
            $html = $this->get($fixture, $code, '/');
            assertStringContains('meta name="app-version" content="' . $initial . '"', $html);
            assertStringContains('meta name="page-version" content="' . $initial . ':' . $fixture->git('rev-parse', 'HEAD') . '"', $html);

            $fixture->git('commit', '--allow-empty', '-m', 'New post');
            assertSame($initial . "\n", $this->get($fixture, $code, '/api/version'));
            $contentHtml = $this->get($fixture, $code, '/');
            assertStringContains('meta name="app-version" content="' . $initial . '"', $contentHtml);
            assertStringContains('meta name="page-version" content="' . $initial . ':' . $fixture->git('rev-parse', 'HEAD') . '"', $contentHtml);

            $this->git($code, 'commit', '--allow-empty', '-m', 'Deploy code');
            $latest = $this->git($code, 'rev-parse', 'HEAD');
            assertTrue($initial !== $latest);
            assertSame($latest . "\n", $this->get($fixture, $code, '/api/version'));
            assertStringContains('meta name="app-version" content="' . $latest . '"', $this->get($fixture, $code, '/'));
            assertStringContains('Version: ' . $latest, $this->get($fixture, $code, '/api/checkin'));
        } finally {
            $previous === false ? putenv('FORUM_NAV_CACHED_COPY_ENABLED') : putenv('FORUM_NAV_CACHED_COPY_ENABLED=' . $previous);
            unlink($code . '/templates');
            unlink($code . '/public');
            $fixture->remove();
        }
    }

    public function testLinkedWorktreePackedAndDetachedCodeCheckoutsResolveTheirOwnHead(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $head = $fixture->git('rev-parse', 'HEAD');
            $fixture->git('pack-refs', '--all');
            assertSame($head . "\n", $this->get($fixture, $fixture->repository, '/api/version'));
            $worktree = $fixture->root . '/worktree';
            $fixture->git('worktree', 'add', '--detach', $worktree, 'HEAD');
            assertTrue(is_file($worktree . '/.git'));
            $this->git($worktree, 'commit', '--allow-empty', '-m', 'Worktree code');
            $worktreeHead = $this->git($worktree, 'rev-parse', 'HEAD');
            assertTrue($worktreeHead !== $head);
            assertSame($worktreeHead . "\n", $this->get($fixture, $worktree, '/api/version'));
            assertSame($worktreeHead, ReadModelMetadata::repositoryHead($worktree));
            $fixture->git('checkout', '--detach');
            assertSame($head . "\n", $this->get($fixture, $fixture->repository, '/api/version'));
        } finally {
            $fixture->remove();
        }
    }

    public function testSourceOnlyAndBrokenCheckoutsNeverUseTheContentOrEnclosingRevision(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $source = $fixture->repository . '/source-only';
            mkdir($source);
            assertSame("no-git\n", $this->get($fixture, $source, '/api/version'));
            file_put_contents($source . '/.git', "gitdir: missing-git-directory\n");
            assertSame("git-error\n", $this->get($fixture, $source, '/api/version'));
        } finally {
            $fixture->remove();
        }
    }

    private function get(RebuildHistoryFixture $fixture, string $code, string $path): string
    {
        ob_start();
        try {
            (new Application($code, $fixture->repository, $fixture->root . '/read.sqlite3'))->handle('GET', $path);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    private function git(string $root, string ...$args): string
    {
        $command = ['git', '-C', $root, '-c', 'user.name=Version test', '-c', 'user.email=version@example.invalid', ...$args];
        exec(implode(' ', array_map('escapeshellarg', $command)) . ' 2>&1', $output, $status);
        assertSame(0, $status, implode("\n", $output));
        return trim(implode("\n", $output));
    }
}
