<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\{AcceptedRemoval, AcceptedRetraction, PostRetractionRecord, PostRetractionRecordParser, RemovalTarget};

/** A signed retraction can name a like (a reaction record) in Target-ID. */
final class ReactionRetractionTest
{
    private const AUTHOR = 'openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const REACTION = 'post-reaction-0123456789abcdef';

    private function request(string $id = self::REACTION, string $header = 'Target-ID'): string
    {
        return "Record-ID: post-retraction-$id\nCreated-At: 2026-11-14T09:00:00Z\n$header: $id\nOperation: retract\nAuthor-Identity-ID: " . self::AUTHOR . "\n\n";
    }

    private function rejects(callable $callback, string $message): void
    {
        try { $callback(); } catch (RuntimeException $error) { assertSame($message, $error->getMessage()); return; }
        throw new LogicException('Expected a rejection.');
    }

    private function accepted(string $root, string $id = self::REACTION): array
    {
        $parsed = (new PostRetractionRecordParser())->parse($this->request($id));
        $request = new PostRetractionRecord($parsed->recordId, $parsed->createdAt, $parsed->postId, $parsed->operation,
            $parsed->authorIdentityId, $parsed->actorIdentityId, $parsed->classification, true);
        $record = AcceptedRetraction::prepareReaction(['record_id' => $id, 'thread_id' => 'root', 'target_post_id' => 'reply-1',
            'created_at' => '2026-11-14T08:00:00Z', 'author_identity_id' => self::AUTHOR], $request, $this->request($id), "signature\n", 'author', str_repeat('b', 64));
        foreach (AcceptedRetraction::files($record) as $path => $bytes) {
            if (!is_dir(dirname($root . '/' . $path))) mkdir(dirname($root . '/' . $path), 0775, true);
            file_put_contents($root . '/' . $path, $bytes);
        }
        return $record;
    }

    public function testFamiliesAndSourcePaths(): void
    {
        assertSame('post-reaction', RemovalTarget::family(self::REACTION));
        assertSame('thread-label', RemovalTarget::family('thread-label-abc'));
        assertSame('post', RemovalTarget::family('thread-20261110171842-0dece695'));
        assertSame(['records/post-reactions/' . self::REACTION . '.txt'], RemovalTarget::sourceCandidates(self::REACTION));
        assertSame(['records/thread-labels/thread-label-abc.txt'], RemovalTarget::sourceCandidates('thread-label-abc'));
    }

    public function testRequestNamesAReactionOnlyWithTargetId(): void
    {
        assertSame(self::REACTION, (new PostRetractionRecordParser())->parse($this->request())->postId);
        $this->rejects(fn () => (new PostRetractionRecordParser())->parse($this->request(self::REACTION, 'Post-ID')),
            'A reaction can be retracted only with Target-ID.');
    }

    public function testReactionReceiptRoundTripsAndStaysOutOfPostRemovals(): void
    {
        $root = sys_get_temp_dir() . '/reaction-retraction-' . bin2hex(random_bytes(4));
        mkdir($root);
        $record = $this->accepted($root);
        $receipt = file_get_contents($root . '/' . AcceptedRetraction::path(self::REACTION));
        assertSame(true, str_contains($receipt, "\nTarget-ID: " . self::REACTION . "\n"));
        assertSame(true, str_contains($receipt, "\nParent-ID: reply-1\n"));
        assertSame(true, str_contains($receipt, "\nTarget-Created-At: 2026-11-14T08:00:00Z\n"));
        assertSame($record, AcceptedRetraction::read($root, self::REACTION));
        assertSame([], AcceptedRemoval::all($root));
        assertSame([self::REACTION], array_keys(AcceptedRemoval::reactions($root)));
        assertSame([], \ForumRewrite\Canonical\RetractedPosts::effectivePostIds($root));
    }

    public function testPrepareRejectsPostsAndMismatchedAuthors(): void
    {
        $parsed = (new PostRetractionRecordParser())->parse($this->request());
        $reaction = ['record_id' => self::REACTION, 'thread_id' => 'root', 'target_post_id' => 'root',
            'created_at' => '2026-11-14T08:00:00Z', 'author_identity_id' => 'openpgp:' . str_repeat('c', 40)];
        $this->rejects(fn () => AcceptedRetraction::prepareReaction($reaction, $parsed, '', '', 'author', ''),
            'Retraction does not authorize this target.');
        $post = (new PostRetractionRecordParser())->parse(str_replace(self::REACTION, 'root', $this->request()));
        $this->rejects(fn () => AcceptedRetraction::prepareReaction(['record_id' => 'root'] + $reaction, $post, '', '', 'author', ''),
            'Retraction does not authorize this target.');
    }
}
