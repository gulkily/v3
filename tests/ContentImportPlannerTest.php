<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Import\ContentImportPlanner;

final class ContentImportPlannerTest
{
    public function testOldArchiveCannotRestoreRemovedSourceOrItsOwnedLabels(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source', true); $target = $w->repository('target', true);
        $w->git($target);
        $record = \ForumRewrite\Canonical\LabelRemoval::prepare($target, 'root-001', 'delete', ['thread-label-20260415153000-ab12cd34']);
        $path = \ForumRewrite\Canonical\LabelRemoval::store($target, $record);
        $git = static fn (array $args): string => \ForumRewrite\Canonical\LabelRemoval::git($target, $args);
        $git(['add', $path]); $git(['rm', 'records/posts/root-001.txt', 'records/thread-labels/thread-label-20260415153000-ab12cd34.txt']);
        $git(['commit', '-qm', 'Accept source label cleanup root-001']);
        $plan = (new ContentImportPlanner())->plan($source, $target);
        assertSame('duplicate', $plan['records/posts/root-001.txt']['state']);
        assertSame('excluded', $plan['records/thread-labels/thread-label-20260415153000-ab12cd34.txt']['state']);
        assertSame('duplicate', $plan['records/posts/reply-001.txt']['state']);
        $database = $w->root . '/read.sqlite3';
        $run = (new \ForumRewrite\Import\ContentImportRunner($target, $database))->run($source, static function () use ($target, $database): void {
            (new \ForumRewrite\ReadModel\ReadModelBuilder($target, $database, new \ForumRewrite\Canonical\CanonicalRecordRepository($target)))->rebuild();
        });
        assertSame('complete', $run['status']);
        assertSame(false, is_file($target . '/records/posts/root-001.txt'));
        assertSame(false, is_file($target . '/records/thread-labels/thread-label-20260415153000-ab12cd34.txt'));
        assertSame("Reply body.\nSecond line for context.", trim((new PDO('sqlite:' . $database))->query("SELECT body FROM posts WHERE post_id='reply-001'")->fetchColumn()));

        // An archive receipt without the destination's trusted history is not authority.
        $w->put($source . '/' . $path, file_get_contents($target . '/' . $path));
        $empty = $w->repository('empty');
        $fresh = (new ContentImportPlanner())->plan($source, $empty);
        assertSame('invalid', $fresh[$path]['state']);
    }

