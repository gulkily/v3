<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\{CanonicalRecordParseException, LabelRemoval, PostRecordParser, PostRetractionRecordParser, RemovalReceipt, RemovalTarget, RetractionReceipt};

/** Retractions name their target with Target-ID; files written with Post-ID before the rename still read. */
final class RetractionTargetIdTest
{
    private const AUTHOR = 'openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private function request(string $targetHeader = 'Target-ID', string $extra = ''): string
    {
        return "Record-ID: post-retraction-root\nCreated-At: 2026-10-25T13:00:00Z\n$targetHeader: root\n{$extra}Operation: retract\nAuthor-Identity-ID: " . self::AUTHOR . "\n\n";
    }

    /** @return array<string, string> receipt headers in the current or legacy form */
    private function receipt(bool $legacy, string $authority = 'local-operator'): array
    {
        $signed = $authority === 'signed-retraction';
        return ['Format' => 'removal-acceptance-v1', ($legacy ? 'Post-ID' : 'Target-ID') => 'root', 'Operation' => 'delete',
            'Authority' => $authority, 'Accepted-At' => '2026-10-25T13:00:00Z', 'Actor' => $signed ? self::AUTHOR : 'local-operator',
            'Source-SHA256' => str_repeat('a', 64), 'Thread-ID' => 'root', 'Parent-ID' => '',
            ($legacy ? 'Post-Created-At' : 'Target-Created-At') => '2026-10-25T12:00:00Z', 'Author-Identity-ID' => self::AUTHOR,
            'Thread-Type' => '', 'Retraction-Class' => $signed ? 'author' : '', 'Retraction-Attribution' => $signed ? 'author' : '',
            'Request-SHA256' => $signed ? hash('sha256', $this->request()) : '', 'Signature-SHA256' => $signed ? hash('sha256', "signature\n") : ''];
    }

    private function rejects(callable $callback, ?string $message = null): void
    {
        try { $callback(); } catch (RuntimeException $error) {
            if ($message !== null) assertSame($message, $error->getMessage());
            return;
        }
        throw new LogicException('Expected a rejection.');
    }

    public function testRequestReadsTargetIdAndLegacyPostId(): void
    {
        $parser = new PostRetractionRecordParser();
        foreach (['Target-ID', 'Post-ID'] as $header) {
            $record = $parser->parse($this->request($header));
            assertSame('root', $record->postId);
            assertSame('post-retraction-root', $record->recordId);
        }
    }

    public function testRequestWithBothOrNeitherTargetHeaderIsRejected(): void
    {
        $parser = new PostRetractionRecordParser();
        $this->rejects(fn () => $parser->parse($this->request('Target-ID', "Post-ID: root\n")),
            'Retraction target must use Target-ID or the older Post-ID, not both.');
        $this->rejects(fn () => $parser->parse(str_replace("Target-ID: root\n", '', $this->request())),
            'Missing required retraction target header: Target-ID');
        $this->rejects(fn () => $parser->parse(str_replace('Target-ID: root', 'Target-ID: other', $this->request())),
            'Post-retraction Record-ID must be derived from Target-ID.');
    }

    public function testReceiptRoundTripsInBothFormsAndKeepsStoredBytes(): void
    {
        foreach ([false, true] as $legacy) {
            $headers = $this->receipt($legacy);
            $bytes = RemovalReceipt::encode($headers);
            assertSame(true, str_starts_with($bytes, "Format: removal-acceptance-v1\n" . ($legacy ? 'Post-ID' : 'Target-ID') . ": root\n"));
            assertSame(['headers' => $headers, 'labels' => []], RemovalReceipt::decode($bytes));
            assertSame(['id' => 'root', 'created_at' => '2026-10-25T12:00:00Z', 'legacy' => $legacy], RemovalTarget::fromHeaders($headers));
        }
    }

    public function testReceiptMixingOrMissingTargetFieldsIsRejected(): void
    {
        $current = RemovalReceipt::encode($this->receipt(false));
        $this->rejects(fn () => RemovalReceipt::decode(str_replace('Target-Created-At:', 'Post-Created-At:', $current)), 'Unsupported removal receipt format.');
        $this->rejects(fn () => RemovalReceipt::decode(str_replace("Target-ID: root\n", '', $current)), 'Unsupported removal receipt format.');
        $this->rejects(fn () => RemovalReceipt::decode(str_replace("Target-ID: root\n", "Target-ID: root\nPost-ID: root\n", $current)), 'Unsupported removal receipt format.');
        $mixed = $this->receipt(false); unset($mixed['Target-Created-At']); $mixed['Post-Created-At'] = '2026-10-25T12:00:00Z';
        $this->rejects(fn () => RemovalReceipt::encode($mixed), 'Invalid removal receipt fields.');
    }

    public function testLocalAndSignedReceiptReadersAcceptBothForms(): void
    {
        foreach ([false, true] as $legacy) {
            $local = LabelRemoval::decode(RemovalReceipt::encode($this->receipt($legacy)), 'root');
            assertSame('2026-10-25T12:00:00Z', $local['post']['created_at']);
            assertSame('root', $local['source_id']);

            $request = $this->request($legacy ? 'Post-ID' : 'Target-ID');
            $headers = $this->receipt($legacy, 'signed-retraction');
            $headers['Request-SHA256'] = hash('sha256', $request);
            $signed = RetractionReceipt::decode(RemovalReceipt::encode($headers), 'root', $request, "signature\n");
            assertSame('2026-10-25T12:00:00Z', $signed['post']['created_at']);
            assertSame('root', $signed['post_id']);
            $this->rejects(fn () => LabelRemoval::decode(RemovalReceipt::encode($this->receipt($legacy)), 'other'), 'Mismatched local removal receipt.');
        }
    }

    public function testLocalOperatorReceiptsAreWrittenWithTargetId(): void
    {
        $record = LabelRemoval::decode(RemovalReceipt::encode($this->receipt(true)), 'root');
        $bytes = LabelRemoval::encode($record);
        assertSame(true, str_contains($bytes, "\nTarget-ID: root\n"));
        assertSame(true, str_contains($bytes, "\nTarget-Created-At: 2026-10-25T12:00:00Z\n"));
        assertSame(false, str_contains($bytes, 'Post-ID'));
        assertSame($record, LabelRemoval::decode($bytes, 'root'));
    }

    public function testPostsCannotUseReactionPrefixes(): void
    {
        $post = fn (string $id): string => "Post-ID: $id\nCreated-At: 2026-10-25T12:00:00Z\nAuthor-Identity-ID: " . self::AUTHOR . "\nBoard-Tags: general\n\nprose\n";
        foreach (['post-reaction-abc', 'thread-label-abc'] as $id) {
            $this->rejects(fn () => (new PostRecordParser())->parse($post($id)), 'Post-ID must not start with a reaction record prefix.');
        }
        foreach (['root', 'thread-20261110171842-0dece695', 'thread-labels', 'reply-post-reaction'] as $id) {
            assertSame($id, (new PostRecordParser())->parse($post($id))->postId);
        }
        assertSame(true, RemovalTarget::isReactionId('post-reaction-1'));
        assertSame(false, RemovalTarget::isReactionId('post-reactions'));
    }
}
