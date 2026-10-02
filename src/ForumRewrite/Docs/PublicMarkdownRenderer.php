<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

final class PublicMarkdownRenderer
{
    public static function render(string $markdown): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        $html = [];
        $paragraph = [];
        $listType = null;
        $inCodeBlock = false;
        $codeLines = [];

        $flushParagraph = static function () use (&$html, &$paragraph): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . self::inline(implode("\n", $paragraph)) . '</p>';
                $paragraph = [];
            }
        };
        $flushList = static function () use (&$html, &$listType): void {
            if ($listType !== null) {
                $html[] = '</' . $listType . '>';
                $listType = null;
            }
        };

        foreach ($lines as $line) {
            if (preg_match('/^\s*```/', $line) === 1) {
                $flushParagraph();
                $flushList();
                if ($inCodeBlock) {
                    $html[] = '<pre><code>' . self::escape(implode("\n", $codeLines)) . '</code></pre>';
                    $codeLines = [];
                }
                $inCodeBlock = !$inCodeBlock;
                continue;
            }

            if ($inCodeBlock) {
                $codeLines[] = $line;
                continue;
            }

            if (trim($line) === '') {
                $flushParagraph();
                $flushList();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $matches) === 1) {
                $flushParagraph();
                $flushList();
                $level = strlen($matches[1]);
                $html[] = "<h{$level}>" . self::inline($matches[2]) . "</h{$level}>";
                continue;
            }

            if (preg_match('/^\s*(?:[-*_]\s*){3,}$/', $line) === 1) {
                $flushParagraph();
                $flushList();
                $html[] = '<hr>';
                continue;
            }

            if (preg_match('/^\s*([-*+])\s+(.+)$/', $line, $matches) === 1) {
                $flushParagraph();
                if ($listType !== 'ul') {
                    $flushList();
                    $html[] = '<ul>';
                    $listType = 'ul';
                }
                $html[] = '<li>' . self::inline($matches[2]) . '</li>';
                continue;
            }

            if (preg_match('/^\s*\d+[.)]\s+(.+)$/', $line, $matches) === 1) {
                $flushParagraph();
                if ($listType !== 'ol') {
                    $flushList();
                    $html[] = '<ol>';
                    $listType = 'ol';
                }
                $html[] = '<li>' . self::inline($matches[1]) . '</li>';
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $line, $matches) === 1) {
                $flushParagraph();
                $flushList();
                $html[] = '<blockquote><p>' . self::inline($matches[1]) . '</p></blockquote>';
                continue;
            }

            $flushList();
            $paragraph[] = $line;
        }

        $flushParagraph();
        $flushList();

        if ($inCodeBlock) {
            $html[] = '<pre><code>' . self::escape(implode("\n", $codeLines)) . '</code></pre>';
        }

        return implode("\n", $html);
    }

    private static function inline(string $text): string
    {
        $tokens = [];
        $escaped = self::escape($text);
        $escaped = preg_replace_callback('/`([^`]+)`/', static function (array $matches) use (&$tokens): string {
            $token = "\x1A" . count($tokens) . "\x1A";
            $tokens[$token] = '<code>' . $matches[1] . '</code>';

            return $token;
        }, $escaped) ?? $escaped;
        $escaped = preg_replace_callback('/\[([^\]]+)\]\(([^\s)]+)(?:\s+&quot;[^)]*&quot;)?\)/', static function (array $matches): string {
            $url = html_entity_decode($matches[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if (!self::isSafeLink($url)) {
                return $matches[1];
            }

            return '<a href="' . self::escape($url) . '">' . self::inline($matches[1]) . '</a>';
        }, $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $escaped) ?? $escaped;

        return strtr($escaped, $tokens);
    }

    private static function isSafeLink(string $url): bool
    {
        return str_starts_with($url, '/')
            || str_starts_with($url, '#')
            || preg_match('#^https?://#i', $url) === 1
            || preg_match('#^mailto:#i', $url) === 1
            || preg_match('~^(?:[A-Za-z0-9._-]+/)*[A-Za-z0-9._-]+(?:#[A-Za-z0-9_-]+)?$~', $url) === 1;
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
