<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Import\ContentImportPlanner;

final class ContentImportPlannerTest
{
    public function testPublicCoverageKeepsSourceBytesAndExcludesAuthority(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source', true);
        $target = $w->repository('target');
        $w->put($source . '/records/posts/authority.txt', str_replace('Board-Tags: general', 'Board-Tags: identity approval', $w->post('authority')));
        $w->put($source . '/records/posts/invite.txt', str_replace('Board-Tags: general', 'Board-Tags: invitation', $w->post('invite')));
        $plan = (new ContentImportPlanner())->plan($source, $target);
        assertSame('import', $plan['records/posts/root-001.txt']['state']);
        assertSame('import', $plan['records/thread-subjects/thread-subject-20260415153000-ab12cd34.txt']['state']);
        assertSame('excluded', $plan['records/posts/authority.txt']['state']);
        assertSame('excluded', $plan['records/posts/invite.txt']['state']);
        assertSame('excluded', $plan['records/instance/public.txt']['state']);
        assertSame([], ContentImportPlanner::files($target));
        foreach ($plan as $path => $entry) {
            assertSame(hash_file('sha256', $source . '/' . $path), $entry['hash']);
        }
    }

    public function testConflictsRejectDependentRecordsAndSignatures(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target');
        $w->put($source . '/records/posts/root.txt', $w->post('root'));
        $w->put($source . '/records/posts/reply.txt', $w->post('reply', "Thread-ID: root\nParent-ID: root\n"));
        $w->put($source . '/records/posts/root.txt.sig', 'source signature');
        $w->put($target . '/records/posts/root.txt', $w->post('root', '', 'Local version'));
        $plan = (new ContentImportPlanner())->plan($source, $target);
        assertSame('conflict', $plan['records/posts/root.txt']['state']);
        assertSame('invalid', $plan['records/posts/root.txt.sig']['state']);
        assertSame('invalid', $plan['records/posts/reply.txt']['state']);
        assertStringContains('Local version', file_get_contents($target . '/records/posts/root.txt'));
    }

    public function testRepeatIsDuplicateAndMissingDependenciesAreReported(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target');
        foreach ([$source, $target] as $root) {
            $w->put($root . '/records/posts/root.txt', $w->post('root'));
        }
        $w->put($source . '/records/posts/orphan.txt', $w->post('orphan', "Thread-ID: absent\nParent-ID: absent\n"));
        $w->put($source . '/records/posts/legacy.txt', str_replace("Created-At: 2026-04-10T12:00:00Z\n", '', $w->post('legacy')));
        $plan = (new ContentImportPlanner())->plan($source, $target);
        assertSame('duplicate', $plan['records/posts/root.txt']['state']);
        assertSame('invalid', $plan['records/posts/orphan.txt']['state']);
        assertSame('invalid', $plan['records/posts/legacy.txt']['state']);
    }
}
