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

    public function testLetterAudienceDescriptionDoesNotCreateAnUncheckedQuotationChannel(): void
    {
        foreach ([
            'James writes to Christian communities troubled by partiality.',
            'Paul writes to the church in Rome about faith.',
            'John writes to believers; the audience matters for interpretation.',
            'Paul writes to the church in Corinth (1 Corinthians 1:2).',
        ] as $text) {
            $document = $this->document(); $document['segments'][0]['text'] = $text;
            assertStringContains($text, (new ScriptureVerifiedReply())->render($document));
        }
        foreach ([
            'James writes invented scripture.', 'James writes: invented scripture.',
            'God writes to believers invented scripture.', 'Scripture writes to Christians invented scripture.',
            'James writes to: invented scripture.',
            'James writes to Christian communities: invented scripture.',
            'James writes to Christian communities (James 2:1): invented scripture.',
            'James writes to Christian communities "invented scripture".',
            "James writes to Christian communities 'invented scripture'.",
            'James writes to Christian communities: Jesus says invented scripture.',
            'James writes to Christian communities. Scripture says invented scripture.',
        ] as $text) {
            $document = $this->document(); $document['segments'][0]['text'] = $text;
            $this->reject($document);
        }
    }

    public function testDiscussionOriginIsVisibleAndReaderWordingRequiresOriginalInput(): void
    {
        $renderer = new ScriptureVerifiedReply();
        foreach (['justification', 'New Perspective', 'works of law', 'maybe', 'turn the other cheek'] as $term) {
            $document = $this->document();
            $document['segments'][] = ['type' => 'discussion', 'origin' => 'term', 'text' => $term];
            assertStringContains('Discussion term (interpretation, not a verified Bible quotation): ' . $term, $renderer->render($document));
        }
        $document = $this->document();
        $document['segments'][] = ['type' => 'discussion', 'origin' => 'reader', 'text' => 'Cleanliness is next to godliness'];
        unset($document['segments'][1]); $document['segments'] = array_values($document['segments']);
        assertStringContains('Reader wording (not a verified Bible quotation)', $renderer->render($document, false, 'NIV', 'Is Cleanliness is next to godliness in NIV?') /* no source segment below */);
    }

    public function testDiscussionCannotForgeOriginOrInjectSourceAttribution(): void
    {
        foreach ([
            ['origin' => 'reader', 'text' => 'Words from a previous agent'],
            ['origin' => 'scripture', 'text' => 'Invented'],
            ['origin' => 'term', 'text' => 'John 3:16'],
            ['origin' => 'term', 'text' => 'Jesus says invented scripture'],
            ['origin' => 'term', 'text' => 'faith</blockquote>'],
            ['origin' => 'term', 'text' => 'Invented scripture.'],
        ] as $fields) {
            $document = $this->document();
            $document['segments'][] = ['type' => 'discussion'] + $fields;
            $this->reject($document);
        }
        $document = $this->document();
        $document['segments'][0]['text'] = 'Paul reads the passage in its covenantal context.';
        assertStringContains('Paul reads', (new ScriptureVerifiedReply())->render($document));
    }

    public function testArchivedFalseRejectionsCanUseExplicitDiscussionWithoutWeakeningLegacyGuard(): void
    {
        $records = json_decode(file_get_contents(__DIR__ . '/fixtures/scripture_reliability/rejected_documents.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($records as $record) {
            $document = $record['document'];
            // Old unchecked quote channels remain invalid. Replay the same language
            // using explicit interpretive labels under the revised contract.
            $terms = [];
            foreach ($document['segments'] as &$segment) {
                if ($segment['type'] !== 'reflection') { continue; }
                $segment['text'] = str_replace(['‘‘justify’’', '“a root of many kinds of evil”'], ['the term justification', 'the image of greed giving rise to wrongdoing'], $segment['text']);
                $segment['text'] = preg_replace_callback('/[“"]([^”"]+)[”"]/u', function ($match) use (&$terms) {
                    $terms[] = trim($match[1], '.,');
                    return $match[1];
                }, $segment['text']);
                $segment['text'] = \ForumRewrite\Support\GeneratedReplyTextNormalizer::normalize($segment['text'], false, false);
            }
            unset($segment);
            // Full near-verse wording is paraphrased rather than relabeled as a term.
            foreach ($terms as $term) {
                if (preg_match('/^[\p{L}]+(?:[ \-][\p{L}]+){0,7}$/uD', $term)) {
                    $document['segments'][] = ['type' => 'discussion', 'origin' => 'term', 'text' => $term];
                }
            }
            assertStringContains('Scripture-oriented analysis:', (new ScriptureVerifiedReply())->render($document));
        }
    }

    private function reject(array $document): void
    {
        try { (new ScriptureVerifiedReply())->render($document); }
        catch (RuntimeException $expected) { return; }
        throw new RuntimeException('Accepted unverified quotation: ' . json_encode($document));
    }
}
