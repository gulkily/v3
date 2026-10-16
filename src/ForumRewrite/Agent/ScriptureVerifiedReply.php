<?php

declare(strict_types=1);

namespace ForumRewrite\Agent;

use RuntimeException;
use ForumRewrite\Support\GeneratedReplyTextNormalizer;

/** Render source-owned quotations separately from untrusted interpretation. */
final class ScriptureVerifiedReply
{
    public function __construct(private readonly ScriptureSource $source = new ScriptureSource()) {}

    public function render(array $document, bool $expansion = false, ?string $requestedTranslation = null): string
    {
        if (array_keys($document) !== ['segments'] || !is_array($document['segments'])
            || !array_is_list($document['segments']) || $document['segments'] === []) {
            throw new ScriptureVerificationException('Invalid verified scripture reply document.');
        }
        $available = true;
        try { $this->source->books(); } catch (ScriptureVerificationException) { $available = false; }
        $unsupported = $requestedTranslation !== null && $requestedTranslation !== 'KJV';
        $parts = []; $reflections = 0;
        foreach ($document['segments'] as $segment) {
            if (!is_array($segment)) { throw new ScriptureVerificationException('Invalid scripture segment.'); }
            if (($segment['type'] ?? '') === 'reflection') {
                $this->keys($segment, ['type', 'text']);
                if (!is_string($segment['text']) || trim($segment['text']) === '') {
                    throw new ScriptureVerificationException('Empty scripture reflection.');
                }
                $this->reflection($segment['text']);
                $parts[] = trim($segment['text']); $reflections++;
            } elseif (($segment['type'] ?? '') === 'quotation') {
                if (!$available || $unsupported) { throw new ScriptureVerificationException('Requested quotation source is unavailable; use explicit paraphrase or retry.'); }
                $this->keys($segment, ['type', 'book', 'chapter', 'start', 'end', 'translation'], ['excerpts']);
                if (($segment['translation'] ?? '') !== 'KJV' || !is_string($segment['book'])
                    || !is_int($segment['chapter']) || !is_int($segment['start']) || !is_int($segment['end'])) {
                    throw new ScriptureVerificationException('Unsupported scripture quotation or translation.');
                }
                $text = $this->source->passage($segment['book'], $segment['chapter'], $segment['start'], $segment['end']);
                if (isset($segment['excerpts'])) {
                    $text = $this->excerpts($text, $segment['excerpts']);
                }
                $text = GeneratedReplyTextNormalizer::normalize($text, true, true);
                $range = $segment['start'] . ($segment['end'] === $segment['start'] ? '' : '-' . $segment['end']);
                $parts[] = '"' . $text . '" (' . $segment['book'] . ' ' . $segment['chapter'] . ':' . $range . ', KJV)';
            } else { throw new ScriptureVerificationException('Unsupported scripture segment type.'); }
        }
        if ($reflections === 0) { throw new ScriptureVerificationException('Scripture reply lacks an answer to the concern.'); }
        $limitation = $unsupported
            ? 'Verified text for the requested translation is unavailable. No other translation has been substituted; the discussion uses paraphrase and interpretation.'
            : (!$available ? 'Verified KJV source text is unavailable. The discussion uses paraphrase and interpretation.' : '');
        if ($limitation !== '') { array_unshift($parts, $limitation); }
        return ($expansion ? 'Further scripture reflection: ' : 'Scripture-oriented analysis: ')
            . implode("\n\n", $parts) . "\n\nNon-quoted text is interpretation and practical reflection.";
    }

    private function keys(array $value, array $required, array $optional = []): void
    {
        if (array_diff($required, array_keys($value)) !== [] || array_diff(array_keys($value), [...$required, ...$optional]) !== []) {
            throw new ScriptureVerificationException('Unexpected scripture segment fields.');
        }
    }

    private function excerpts(string $source, mixed $excerpts): string
    {
        if (!is_array($excerpts) || !array_is_list($excerpts) || $excerpts === []) {
            throw new ScriptureVerificationException('Invalid scripture excerpts.');
        }
        $offset = 0; $parts = [];
        foreach ($excerpts as $excerpt) {
            if (!is_string($excerpt) || trim($excerpt) !== $excerpt || $excerpt === '') {
                throw new ScriptureVerificationException('Invalid scripture excerpt.');
            }
            $position = strpos($source, $excerpt, $offset);
            if ($position === false || ($position > 0 && ctype_alnum($source[$position - 1]) && ctype_alnum($excerpt[0]))
                || (isset($source[$position + strlen($excerpt)]) && ctype_alnum($source[$position + strlen($excerpt)]) && ctype_alnum(substr($excerpt, -1)))) {
                throw new ScriptureVerificationException('Scripture quotation does not match the verified source.');
            }
            if ($position > $offset) { $parts[] = '...'; }
            $parts[] = $excerpt; $offset = $position + strlen($excerpt);
        }
        if ($offset < strlen($source)) { $parts[] = '...'; }
        return implode(' ', $parts);
    }

    private function reflection(string $text): void
    {
        // Prose cannot create a second, unchecked quotation channel. Apostrophes
        // inside words remain valid; quotations, markup and direct-speech claims do not.
        if (preg_match('/["\x{201C}\x{201D}\x{2018}\x{00AB}\x{00BB}\x{2039}\x{203A}\x{FF02}\x{301D}\x{301E}\x{300A}\x{300B}\p{Pi}\p{Pf}<>`\p{Cf}]|(?<![\p{L}])\x{27}|^\s*>/um', $text)
            || preg_match('/\b(?:says?|said|saith|writes?|reads?|written|declares?|commands?)\s*[:;,]/iu', $text)
            || preg_match('/\b(?:Bible|Scripture|God|Jesus|Lord|Paul|James|John|Peter|Moses|Isaiah|Proverbs?|Psalms?)\s+(?:says?|said|saith|writes?|reads?|declares?)\b/iu', $text)
            || preg_match('/\b\d+:\d+(?:-\d+)?\s*:/u', $text)) {
            throw new ScriptureVerificationException('Use verified quotation segments; reflection must be paraphrase or interpretation.');
        }
    }
}
