<?php

declare(strict_types=1);

use ForumRewrite\Agent\ScriptureVerifiedReply;

final class ScriptureVerifiedReplyTest
{
    private function document(array $quotation = []): array
    {
        return ['segments' => [
            ['type' => 'reflection', 'text' => 'Diligence supports planning; this is a practical analogy, not a guarantee.'],
            array_merge(['type' => 'quotation', 'book' => 'Proverbs', 'chapter' => 21, 'start' => 5, 'end' => 5, 'translation' => 'KJV'], $quotation),
        ]];
    }

    public function testCanonicalSourceOwnsQuotationAndOrderedOmissionsAreExplicit(): void
    {
        $renderer = new ScriptureVerifiedReply();
        assertStringContains('"The thoughts of the diligent tend only to plenteousness; but of every one that is hasty only to want." (Proverbs 21:5, KJV)', $renderer->render($this->document()));
        assertStringContains('"... but of every one that is hasty only to want."', $renderer->render($this->document(['excerpts' => ['but of every one that is hasty only to want.']])));
        assertStringContains('"The thoughts ... only to want."', $renderer->render($this->document(['excerpts' => ['The thoughts', 'only to want.']])));
    }

    public function testBlendedUnknownOrUnmarkedQuotationsCannotUseReflectionChannel(): void
    {
        foreach ([
            $this->document(['excerpts' => ['The plans of the diligent lead surely to abundance.']]),
            $this->document(['translation' => 'NIV']),
            $this->document(['book' => 'Hezekiah']),
            $this->document(['text' => 'Invented quotation.']),
            $this->document(['excerpts' => ['only to want.', 'The thoughts']]),
            $this->document(['excerpts' => ['thought']]),
        ] as $document) { $this->reject($document); }
        foreach ([
            '"Invented scripture"', '“Invented scripture”', "'Invented scripture'", '> Invented scripture',
            'Jesus says invented scripture.', 'Scripture says invented scripture.',
            'John 3:16: Invented scripture.', '<blockquote>Invented scripture</blockquote>',
            'God declares: invented scripture.', '《Invented scripture》',
        ] as $text) {
            $document = $this->document(); $document['segments'][0]['text'] = $text;
            $this->reject($document);
        }
    }

    private function reject(array $document): void
    {
        try { (new ScriptureVerifiedReply())->render($document); }
        catch (RuntimeException $expected) { return; }
        throw new RuntimeException('Accepted unverified quotation: ' . json_encode($document));
    }
}
