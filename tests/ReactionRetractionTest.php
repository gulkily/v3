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

    public function testPostRecordListFindsExactTargetsOnly(): void
    {
        $root = sys_get_temp_dir() . '/post-record-list-' . bin2hex(random_bytes(4));
        foreach (['records/posts', 'records/post-reactions', 'records/thread-labels', 'records/post-formats'] as $dir) mkdir($root . '/' . $dir, 0775, true);
        $author = self::AUTHOR;
        file_put_contents($root . '/records/posts/root.1.txt', "Post-ID: root.1\nCreated-At: 2026-11-14T07:00:00Z\nBoard-Tags: general\nAuthor-Identity-ID: {$author}\n\nRoot\n");
        $like = \ForumRewrite\Canonical\ReactionRecordParser::build('thread', 'thread-label-like', '2026-11-14T08:00:00Z', 'root.1', ['like'], $author);
        file_put_contents($root . '/records/thread-labels/thread-label-like.txt', $like);
        // Look-alikes that must not match: a regex dot, and a longer ID.
        file_put_contents($root . '/records/thread-labels/thread-label-dot.txt', str_replace('root.1', 'rootX1', str_replace('thread-label-like', 'thread-label-dot', $like)));
        file_put_contents($root . '/records/thread-labels/thread-label-long.txt', str_replace('root.1', 'root.10', str_replace('thread-label-like', 'thread-label-long', $like)));
        // A remove record is listed, but not as a retractable like.
        file_put_contents($root . '/records/thread-labels/thread-label-unlike.txt', str_replace(['thread-label-like', 'Operation: add'], ['thread-label-unlike', 'Operation: remove'], $like));
        file_put_contents($root . '/records/post-formats/post-format-root.1-1.txt', "Record-ID: post-format-root.1-1\nCreated-At: 2026-11-14T09:00:00Z\nPost-ID: root.1\nBody-Format: markdown\nActor-Identity-ID: {$author}\n\n");
        \ForumRewrite\Canonical\LabelRemoval::git($root, ['init', '-q']);
        \ForumRewrite\Canonical\LabelRemoval::git($root, ['add', '.']);
        \ForumRewrite\Canonical\LabelRemoval::git($root, ['-c', 'user.name=t', '-c', 'user.email=t@example.invalid', 'commit', '-qm', 'fixture']);

        $rows = \ForumRewrite\Canonical\PostRecordList::forPost($root, 'root.1', true);
        assertSame(['root.1', 'thread-label-like', 'thread-label-unlike', 'post-format-root.1-1'], array_column($rows, 'record_id'));
        assertSame(['Post', 'Like', 'Label', 'Format'], array_column($rows, 'kind'));
        assertSame('remove like', $rows[2]['summary']);
        assertSame('Format: markdown', $rows[3]['summary']);
        assertSame($author, $rows[3]['author_identity_id']);
        // A reply's list leaves out thread-level labels.
        assertSame(['root.1', 'post-format-root.1-1'], array_column(\ForumRewrite\Canonical\PostRecordList::forPost($root, 'root.1', false), 'record_id'));
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
