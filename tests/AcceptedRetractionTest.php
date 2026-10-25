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
        $read = AcceptedRetraction::decode($encoded, 'root');
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
}