    public function testPublicCoverageKeepsSourceBytesAndExcludesAuthority(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source', true);
        $target = $w->repository('target');
        $w->put($source . '/records/posts/authority.txt', str_replace('Board-Tags: general', 'Board-Tags: identity approval', $w->post('authority')));
        $w->put($source . '/records/posts/invite.txt', str_replace('Board-Tags: general', 'Board-Tags: invitation', $w->post('invite')));
        $w->put($source . '/records/posts/topic.txt', str_replace('Board-Tags: general', 'Board-Tags: general approval private', $w->post('topic')));
        $plan = (new ContentImportPlanner())->plan($source, $target);
        assertSame('import', $plan['records/posts/topic.txt']['state']);
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

    public function testUnsignedAndForgedPostRetractionsAreRejected(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source', true);
        $target = $w->repository('target');
        $record = 'records/post-retractions/post-retraction-reply-001.txt';
        $w->put($source . '/' . $record, "Record-ID: post-retraction-reply-001\nCreated-At: 2026-10-13T12:00:00Z\nPost-ID: reply-001\nOperation: retract\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\n");
        $w->put($source . '/' . $record . '.asc', 'detached signature');
        $w->put($source . '/records/post-retractions/post-retraction-absent.txt', "Record-ID: post-retraction-absent\nCreated-At: 2026-10-13T12:00:00Z\nPost-ID: absent\nOperation: retract\nAuthor-Identity-ID: openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954\n\n");
        $plan = (new ContentImportPlanner())->plan($source, $target);
        assertSame('invalid', $plan[$record]['state']);
        assertStringContains('local acceptance evidence', $plan[$record]['reason']);
        assertSame('invalid', $plan[$record . '.asc']['state']);
        assertSame('invalid', $plan['records/post-retractions/post-retraction-absent.txt']['state']);
        assertStringContains('local acceptance evidence', $plan['records/post-retractions/post-retraction-absent.txt']['reason']);
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

    public function testSignatureCannotBeOrphanedByCanonicalDuplicateAtAnotherPath(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target');
        $w->put($source . '/records/posts/2026/04/10/root.txt', $w->post('root'));
        $w->put($source . '/records/posts/2026/04/10/root.txt.sig', 'detached signature');
        $w->put($target . '/records/posts/root.txt', $w->post('root'));
        $plan = (new ContentImportPlanner())->plan($source, $target);
        assertSame('invalid', $plan['records/posts/2026/04/10/root.txt.sig']['state']);
        assertStringContains('manual association', $plan['records/posts/2026/04/10/root.txt.sig']['reason']);
    }

    public function testOversizedRecordIsReportedWithoutParsing(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target');
        $w->put($source . '/records/posts/oversized.txt', '');
        $file = fopen($source . '/records/posts/oversized.txt', 'wb');
        ftruncate($file, 16 * 1024 * 1024 + 1);
        fclose($file);
        $plan = (new ContentImportPlanner())->plan($source, $target);
        assertSame('invalid', $plan['records/posts/oversized.txt']['state']);
        assertStringContains('16 MiB', $plan['records/posts/oversized.txt']['reason']);
    }

    public function testRejectedDependenciesRetainTheirRootCauseAcrossRepliesAndSignatures(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target');
        $w->put($source . '/records/posts/legacy.txt', str_replace("Created-At: 2026-04-10T12:00:00Z\n", '', $w->post('legacy')));
        $w->put($source . '/records/posts/child.txt', $w->post('child', "Thread-ID: legacy\nParent-ID: legacy\n"));
        $w->put($source . '/records/posts/grandchild.txt', $w->post('grandchild', "Thread-ID: child\nParent-ID: child\n"));
        $w->put($source . '/records/posts/grandchild.txt.sig', 'Detached signature');
        $w->put($source . '/records/posts/orphan.txt', $w->post('orphan', "Thread-ID: absent\nParent-ID: absent\n"));
        $plan = (new ContentImportPlanner())->plan($source, $target);
        foreach (['child.txt', 'grandchild.txt', 'grandchild.txt.sig'] as $name) {
            assertSame('invalid', $plan['records/posts/' . $name]['state']);
            assertSame('records/posts/legacy.txt', $plan['records/posts/' . $name]['root_cause']);
        }
        assertStringContains('Rejected dependency:', $plan['records/posts/child.txt']['reason']);
        assertStringContains('Missing dependency:', $plan['records/posts/orphan.txt']['reason']);
        $w->git($target);
        $report = (new \ForumRewrite\Import\ContentImportRunner($target, $w->root . '/cache/index.sqlite3'))->run($source, static fn () => null, true);
        assertSame(2, count($report['review_causes']));
        assertSame('records/posts/legacy.txt', $report['review_causes'][0]['path']);
        assertSame(4, $report['review_causes'][0]['affected_count']);
    }

    public function testExcludedAuthorityExplainsBlockedRepliesAndExcludesCompanionMetadata(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target');
        $bytes = str_replace(["Created-At: 2026-04-10T12:00:00Z\n", 'Board-Tags: general'], ['', 'Board-Tags: identity approval'], $w->post('authority'));
        $w->put($source . '/records/posts/authority.txt', $bytes);
        $w->put($source . '/records/post-timestamps/authority.json', \ForumRewrite\Canonical\LegacyPostTimestamp::encode('authority', $bytes, '2026-04-10T12:00:00Z'));
        $w->put($source . '/records/posts/reply.txt', $w->post('reply', "Thread-ID: authority\nParent-ID: authority\n"));
        $w->put($source . '/records/posts/reply.txt.sig', 'Detached signature');
        $w->put($source . '/records/posts/nested.txt', $w->post('nested', "Thread-ID: reply\nParent-ID: reply\n"));
        $w->git($target);
        $report = (new \ForumRewrite\Import\ContentImportRunner($target, $w->root . '/cache/index.sqlite3'))->run($source, static fn () => null, true);
        assertSame(2, $report['counts']['excluded']);
        assertSame(3, $report['counts']['invalid']);
        assertSame(1, count($report['review_causes']));
        assertSame('excluded', $report['review_causes'][0]['state']);
        assertSame('records/posts/authority.txt', $report['review_causes'][0]['path']);
        assertSame(3, $report['review_causes'][0]['affected_count']);
        assertStringContains('Excluded dependency:', $report['entries']['records/posts/reply.txt']['reason']);
        assertSame('excluded', $report['entries']['records/post-timestamps/authority.json']['state']);
    }

    public function testOrphanSignaturesProduceDiagnosticsWithoutFilesystemWarnings(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target');
        foreach ([$source, $target] as $root) {
            $w->put($root . '/records/posts/absent.txt.sig', 'Detached signature');
        }
        set_error_handler(static function (int $severity, string $message): never { throw new ErrorException($message, 0, $severity); });
        try {
            $plan = (new ContentImportPlanner())->plan($source, $target);
        } finally {
            restore_error_handler();
        }
        assertSame('invalid', $plan['records/posts/absent.txt.sig']['state']);
        assertStringContains('Signature record is absent:', $plan['records/posts/absent.txt.sig']['reason']);
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
