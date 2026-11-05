<?php

declare(strict_types=1);

namespace ForumRewrite\View;

/** Post presentation only; never changes the signed body or assigns thread labels. */
final class PostHashtags
{
    /** @param list<string> $labels */
    public static function renderLabels(array $labels): string
    {
        return implode(', ', array_map(static function (string $label): string {
            $text = htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<a href="/tags/' . rawurlencode(strtolower($label)) . '">' . $text . '</a>';
        }, $labels));
    }

    /**
     * Accept escaped text, not arbitrary HTML. Markdown calls this while its code and
     * links are still protected by tokens. Plain text keeps literal code/links/URLs.
     * Keep the offline_reader.js equivalent and its parity test in sync.
     */
    public static function linkEscapedText(string $text): string
    {
        $parts = preg_split('~(```[\s\S]*?(?:```|\z)|`[^`\r\n]*`|\[[^\]\r\n]*\]\([^\r\n)]*\)|&(?:amp;)?#(?:[0-9]+|x[0-9a-f]+);|(?:[a-z][a-z0-9+.-]*://|www\.)[^\s<>]+)~i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) return $text;
        foreach ($parts as $index => &$part) {
            if ($index % 2 !== 0) continue;
            // Canonical tags are lowercase ASCII with single internal hyphens.
            // Do not link a partial word, an HTML entity, or a relative URL fragment.
            $part = preg_replace_callback('~(?<![\p{L}\p{N}\p{M}_/#&-])#([a-z0-9]+(?:-[a-z0-9]+)*)(?![\p{L}\p{N}\p{M}_-])~u',
                static fn (array $match): string => '<a href="/tags/' . $match[1] . '">' . $match[0] . '</a>',
                $part) ?? $part;
        }
        return implode('', $parts);
    }
}
