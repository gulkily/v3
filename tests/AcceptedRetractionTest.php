<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\AcceptedRetraction;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\Canonical\PostRetractionRecordParser;

final class AcceptedRetractionTest
{
    public function testRecordPreservesSignedBytesAndStructureWithoutProse(): void
    {
        $author = 'openpgp:' . str_repeat('a', 40);
        $post = (new PostRecordParser())->parse("Post-ID: root\nCreated-At: 2026-10-25T12:00:00Z\nAuthor-Identity-ID: $author\nBoard-Tags: general\nSubject: secret title\n\nsecret prose\n");
        $bytes = "Record-ID: post-retraction-root\nCreated-At: 2026-10-25T13:00:00Z\nPost-ID: root\nOperation: retract\nAuthor-Identity-ID: $author\n\n";
        $request = (new PostRetractionRecordParser())->parse($bytes);
        $record = AcceptedRetraction::prepare($post, $request, $bytes, 'original detached signature', 'author');
        $encoded = AcceptedRetraction::encode($record);
        assertSame(false, str_contains($encoded, 'secret'));
        $read = AcceptedRetraction::decode($encoded, 'root', $record['signed_request'], $record['signature']);
        assertSame($bytes, $read['signed_request']);
        assertSame('root', $read['post']['thread_id']);
        assertSame('original detached signature', $read['signature']);
        $record['post']['body'] = 'secret';
        try { AcceptedRetraction::encode($record); throw new LogicException('Accepted prose'); }
        catch (RuntimeException $e) { assertSame('Unexpected retraction metadata.', $e->getMessage()); }
    }

