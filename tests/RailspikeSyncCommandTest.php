<?php

declare(strict_types=1);

final class RailspikeSyncCommandTest
{
    public function testHelpAndInvalidOptions(): void
    {
        [$code, $out] = $this->command(['bash', dirname(__DIR__) . '/v3', '--help']);
        assertSame(0, $code);
        assertStringContains('./v3 railspike sync', $out);
        foreach (['railspike', 'fdp'] as $name) {
            [$code, $out, $err] = $this->sync(null, ['--help'], $name);
            assertSame(0, $code);
            assertStringContains('--repository-root=', $out);
            assertStringContains('--remote-url=', $out);
            if ($name === 'fdp') {
                assertStringContains('Deprecated:', $err);
            }
            [$code, , $err] = $this->sync(null, ['--unknown'], $name);
            assertSame(1, $code);
            assertStringContains('Unknown Railspike sync option', $err);
        }
    }

    public function testUpdateAliasAndNoopPreserveFdpAndOrdinaryCloneWorks(): void
    {
        $this->fixture(function (string $host, string $up, string $root): void {
            $this->change($up, 'second');
            [$code, $out, $err] = $this->sync($host, ['--remote-url=' . $up]);
            assertSame(0, $code, $out . $err);
            assertSame('second', file_get_contents($host . '/docs/railspike/content.txt'));
            assertSame($up, trim($this->git($host, 'remote', 'get-url', 'railspike')));
            $head = $this->git($host, 'rev-parse', 'HEAD');
            [$code, $out, $err] = $this->sync($host, ['--remote-url=' . $up], 'fdp');
            assertSame(0, $code, $out . $err);
            assertStringContains('Deprecated:', $err);
            assertSame($head, $this->git($host, 'rev-parse', 'HEAD'));
            $this->change($up, 'third');
            [$code, $out, $err] = $this->sync($host, ['--remote-url=' . $up], 'fdp');
            assertSame(0, $code, $out . $err);
            assertSame('third', file_get_contents($host . '/docs/railspike/content.txt'));
            assertSame('legacy', file_get_contents($host . '/docs/fdp/README.md'));
            $clone = $root . '/clone';
            $this->git($root, 'clone', '--quiet', '--no-local', $host, $clone);
            $this->identity($clone);
            assertTrue(is_file($clone . '/docs/railspike/RAILSPIKE.md'));
            $this->change($up, 'fourth');
            [$code, $out, $err] = $this->sync($clone, ['--remote-url=' . $up]);
            assertSame(0, $code, $out . $err);
            assertSame('fourth', file_get_contents($clone . '/docs/railspike/content.txt'));
        });
    }

    public function testRejectsDirtyAndUnexpectedSourcesWithoutMutation(): void
    {
        $this->fixture(function (string $host, string $up): void {
            $this->git($host, 'remote', 'add', 'railspike', '/unexpected/source');
            $this->reject($host, [], 'not the canonical source');
            file_put_contents($host . '/untracked.txt', 'keep');
            $this->reject($host, ['--remote-url=' . $up], 'clean working tree');
            unlink($host . '/untracked.txt');
            file_put_contents($host . '/docs/railspike/content.txt', 'keep local');
            $this->reject($host, ['--remote-url=' . $up], 'clean working tree');
            $this->git($host, 'add', '.');
            $this->reject($host, ['--remote-url=' . $up], 'clean working tree');
        });
    }

    public function testOverrideReplacesUnexpectedSourceOnlyAfterSuccess(): void
    {
        $this->fixture(function (string $host, string $up): void {
            $this->git($host, 'remote', 'add', 'railspike', '/unexpected/source');
            $this->change($up, 'override');
            [$code, $out, $err] = $this->sync($host, ['--remote-url=' . $up]);
            assertSame(0, $code, $out . $err);
            assertSame($up, trim($this->git($host, 'remote', 'get-url', 'railspike')));
        });
    }

    public function testUnmigratedAndMissingMetadataTargetsAreUnchanged(): void
    {
        $this->fixture(function (string $host, string $up, string $root): void {
            $old = $root . '/old';
            mkdir($old);
            $this->git($old, 'init', '--quiet');
            $this->identity($old);
            mkdir($old . '/docs/fdp', 0777, true);
            file_put_contents($old . '/docs/fdp/README.md', 'legacy');
            $this->commit($old, 'old');
            $this->reject($old, ['--remote-url=' . $up], 'not migrated');
            $this->reject($old, ['--remote-url=' . $up], 'not migrated', 'fdp');
            mkdir($old . '/docs/railspike');
            file_put_contents($old . '/docs/railspike/RAILSPIKE.md', 'copied');
            $this->commit($old, 'copied without history');
            $this->reject($old, ['--remote-url=' . $up], 'Missing Railspike subtree metadata');
        });
    }

