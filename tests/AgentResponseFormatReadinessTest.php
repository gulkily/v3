<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Canonical\AgentResponseMetadata as Meta;
use ForumRewrite\Support\AgentResponseFormatReadiness;

final class AgentResponseFormatReadinessTest
{
    public function testReadOnlyCliListsLegacyAndDependentExpansionAndMatchesJson(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('source');
        $source = $w->post('source');
        $initial = ['type' => Meta::SCRIPTURE, 'source_post_id' => 'source', 'source_hash' => str_repeat('a', 64)];
        $expanded = ['type' => Meta::EXPANSION, 'source_post_id' => 'source', 'source_hash' => str_repeat('a', 64),
            'expansion_of' => 'initial', 'initial_hash' => str_repeat('b', 64)];
        $w->put($root . '/records/posts/source.txt', $source);
        $path = $root . '/records/posts/2026/04/10/initial.txt';
        $w->put($path, $w->post('initial', "Thread-ID: source\nParent-ID: source\nAgent-Response: " . json_encode($initial) . "\n"));
        $w->put($root . '/records/posts/expanded.txt', $w->post('expanded', "Thread-ID: source\nParent-ID: initial\n" . Meta::toHeaders($expanded)));
        $w->git($root);
        $before = $this->tree($root);
        $command = [dirname(__DIR__) . '/v3', 'agent-reply', 'format-readiness', '--repository-root=' . $root];
        [$code, $text] = $w->command($command);
        assertSame(2, $code, $text);
        assertStringContains('Legacy-Post-ID: initial', $text);
        assertStringContains('Dependent-Expansion: expanded', $text);
        [$code, $json] = $w->command([...$command, '--json']);
        assertSame(2, $code, $json);
        $result = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        assertSame((new AgentResponseFormatReadiness($root))->inspect(), $result);
        assertSame(['initial'], $result['dependent_expansions'][0]['legacy_dependencies']);
        assertSame($before, $this->tree($root));
        assertSame(false, is_dir($root . '/state'));
        $w->put($path, $w->post('initial', "Thread-ID: source\nParent-ID: source\n" . Meta::toHeaders($initial)));
        [$code, $text] = $w->command($command);
        assertSame(0, $code, $text); assertStringContains('Archive-Coverage: unknown', $text);
        assertStringContains('Retirement-Decision: not authorized', $text);
        assertSame(0, $w->command([...$command, '--help'])[0]);
        assertSame(1, $w->command([...$command, '--invalid'])[0]);
    }

    public function testMalformedIncompleteAndChangingScansCannotClearRetirement(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('source');
        assertSame(false, (new AgentResponseFormatReadiness($root))->inspect()['scan_complete']);
        $w->put($root . '/records/posts/source.txt', $w->post('source'));
        $meta = ['type' => Meta::SCRIPTURE, 'source_post_id' => 'source', 'source_hash' => str_repeat('a', 64)];
        $bad = $w->post('bad', "Thread-ID: source\nParent-ID: source\n" . Meta::toHeaders($meta) . 'Agent-Response: ' . json_encode($meta) . "\n");
        $w->put($root . '/records/posts/bad.txt', $bad);
        $result = (new AgentResponseFormatReadiness($root))->inspect();
        assertSame(false, $result['live_repository_ready']);
        assertSame('bad', $result['legacy_records'][0]['post_id']);
        assertSame(true, $result['scan_complete']);
        unlink($root . '/records/posts/bad.txt');
        symlink('/absent', $root . '/records/posts/unreadable.txt');
        assertSame(false, (new AgentResponseFormatReadiness($root))->inspect()['scan_complete']);
        unlink($root . '/records/posts/unreadable.txt');
        $changed = (new AgentResponseFormatReadiness($root))->inspect(function () use ($w, $root): void {
            $w->put($root . '/records/posts/added.txt', $w->post('added'));
        });
        assertSame(false, $changed['scan_complete']);
        assertStringContains('changed during inspection', implode(' ', $changed['blockers']));
        $w->put($root . '/records/posts/2026/04/10/source.txt', $w->post('source'));
        assertStringContains('Duplicate Post-ID', implode(' ', (new AgentResponseFormatReadiness($root))->inspect()['blockers']));
        [$code, $text] = $w->command([dirname(__DIR__) . '/v3', 'agent-reply', 'format-readiness', '--repository-root=' . $root]);
        assertSame(1, $code, $text);
    }

