<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';
use ForumRewrite\Canonical\{RecordLimits, RecordSizeException};
use ForumRewrite\Import\{ContentImportPlanner, RepositoryArchive};
final class ImportAdmissionTest
{
    public function testMalformedHeaderDiagnosticsAreBounded(): void
    {
        $w = new ImportTestWorkspace(); $source = $w->repository('source'); $target = $w->repository('target');
        $w->put($source . '/records/posts/invalid.txt', str_repeat('x', 2 * 1024 * 1024) . "\n\nbody\n");
        $entry = (new ContentImportPlanner())->plan($source, $target)['records/posts/invalid.txt'];
        assertSame('invalid', $entry['state']);
        assertTrue(strlen($entry['reason']) < 1100);
        assertStringContains('[truncated]', $entry['reason']);
    }

    public function testBoundariesAndIndirectReads(): void
    {
        $w = new ImportTestWorkspace();
        foreach (['records/posts/root.txt' => RecordLimits::TEXT, 'records/posts/root.txt.asc' => RecordLimits::ARMOR,
            'records/posts/root.txt.sig' => RecordLimits::ARMOR, 'records/post-timestamps/root.json' => 4096] as $path => $limit) {
            $full = $w->root . '/' . $path; $w->put($full, str_repeat('a', $limit));
            assertSame($limit, strlen(RecordLimits::read($full)));
            file_put_contents($full, 'b', FILE_APPEND);
            try { RecordLimits::read($full); throw new LogicException('Expected limit'); }
            catch (RecordSizeException $e) { assertStringContains($path, $e->getMessage()); }
        }
        $source = $w->repository('source', true); $target = $w->repository('target');
        $label = $w->signedLabel($source);
        $key = '/records/public-keys/openpgp-066236E516A3B74EC51B3B423A26034600DE67FD.asc';
        $w->put($source . $key, str_repeat('x', RecordLimits::ARMOR + 1));
        $plan = (new ContentImportPlanner())->plan($source, $target);
        assertSame('invalid', $plan[$label]['state']);
        assertSame('import', $plan['records/posts/root-001.txt']['state']);
        $w->put($target . $key, file_get_contents($source . $key)); unlink($source . $key);
        try { (new ContentImportPlanner())->plan($source, $target); throw new LogicException('Expected local failure'); }
        catch (RecordSizeException $e) { assertStringContains($target . $key, $e->getMessage()); }
    }
    public function testOversizedCompanionsRejectPairs(): void
    {
        foreach (['.asc', '.sig'] as $suffix) {
            $w = new ImportTestWorkspace(); $source = $w->repository('source'); $target = $w->repository('target');
            $w->put($source . '/records/posts/root.txt', $w->post('root'));
            $w->put($source . '/records/posts/root.txt' . $suffix, str_repeat('x', RecordLimits::ARMOR + 1));
            $w->put($source . '/records/posts/independent.txt', $w->post('independent'));
            $plan = (new ContentImportPlanner())->plan($source, $target);
            assertSame('invalid', $plan['records/posts/root.txt']['state']);
            assertSame('invalid', $plan['records/posts/root.txt' . $suffix]['state']);
            assertSame('import', $plan['records/posts/independent.txt']['state']);
        }
    }
    public function testBadFilenameFailsBeforeExtractionIncludingExcludedCategories(): void
    {
        foreach (["records/unknown/bad-\xff.txt", "outside-\xff/data"] as $path) {
            $w = new ImportTestWorkspace(); $source = $w->repository('source');
            $w->put($source . '/records/posts/root.txt', $w->post('root'));
            $w->put($source . '/' . $path, 'ignored');
            $archive = $w->root . '/source.tar.gz';
            assertSame(0, $w->command(['tar', '-czf', $archive, '-C', $source, '.'])[0]);
            try { (new RepositoryArchive())->extract($archive, $w->root . '/out'); throw new LogicException('Expected path rejection'); }
            catch (RuntimeException $e) { assertStringContains('Unsafe archive path', $e->getMessage()); }
            assertTrue(!file_exists($w->root . '/out'));
        }
    }
}