    public function testFetchFailureDoesNotChangeContentOrConfiguration(): void
    {
        $this->fixture(function (string $host, string $up): void {
            $this->git($host, 'remote', 'add', 'railspike', $up);
            $this->reject($host, ['--remote-url=/nonexistent/railspike-source', '--ref=v-x'], 'Railspike fetch of tag');
        });
    }

    public function testMergeConflictIsRecoverableWithoutLosingLocalChanges(): void
    {
        $this->fixture(function (string $host, string $up): void {
            file_put_contents($host . '/docs/railspike/content.txt', 'local');
            $this->commit($host, 'local instruction customization');
            $before = $this->snapshot($host);
            $this->change($up, 'upstream conflict');
            [$code, , $err] = $this->sync($host, ['--remote-url=' . $up]);
            assertSame(1, $code);
            assertStringContains('git merge --abort', $err);
            assertTrue(trim($this->git($host, 'diff', '--name-only', '--diff-filter=U')) !== '');
            $this->reject($host, ['--remote-url=' . $up], 'existing Git operation');
            $this->git($host, 'merge', '--abort');
            assertSame($before, $this->snapshot($host));
            assertSame('local', file_get_contents($host . '/docs/railspike/content.txt'));
        });
    }

    public function testDefaultRefIsPinnedAndBranchesAreRefused(): void
    {
        $this->fixture(function (string $host, string $up): void {
            $this->change($up, 'newer');
            $before = $this->snapshot($host);
            [$code, $out, $err] = $this->command(['bash', dirname(__DIR__) . '/v3', 'railspike', 'sync',
                '--repository-root=' . $host, '--remote-url=' . $up, '--ref=main']);
            assertSame(1, $code, $out . $err);
            assertStringContains('fetch of tag main failed', $err);
            [$code, $out, $err] = $this->command(['bash', dirname(__DIR__) . '/v3', 'railspike', 'sync',
                '--repository-root=' . $host, '--remote-url=' . $up]);
            assertSame(1, $code, $out . $err);
            assertStringContains('fetch of tag v0.5.0-beta.1 failed', $err);
            assertSame($before, $this->snapshot($host));
            assertSame('initial', file_get_contents($host . '/docs/railspike/content.txt'));
        });
    }

    public function testRefusesDowngradeAndRecordsSourceAfterUpdate(): void
    {
        $this->fixture(function (string $host, string $up): void {
            $this->change($up, 'second');
            $record = "- Source: x\n- Ref: tag v-initial\n- Commit: " . trim($this->git($up, 'rev-parse', 'v-initial')) . "\n";
            file_put_contents($host . '/docs/railspike/RAILSPIKE_SOURCE.md', $record);
            $this->commit($host, 'record');
            [$code, $out, $err] = $this->sync($host, ['--remote-url=' . $up, '--ref=v-second']);
            assertSame(0, $code, $out . $err);
            assertStringContains('- Ref: tag v-second', file_get_contents($host . '/docs/railspike/RAILSPIKE_SOURCE.md'));
            assertSame('', trim($this->git($host, 'status', '--porcelain')));
            [$code, $out] = $this->sync($host, ['--remote-url=' . $up, '--ref=v-second']);
            assertSame(0, $code);
            assertStringContains('nothing to do', $out);
            $this->reject($host, ['--remote-url=' . $up, '--ref=v-initial'], 'does not contain the recorded');
        });
    }

    public function testRejectsInvalidRootAndEmptySource(): void
    {
        $this->fixture(function (string $host, string $up, string $root): void {
            foreach (['', $root . '/missing', $host . '/docs'] as $target) {
                [$code] = $this->sync($target, ['--remote-url=' . $up]);
                assertSame(1, $code);
            }
            $this->reject($host, ['--remote-url='], 'nonempty');
            $this->reject($host, ['--remote-url=-bad'], 'cannot start with a dash');
        });
    }

    public function testRejectsRewrittenUpstreamWithoutMerging(): void
    {
        $this->fixture(function (string $host, string $up, string $root): void {
            $other = $root . '/other';
            mkdir($other);
            $this->git($other, 'init', '--quiet', '--initial-branch=main');
            $this->identity($other);
            file_put_contents($other . '/RAILSPIKE.md', 'unrelated');
            $this->commit($other, 'unrelated source');
            $this->git($other, 'tag', 'v-unrelated');
            $this->reject($host, ['--remote-url=' . $other], 'does not contain the recorded');
        });
    }

