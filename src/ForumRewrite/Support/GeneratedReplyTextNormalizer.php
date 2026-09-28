<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

final class GeneratedReplyTextNormalizer
{
    public static function normalize(
        string $text,
        bool $allowUnicodeAuthoredText = false,
        bool $allowEmojiAuthoredText = false,
    ): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", trim($text));
        $normalized = strtr($normalized, [
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{201C}" => '"',
            "\u{201D}" => '"',
            "\u{2013}" => '-',
            "\u{2014}" => '-',
            "\u{2026}" => '...',
            "\u{00A0}" => ' ',
        ]);

        if ($allowUnicodeAuthoredText) {
            return trim((new UnicodeTextPolicy($allowEmojiAuthoredText))->normalizeBody($normalized, 'response_text'));
        }

        $normalized = preg_replace('/[^\x0A\x20-\x7E]/u', '', $normalized);

        return trim($normalized ?? '');
    }
}