    public function testUnreadableOversizedAndMissingDependenciesRemainBlocked(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('source');
        $path = $root . '/records/posts/source.txt';
        $w->put($path, $w->post('source'));
        chmod($path, 0000);
        try {
            if (!is_readable($path)) assertSame(false, (new AgentResponseFormatReadiness($root))->inspect()['scan_complete']);
        } finally { chmod($path, 0600); }
        $large = $root . '/records/posts/large.txt';
        $handle = fopen($large, 'wb'); ftruncate($handle, \ForumRewrite\Canonical\RecordLimits::TEXT + 1); fclose($handle);
        assertSame(false, (new AgentResponseFormatReadiness($root))->inspect()['scan_complete']);
        unlink($large);
        $meta = ['type' => Meta::EXPANSION, 'source_post_id' => 'source', 'source_hash' => str_repeat('a', 64),
            'expansion_of' => 'missing', 'initial_hash' => str_repeat('b', 64)];
        $w->put($root . '/records/posts/expansion.txt', $w->post('expansion', "Thread-ID: source\nParent-ID: missing\n" . Meta::toHeaders($meta)));
        $result = (new AgentResponseFormatReadiness($root))->inspect();
        assertSame(true, $result['scan_complete']);
        assertSame(false, $result['live_repository_ready']);
        assertStringContains('Missing dependency missing', implode(' ', $result['blockers']));
    }

    public function testFreshProcessesWorkWithLegacyDecoderUnavailable(): void
    {
        $w = new ImportTestWorkspace();
        $blocker = $w->root . '/block.php';
        $w->put($blocker, '<?php spl_autoload_register(function ($class) { if ($class === "ForumRewrite\\Canonical\\LegacyAgentResponseMetadata") throw new RuntimeException("Legacy agent-response decoder unavailable"); }, true, true);');
        // A PHP wrapper carries the same absence into v3/import child processes.
        $wrapper = $w->root . '/bin/php';
        $w->put($wrapper, "#!/bin/sh\nexec " . escapeshellarg(PHP_BINARY) . ' -d ' . escapeshellarg('auto_prepend_file=' . $blocker) . ' "$@"' . "\n");
        chmod($wrapper, 0700);
        [$code, $out] = $w->command(['env', 'PATH=' . dirname($wrapper) . ':' . getenv('PATH'), $wrapper,
            __DIR__ . '/run.php', 'WriteApiSmokeTest::testReadableResponseTransferRoundTrip']);
        assertSame(0, $code, $out);
        assertStringContains('1 passed, 0 failed', $out);
        $root = $w->repository('legacy');
        $w->put($root . '/records/posts/old.txt', $w->post('old', "Thread-ID: source\nParent-ID: source\nAgent-Response: " . json_encode([
            'type' => Meta::SCRIPTURE, 'source_post_id' => 'source', 'source_hash' => str_repeat('a', 64)]) . "\n"));
        [$code, $out] = $w->command([$wrapper, dirname(__DIR__) . '/scripts/agent_response_format_readiness.php', '--repository-root=' . $root]);
        assertSame(1, $code, $out); assertStringContains('Legacy agent-response decoder unavailable', $out);
    }

    private function tree(string $root): array
    {
        $result = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) $result[$file->getPathname()] = hash_file('sha256', $file->getPathname());
        }
        ksort($result); return $result;
    }
}
