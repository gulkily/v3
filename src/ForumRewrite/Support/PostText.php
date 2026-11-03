<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * Plain text for places that show a post's words without rendering it: Board and Recent
 * previews, titles taken from a body, quotes and the reply composer's parent. Plain posts
 * pass through unchanged; Markdown posts lose their markup but keep their words, line by line.
 */
final class PostText
{
    public static function plainPreview(string $body, ?string $format): string
    {
        if ($format !== 'markdown') {
            return $body;
        }

        $lines = preg_split('/\r\n|\r|\n/', $body) ?: [];
        $inFence = false;
        foreach ($lines as &$line) {
            if (preg_match('/^\s*(?:`{3,}|~{3,})/', $line) === 1) {
                // Fence markers carry no words; the code inside stays as written.
                $inFence = !$inFence;
                $line = '';
                continue;
            }
            if ($inFence) {
                continue;
            }
            if (preg_match('/^\s*(?:[-*_]\s*){3,}$/', $line) === 1 || preg_match('/^[ \t|:\-]+$/', $line) === 1 && str_contains($line, '-')) {
                // Thematic breaks and table delimiter rows.
                $line = '';
                continue;
            }
            $line = preg_replace('/^\s{0,3}#{1,6}\s+(.*?)\s*#*\s*$/', '$1', $line) ?? $line;
            $line = preg_replace('/^(\s*)>\s?/', '$1', $line) ?? $line;
            $line = preg_replace('/^(\s*[-+*]\s+)\[[ xX]\]\s+/', '$1', $line) ?? $line;
            if (str_starts_with(ltrim($line), '|')) {
                $line = trim(preg_replace('/\s*\|\s*/', ' ', trim($line)) ?? $line);
            }
            $line = preg_replace('/!?\[([^\]]+)\]\(([^\s)]+)(?:\s+"[^)]*")?\)/', '$1', $line) ?? $line;
            $line = preg_replace('/`([^`]+)`/', '$1', $line) ?? $line;
            $line = preg_replace('/\*\*(?=\S)([^*]+?)(?<=\S)\*\*/', '$1', $line) ?? $line;
            $line = preg_replace('/(?<![*\w])\*(?=\S)([^*]+?)(?<=\S)\*(?![*\w])/', '$1', $line) ?? $line;
        }
        unset($line);

        return implode("\n", $lines);
    }
}
