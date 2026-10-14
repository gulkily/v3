<?php

declare(strict_types=1);

/** Isolated, fixed-content histories for rebuild parity and process-count checks. */
final class RebuildHistoryFixture
{
    public readonly string $root;
    public readonly string $repository;

    public function __construct(int $labels = 12, int $reactions = 0, int $flags = 0)
    {
        $this->root = sys_get_temp_dir() . '/rebuild-history-' . bin2hex(random_bytes(6));
        $this->repository = $this->root . '/repository';
        mkdir($this->repository, 0777, true);
        $source = dirname(__DIR__) . '/fixtures/parity_minimal_v1';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $target = $this->repository . '/' . $iterator->getSubPathName();
            $item->isDir() ? mkdir($target, 0777, true) : copy($item->getPathname(), $target);
        }
        $this->git('init', '-b', 'main');
        $this->git('config', 'user.email', 'fixture@example.invalid');
        $this->git('config', 'user.name', 'Rebuild fixture');
        $this->git('add', '.');
        $this->git('commit', '-qm', 'initial');
        foreach (['thread-labels' => $labels, 'post-reactions' => $reactions, 'feature-flag-changes' => $flags] as $family => $count) {
            if (!is_dir($this->repository . '/records/' . $family)) {
                mkdir($this->repository . '/records/' . $family);
            }
            for ($i = 0; $i < $count; $i++) {
                $this->write($family, $i);
                $this->git('add', '.');
                $this->git('commit', '-qm', $family . ' ' . $i);
            }
        }
    }

    public function write(string $family, int $i): string
    {
        $prefix = ['thread-labels' => 'thread-label', 'post-reactions' => 'post-reaction', 'feature-flag-changes' => 'feature-flag-change'][$family];
        $id = $prefix . '-fixture-' . $i;
        $identity = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
        $fields = match ($family) {
            'thread-labels' => "Thread-ID: root-001\nOperation: add\nLabels: fixture-$i\nAuthor-Identity-ID: $identity\n",
            'post-reactions' => "Post-ID: root-001\nOperation: add\nTags: like fixture-$i\nAuthor-Identity-ID: $identity\n",
            'feature-flag-changes' => "Flag-Key: APPROVED_MEMBERS_ONLY\nValue: false\nOperator-Identity-ID: $identity\n",
        };
        $path = 'records/' . $family . '/' . $id . '.txt';
        file_put_contents($this->repository . '/' . $path, "Record-ID: $id\nCreated-At: 2026-06-01T00:00:00Z\n" . $fields . "\n");
        return $path;
    }

    public function git(string ...$args): string
    {
        exec('git -C ' . escapeshellarg($this->repository) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1', $output, $status);
        if ($status !== 0) {
            throw new RuntimeException(implode("\n", $output));
        }
        return trim(implode("\n", $output));
    }

    public function remove(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    }
}