    public function testMismatchedTargetDoesNotGrantAcceptance(): void
    {
        $author = 'openpgp:' . str_repeat('a', 40);
        $post = (new PostRecordParser())->parse("Post-ID: root\nCreated-At: 2026-10-25T12:00:00Z\nAuthor-Identity-ID: $author\nBoard-Tags: general\n\ncontent\n");
        $bytes = "Record-ID: post-retraction-other\nCreated-At: 2026-10-25T13:00:00Z\nPost-ID: other\nOperation: retract\nAuthor-Identity-ID: $author\n\n";
        try { AcceptedRetraction::prepare($post, (new PostRetractionRecordParser())->parse($bytes), $bytes, '', 'author'); throw new LogicException('Accepted forged target'); }
        catch (RuntimeException $e) { assertSame('Retraction does not authorize this target.', $e->getMessage()); }
    }
    public function testDeletionCommitsTogetherAndFailedCommitRestoresContent(): void
    {
        $root = sys_get_temp_dir() . '/retraction-transaction-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0775, true);
        $git = static fn (array $args): string => \ForumRewrite\Canonical\LabelRemoval::git($root, $args);
        $git(['init']); $git(['config', 'user.name', 'Test']); $git(['config', 'user.email', 'test@example.invalid']);
        $author = 'openpgp:' . str_repeat('a', 40);
        $source = "Post-ID: root\nCreated-At: 2026-10-25T12:00:00Z\nAuthor-Identity-ID: $author\nBoard-Tags: general\n\nsecret prose\n";
        file_put_contents($root . '/records/posts/root.txt', $source);
        file_put_contents($root . '/records/posts/root.txt.asc', 'post signature');
        file_put_contents($root . '/records/posts/reply.txt', 'independent reply');
        $git(['add', '.']); $git(['commit', '-m', 'initial']);
        $request = "Record-ID: post-retraction-root\nCreated-At: 2026-10-25T13:00:00Z\nPost-ID: root\nOperation: retract\nAuthor-Identity-ID: $author\n\n";
        $record = AcceptedRetraction::prepare((new PostRecordParser())->parse($source), (new PostRetractionRecordParser())->parse($request), $request, 'signed', 'author');
        file_put_contents($root . '/.git/hooks/pre-commit', "#!/bin/sh\nexit 1\n"); chmod($root . '/.git/hooks/pre-commit', 0755);
        try { \ForumRewrite\Write\RetractionDeletion::commit($root, $record); throw new LogicException('Accepted failed commit'); }
        catch (RuntimeException) {}
        assertSame($source, file_get_contents($root . '/records/posts/root.txt'));
        assertSame(null, AcceptedRetraction::read($root, 'root'));
        unlink($root . '/.git/hooks/pre-commit');
        $commit = \ForumRewrite\Write\RetractionDeletion::commit($root, $record);
        assertSame(false, is_file($root . '/records/posts/root.txt'));
        assertSame(false, is_file($root . '/records/posts/root.txt.asc'));
        assertSame('independent reply', file_get_contents($root . '/records/posts/reply.txt'));
        assertSame($record, AcceptedRetraction::read($root, 'root'));
        assertSame($commit, \ForumRewrite\Write\RetractionDeletion::commit($root, $record));
        $archive = $root . '-archive'; mkdir($archive . '/records/posts', 0775, true);
        file_put_contents($archive . '/records/posts/root.txt', $source);
        $plan = (new \ForumRewrite\Import\ContentImportPlanner())->plan($archive, $root);
        assertSame('duplicate', $plan['records/posts/root.txt']['state']);
        foreach (AcceptedRetraction::files($record) as $path => $bytes) {
            if (!is_dir(dirname($archive . '/' . $path))) mkdir(dirname($archive . '/' . $path), 0775, true);
            file_put_contents($archive . '/' . $path, $bytes);
        }
        $empty = $root . '-empty'; mkdir($empty . '/records', 0775, true);
        $plan = (new \ForumRewrite\Import\ContentImportPlanner())->plan($archive, $empty);
        assertSame('invalid', $plan[AcceptedRetraction::path('root')]['state']);
        foreach (AcceptedRetraction::files($record) as $path => $_) assertSame('invalid', $plan[$path]['state']);
        $duplicate = (new \ForumRewrite\Import\ContentImportPlanner())->plan($archive, $root);
        foreach (AcceptedRetraction::files($record) as $path => $_) assertSame('duplicate', $duplicate[$path]['state']);
        file_put_contents($archive . '/' . AcceptedRetraction::requestPath('root') . '.asc', 'substituted signature');
        $tampered = (new \ForumRewrite\Import\ContentImportPlanner())->plan($archive, $root);
        foreach (AcceptedRetraction::files($record) as $path => $_) assertSame('invalid', $tampered[$path]['state']);
        unlink($archive . '/' . AcceptedRetraction::path('root'));
        $orphan = (new \ForumRewrite\Import\ContentImportPlanner())->plan($archive, $root);
        assertSame('invalid', $orphan[AcceptedRetraction::requestPath('root')]['state']);


        assertSame('5', trim($git(['diff-tree', '--no-commit-id', '--name-only', '-r', $commit])) === '' ? '0' : (string) count(explode("\n", trim($git(['diff-tree', '--no-commit-id', '--name-only', '-r', $commit])))));
    }

    public function testMaintenanceDoesNotMigratePreviousRetractionsOrNewRequests(): void
    {
        $root = sys_get_temp_dir() . '/no-retraction-migration-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/post-retractions', 0775, true);
        $path = $root . '/records/post-retractions/post-retraction-old.txt';
        file_put_contents($path, 'previous request');
        $json = $root . '/records/post-retractions/post-retraction-old.json';
        file_put_contents($json, '{"previous":"acceptance"}');
        assertSame(null, AcceptedRetraction::read($root, 'old'));
        $result = \ForumRewrite\Retraction\RetractionMigration::run($root, $root . '/write.lock');
        assertSame([], $result['migrated']); assertSame([], $result['unresolved']);
        assertSame('previous request', file_get_contents($path));
        assertSame('{"previous":"acceptance"}', file_get_contents($json));
    }
}
