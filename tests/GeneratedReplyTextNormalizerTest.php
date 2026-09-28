<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Support\GeneratedReplyTextNormalizer;

final class GeneratedReplyTextNormalizerTest
{
    public function testGeneratedReplyTextIsNormalizedToAscii(): void
    {
        $text = GeneratedReplyTextNormalizer::normalize(
            "Smart \u{201C}quotes\u{201D}, dash \u{2014}, ellipsis\u{2026}, cafe\u{00E9}"
        );

        assertSame('Smart "quotes", dash -, ellipsis..., cafe', $text);
    }

    public function testGeneratedReplyTextCanPreserveVisibleUnicode(): void
    {
        $text = GeneratedReplyTextNormalizer::normalize(
            "Smart \u{201C}quotes\u{201D}: Хорошо",
            true,
        );

        assertSame('Smart "quotes": Хорошо', $text);
    }

    public function testGeneratedReplyTextCanPreserveEmojiWhenEnabled(): void
    {
        $text = GeneratedReplyTextNormalizer::normalize(
            "Looks good 🙂",
            true,
            true,
        );

        assertSame('Looks good 🙂', $text);
    }
}
