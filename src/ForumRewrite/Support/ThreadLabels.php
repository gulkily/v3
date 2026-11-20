<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * The hashtag rule for thread labels, shared by the write side (which turns a post's hashtags
 * into thread label records) and the thread page (which spots replies that are only hashtags).
 */
final class ThreadLabels
{
    private const HASHTAG = '/(^|[^A-Za-z0-9-])#([A-Za-z0-9-]+)/';
    private const LABEL = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    /** Code spans, Markdown links and URLs; the same protected text as View\PostHashtags. */
    private const PROTECTED_TEXT = '~(`[^`]*(?:`|$)|\[[^\]]*\]\([^)]*\)|&(?:amp;)?#(?:[0-9]+|x[0-9a-f]+);|(?:[a-z][a-z0-9+.-]*://|www\.)\S+)~i';
    /** A hashtag that renders as a tag link (View\PostHashtags). */
    private const LINK_HASHTAG = '~(?<![\p{L}\p{N}\p{M}_/#&-])#([a-z0-9]+(?:-[a-z0-9]+)*)(?![\p{L}\p{N}\p{M}_-])~u';

    /** @return list<string> labels in first-seen order; quoted (`>`) lines are skipped */
    public static function fromBody(string $body): array
    {
        $labels = [];
        foreach (explode("\n", $body) as $line) {
            if (self::isQuoted($line) || preg_match_all(self::HASHTAG, $line, $matches) < 1) {
                continue;
            }
            foreach ($matches[2] as $label) {
                if (preg_match(self::LABEL, $label) === 1 && !in_array($label, $labels, true)) {
                    $labels[] = $label;
                }
            }
        }

        return $labels;
    }

    /**
     * The self-tag rule (FORUM_HASHTAG_SELF_TAGS_ENABLED): a hashtag tags its thread only on a
     * line made of hashtags, or in the run of hashtags that ends the last non-blank line. Other
     * hashtags are references. Code, links, URLs, quoted lines, all-digit and colour-like
     * hashtags never tag. public/assets/hashtag_tags.js mirrors this for the composer; both
     * are tested against tests/fixtures/hashtag_self_tags_examples.txt.
     *
     * @return list<string> labels in first-seen order
     */
    public static function selfTags(string $body): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
        $last = null;
        foreach ($lines as $index => $line) {
            if (trim($line) !== '') {
                $last = $index;
            }
        }
        $labels = [];
        $inFence = false;
        foreach ($lines as $index => $line) {
            if (preg_match('/^ {0,3}(?:```|~~~)/', $line) === 1) {
                $inFence = !$inFence;
                continue;
            }
            if ($inFence || self::isQuoted($line)) {
                continue;
            }
            $tokens = self::lineTokens($line);
            $tagging = self::isHashtagLine($tokens) ? $tokens : ($index === $last ? self::trailingRun($tokens) : []);
            foreach ($tagging as [$kind, $label]) {
                if ($kind === '#' && !self::neverTags($label) && !in_array($label, $labels, true)) {
                    $labels[] = $label;
                }
            }
        }

        return $labels;
    }

    /**
     * Splits a line into hashtags (['#', label]) and other text (['', text]). Code spans,
     * Markdown links and URLs are text, so hashtags inside them are never seen.
     *
     * @return list<array{0:string,1:string}>
     */
    private static function lineTokens(string $line): array
    {
        $tokens = [];
        $parts = preg_split(self::PROTECTED_TEXT, $line, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$line];
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                $tokens[] = ['', $part];
                continue;
            }
            $pieces = preg_split(self::LINK_HASHTAG, $part, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$part];
            foreach ($pieces as $j => $piece) {
                if ($j % 2 === 1) {
                    $tokens[] = ['#', $piece];
                } elseif ($piece !== '') {
                    $tokens[] = ['', $piece];
                }
            }
        }

        return $tokens;
    }

    /** @param list<array{0:string,1:string}> $tokens */
    private static function isHashtagLine(array $tokens): bool
    {
        $found = false;
        foreach ($tokens as [$kind, $text]) {
            if ($kind === '#') {
                $found = true;
            } elseif (trim($text) !== '') {
                return false;
            }
        }

        return $found;
    }

    /**
     * @param list<array{0:string,1:string}> $tokens
     * @return list<array{0:string,1:string}> the hashtags at the end, separated only by spaces
     */
    private static function trailingRun(array $tokens): array
    {
        $run = [];
        for ($i = count($tokens) - 1; $i >= 0; $i--) {
            [$kind, $text] = $tokens[$i];
            if ($kind === '#') {
                array_unshift($run, $tokens[$i]);
                continue;
            }
            if (trim($text) !== '') {
                break;
            }
        }

        return $run;
    }

    private static function neverTags(string $label): bool
    {
        return preg_match('/^(?:[0-9]+|[0-9a-f]{3}|[0-9a-f]{6})$/', $label) === 1;
    }

    /**
     * True when a reply says nothing but labels: its body has at least one label, and nothing is
     * left once quoted lines, label hashtags and whitespace are removed. A subject, a thread type,
     * agent-response metadata or a retraction keeps the reply in the conversation.
     *
     * @param array<string, mixed> $post a posts read-model row
     */
    public static function isTagOnly(array $post): bool
    {
        return self::isBookkeepingOnly($post, false);
    }

    /**
     * Like isTagOnly, but one `title:` line (see TitleDirective) also counts, alone or with labels.
     *
     * @param array<string, mixed> $post a posts read-model row
     */
    public static function isTagOrTitleOnly(array $post): bool
    {
        return self::isBookkeepingOnly($post, true);
    }

    /** @param array<string, mixed> $post */
    private static function isBookkeepingOnly(array $post, bool $allowTitle): bool
    {
        foreach (['subject', 'thread_type', 'agent_response_json', 'retracted_at'] as $field) {
            if (trim((string) ($post[$field] ?? '')) !== '') {
                return false;
            }
        }
        $body = (string) ($post['body'] ?? '');
        $titleLines = 0;
        foreach (explode("\n", $body) as $line) {
            if (self::isQuoted($line)) {
                continue;
            }
            if ($allowTitle && TitleDirective::lineValue($line) !== null) {
                // Only the first `title:` line takes effect; a second one is ordinary text.
                if (++$titleLines > 1) {
                    return false;
                }
                continue;
            }
            $rest = preg_replace_callback(
                self::HASHTAG,
                static fn (array $m): string => preg_match(self::LABEL, $m[2]) === 1 ? $m[1] : $m[0],
                $line
            );
            if (trim((string) $rest) !== '') {
                return false;
            }
        }

        return $titleLines > 0 || self::fromBody($body) !== [];
    }

    private static function isQuoted(string $line): bool
    {
        return preg_match('/^\s*>/', $line) === 1;
    }
}