    public function testRejectsMissingEntrypointAndAmbiguousRemote(): void
    {
        $this->fixture(function (string $host, string $up): void {
            $this->git($host, 'remote', 'add', 'railspike', $up);
            $this->git($host, 'config', '--add', 'remote.railspike.url', '/other/source');
            $this->reject($host, ['--remote-url=' . $up], 'multiple URLs');
            $this->git($host, 'remote', 'remove', 'railspike');
            unlink($up . '/RAILSPIKE.md');
            $this->commit($up, 'remove entrypoint');
            $this->git($up, 'tag', 'v-no-entrypoint');
            $this->reject($host, ['--remote-url=' . $up], 'missing RAILSPIKE.md');
        });
    }

    private function reject(string $host, array $args, string $message, string $name = 'railspike'): void
    {
        $before = $this->snapshot($host);
        [$code, $out, $err] = $this->sync($host, $args, $name);
        assertSame(1, $code, $out . $err);
        assertStringContains($message, $err);
        assertSame($before, $this->snapshot($host));
    }

    private function snapshot(string $host): array
    {
        return [
            $this->git($host, 'rev-parse', 'HEAD'),
            $this->git($host, 'status', '--porcelain'),
            $this->git($host, 'diff', 'HEAD'),
            file_get_contents($host . '/.git/config'),
            file_get_contents($host . '/docs/fdp/README.md'),
        ];
    }

    private function fixture(callable $test): void
    {
        $root = sys_get_temp_dir() . '/railspike-sync-' . bin2hex(random_bytes(8));
        mkdir($root);
        try {
            $up = $root . '/upstream';
            $host = $root . '/host';
            foreach ([$up, $host] as $repo) {
                mkdir($repo);
                $this->git($repo, 'init', '--quiet', '--initial-branch=main');
                $this->identity($repo);
            }
            file_put_contents($up . '/RAILSPIKE.md', '# Railspike');
            $this->change($up, 'initial');
            mkdir($host . '/docs/fdp', 0777, true);
            file_put_contents($host . '/docs/fdp/README.md', 'legacy');
            $this->commit($host, 'seed');
            $this->git($host, 'subtree', 'add', '--prefix=docs/railspike', $up, 'main', '--squash');
            $test($host, $up, $root);
        } finally {
            $this->remove($root);
        }
    }

    private function identity(string $repo): void
    {
        $this->git($repo, 'config', 'user.name', 'Test');
        $this->git($repo, 'config', 'user.email', 'test@example.invalid');
        $this->git($repo, 'config', 'commit.gpgsign', 'false');
    }

    private function change(string $repo, string $content): void
    {
        file_put_contents($repo . '/content.txt', $content);
        $this->commit($repo, $content);
        $this->git($repo, 'tag', 'v-' . preg_replace('/[^a-z0-9]+/', '-', $content));
    }

    private function commit(string $repo, string $message): void
    {
        $this->git($repo, 'add', '.');
        $this->git($repo, 'commit', '--quiet', '-m', $message);
    }

    private function git(string $repo, string ...$args): string
    {
        [$code, $out, $err] = $this->command(['git', '-C', $repo, ...$args]);
        assertSame(0, $code, $out . $err);
        return $out;
    }

    private function sync(?string $repo, array $args, string $name = 'railspike'): array
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--ref=')) {
                $explicitRef = true;
            } elseif (str_starts_with($arg, '--remote-url=') && is_dir(substr($arg, 13) . '/.git')) {
                $source = substr($arg, 13);
            }
        }
        if (empty($explicitRef) && isset($source)) {
            [$code, $latest] = $this->command(['git', '-C', $source, 'describe', '--tags', '--abbrev=0', 'HEAD']);
            $latest = $code === 0 ? trim($latest) : '';
            if ($latest !== '') {
                $args[] = '--ref=' . $latest;
            }
        }
        return $this->command(['bash', dirname(__DIR__) . '/v3', $name, 'sync',
            ...($repo === null ? [] : ['--repository-root=' . $repo]), ...$args]);
    }

    private function command(array $args): array
    {
        $out = tmpfile();
        $err = tmpfile();
        $process = proc_open($args, [0 => ['pipe', 'r'], 1 => $out, 2 => $err], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start test command');
        }
        fclose($pipes[0]);
        $code = proc_close($process);
        rewind($out);
        rewind($err);
        $result = [$code, stream_get_contents($out), stream_get_contents($err)];
        fclose($out);
        fclose($err);
        return $result;
    }

    private function remove(string $path): void
    {
        if (!is_dir($path) || is_link($path)) {
            unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') {
                $this->remove($path . '/' . $name);
            }
        }
        rmdir($path);
    }
}
