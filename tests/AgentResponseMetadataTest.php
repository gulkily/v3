<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
use ForumRewrite\Canonical\AgentResponseMetadata as Meta;
use ForumRewrite\Canonical\PostRecordParser;

final class AgentResponseMetadataTest
{
    public function testOldAndNewMetadataKeepHashesRelationshipsAndHistoricalBytes(): void
    {
        foreach ([Meta::SCRIPTURE, Meta::EXPANSION] as $type) {
            $data = ['type' => $type, 'source_post_id' => 'source', 'source_hash' => str_repeat('a', 64)];
            $parent = 'source';
            if ($type === Meta::EXPANSION) { $data += ['expansion_of' => 'initial', 'initial_hash' => str_repeat('b', 64)]; $parent = 'initial'; }
            $old = $this->post('Agent-Response: ' . json_encode($data) . "\n", $parent);
            $before = hash('sha256', $old);
            $new = $this->post(Meta::toHeaders($data), $parent);
            $parser = new PostRecordParser();
            assertSame($data, $parser->parse($old)->agentResponse);
            assertSame($data, $parser->parse($new)->agentResponse);
            assertSame($data, Meta::decode(json_encode($data), $parent));
            assertSame($before, hash('sha256', $old));
            $oldPost = $parser->parse($old); $newPost = $parser->parse($new);
            $hash = fn ($post) => Meta::postHash(['post_id' => $post->postId, 'subject' => $post->subject, 'body' => $post->body]);
            assertSame($hash($oldPost), $hash($newPost));
        }
        assertSame(null, (new PostRecordParser())->parse($this->post('', 'source'))->agentResponse);
        assertSame(null, (new PostRecordParser())->parse($this->post("Agent-Response: \n", 'source'))->agentResponse);
    }

    public function testAmbiguousIncompleteUnknownAndDuplicateHeadersFail(): void
    {
        $data = ['type' => Meta::SCRIPTURE, 'source_post_id' => 'source', 'source_hash' => str_repeat('a', 64)];
        $text = Meta::toHeaders($data);
        foreach ([$text . 'Agent-Response: ' . json_encode($data) . "\n", $text . "Agent-Response-Type: scripture_oriented_analysis\n",
            $text . "Agent-Response-Extra: x\n", "Agent-Response-Type: scripture_oriented_analysis\n",
            str_replace('source', 'wrong-parent', $text), str_replace(str_repeat('a', 64), 'bad', $text)] as $bad) {
            try { (new PostRecordParser())->parse($this->post($bad, 'source')); }
            catch (\ForumRewrite\Canonical\CanonicalRecordParseException) { continue; }
            throw new RuntimeException('Expected invalid metadata rejection.');
        }
        $legacy = $data + ['historical_extra' => 'preserve'];
        assertSame($legacy, Meta::fromHeaders(['Agent-Response' => json_encode($legacy)], 'source'));
    }

    private function post(string $headers, string $parent): string
    {
        return "Post-ID: reply\nCreated-At: 2026-01-01T00:00:00Z\nBoard-Tags: general\nThread-ID: root\nParent-ID: {$parent}\n{$headers}\nUnchanged response.\n";
    }
}
