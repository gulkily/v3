<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

final class PublicMarkdownRenderer
{
    public static function render(string $markdown, ?string $sourcePath = null, ?string $projectRoot = null): string
    {
        try {
            return self::convert($markdown, $sourcePath, $projectRoot ?? dirname(__DIR__, 3));
        } catch (\Throwable $error) {
            throw new MarkdownRenderingUnavailable('Markdown rendering unavailable; restore the complete application package.', 0, $error);
        }
    }

    private static function convert(string $markdown, ?string $sourcePath, string $projectRoot): string
    {
        // Preserve the current metadata boundary; never apply embedded presentation CSS.
        $metadata = '';
        if (preg_match('/\A(?:\xEF\xBB\xBF)?---\r?\n(.*?)\r?\n(?:---|\.\.\.)[ \t]*(?:\r?\n|$)/s', $markdown, $match) === 1) {
            $metadata = '<details class="docs-metadata"><summary>Document metadata</summary>'
                . "\n" . '<pre tabindex="0" role="region" aria-label="Document metadata"><code>'
                . self::escape($match[1]) . '</code></pre></details>';
            $markdown = substr($markdown, strlen($match[0]));
        }
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        $html = [];
        $paragraph = [];
        $listType = null;
        $inCodeBlock = false;
        $codeLines = [];
        $codeLanguage = '';
        $headings = [];
        $tableLines = [];
        $inComment = false;

        $flushParagraph = static function () use (&$html, &$paragraph, $sourcePath, $projectRoot): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . self::inline(implode("\n", $paragraph), $sourcePath, $projectRoot) . '</p>';
                $paragraph = [];
            }
        };
        $flushList = static function () use (&$html, &$listType): void {
            if ($listType !== null) {
                $html[] = '</' . $listType . '>';
                $listType = null;
            }
        };

        $flushTable = static function () use (&$html, &$tableLines, $sourcePath, $projectRoot): void {
            if ($tableLines !== []) {
                $html[] = self::table($tableLines, $sourcePath, $projectRoot);
                $tableLines = [];
            }
        };
        foreach ($lines as $lineNumber => $line) {
            if (preg_match('/^\s*```([^`]*)$/', $line, $fence) === 1) {
                $flushParagraph();
                $flushList();
                $flushTable();
                if ($inCodeBlock) {
                    $html[] = self::codeBlock($codeLines, $codeLanguage);
                    $codeLines = [];
                }
                if (!$inCodeBlock) {
                    $codeLanguage = explode(' ', trim($fence[1]))[0];
                }
                $inCodeBlock = !$inCodeBlock;
                continue;
            }

            if ($inCodeBlock) {
                $codeLines[] = $line;
                continue;
            }

            // Hide comments in prose only; fenced examples remain verbatim.
            if ($inComment) {
                $end = strpos($line, '-->');
                if ($end === false) { continue; }
                $line = substr($line, $end + 3);
                $inComment = false;
            }
            $line = preg_replace('/<!--.*?-->/', '', $line) ?? $line;
            $start = strpos($line, '<!--');
            if ($start !== false) {
                $line = substr($line, 0, $start);
                $inComment = true;
            }
            if (preg_match('/^\s*<img\b[^>]*>\s*$/i', $line) === 1
                && preg_match('/\balt\s*=\s*(["\'])(.*?)\1/is', $line, $alt) === 1) {
                $flushParagraph(); $flushList(); $flushTable();
                $html[] = '<p>' . self::escape(html_entity_decode($alt[2], ENT_QUOTES | ENT_HTML5, 'UTF-8') . ' (image unavailable)') . '</p>';
                continue;
            }
            $nextLine = $lines[$lineNumber + 1] ?? '';
            $continuesTable = $tableLines !== [] && str_contains($line, '|');
            $startsTable = str_starts_with(ltrim($line), '|')
                || (str_contains($line, '|') && preg_match('/^[ \t|:\-]+$/', $nextLine) === 1 && str_contains($nextLine, '|'));
            if ($continuesTable || $startsTable) {
                $flushParagraph(); $flushList();
                $tableLines[] = $line;
                continue;
            }
            $flushTable();
            if (trim($line) === '') {
                $flushParagraph();
                $flushList();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $matches) === 1) {
                $flushParagraph();
                $flushList();
                $level = strlen($matches[1]);
                // Keep existing document-fragment navigation when restoring the parser.
                $slug = mb_strtolower(strip_tags(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                $slug = preg_replace('/[^\pL\pN _-]/u', '', $slug) ?? '';
                $slug = preg_replace('/[\s]+/u', '-', trim($slug)) ?: 'section';
                $id = $slug;
                for ($n = 1; isset($headings[$id]); $n++) { $id = $slug . '-' . $n; }
                $headings[$id] = true;
                $html[] = "<h{$level} id=\"" . self::escape($id) . '">' . self::inline($matches[2], $sourcePath, $projectRoot) . "</h{$level}>";
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
                $html[] = '<li>' . self::inline($matches[2], $sourcePath, $projectRoot) . '</li>';
                continue;
            }

            if (preg_match('/^\s*(\d+)[.)]\s+(.+)$/', $line, $matches) === 1) {
                $flushParagraph();
                if ($listType !== 'ol') {
                    $flushList();
                    $html[] = '<ol start="' . (int) $matches[1] . '">';
                    $listType = 'ol';
                }
                $html[] = '<li>' . self::inline($matches[2], $sourcePath, $projectRoot) . '</li>';
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $line, $matches) === 1) {
                $flushParagraph();
                $flushList();
                $html[] = '<blockquote><p>' . self::inline($matches[1], $sourcePath, $projectRoot) . '</p></blockquote>';
                continue;
            }

            $flushList();
            $paragraph[] = $line;
        }

        $flushParagraph();
        $flushList();
        $flushTable();

        if ($inCodeBlock) {
            $html[] = self::codeBlock($codeLines, $codeLanguage);
        }

        return $metadata . implode("\n", $html);
    }

    private static function table(array $lines, ?string $sourcePath, string $projectRoot): string
    {
        $fallback = static fn (): string => '<pre class="docs-table-source" tabindex="0" role="region" aria-label="Table source"><code>'
            . self::escape(implode("\n", $lines)) . '</code></pre>';
        $rows = array_map(static fn (string $line): array => array_map('trim', explode('|', trim(trim($line), '|'))), $lines);
        if (count($rows) < 2 || count($rows[0]) < 2 || count($rows[1]) !== count($rows[0])) { return $fallback(); }
        $alignments = [];
        foreach ($rows[1] as $cell) {
            if (preg_match('/^:?-{3,}:?$/', $cell) !== 1) { return $fallback(); }
            $alignments[] = str_starts_with($cell, ':') ? (str_ends_with($cell, ':') ? 'center' : 'left')
                : (str_ends_with($cell, ':') ? 'right' : null);
        }
        foreach ($rows as $row) {
            if (count($row) !== count($rows[0])) { return $fallback(); }
        }
        $renderRow = static function (array $row, string $tag) use ($alignments, $sourcePath, $projectRoot): string {
            $html = '<tr>';
            foreach ($row as $i => $cell) {
                $html .= '<' . $tag . ($tag === 'th' ? ' scope="col"' : '')
                    . ($alignments[$i] === null ? '' : ' class="docs-align-' . $alignments[$i] . '"') . '>'
                    . self::inline($cell, $sourcePath, $projectRoot) . '</' . $tag . '>';
            }
            return $html . '</tr>';
        };
        $html = '<div class="docs-table-scroll" tabindex="0" role="region" aria-label="Table"><table><thead>'
            . $renderRow($rows[0], 'th') . '</thead><tbody>';
        foreach (array_slice($rows, 2) as $row) { $html .= $renderRow($row, 'td'); }
        return $html . '</tbody></table></div>';
    }

    private static function codeBlock(array $lines, string $language): string
    {
        $code = '<pre tabindex="0" role="region" aria-label="Code"><code'
            . ($language === '' ? '' : ' class="language-' . self::escape($language) . '"') . '>'
            . self::escape(implode("\n", $lines)) . '</code></pre>';
        return $language === '' ? $code : '<figure class="docs-code"><figcaption>'
            . self::escape($language === 'mermaid' ? 'Mermaid source' : $language) . '</figcaption>' . "\n" . $code . "\n</figure>";
    }

    private static function inline(string $text, ?string $sourcePath, string $projectRoot): string
    {
        // Protect generated markup from later emphasis replacements and input token collisions.
        $text = str_replace("\x1A", '', $text);
        $tokens = [];
        $store = static function (string $html) use (&$tokens): string {
            $key = "\x1A" . count($tokens) . "\x1A";
            $tokens[$key] = $html;
            return $key;
        };
        $escaped = self::escape($text);
        $escaped = preg_replace_callback('/`([^`]+)`/', static fn (array $m): string => $store('<code>' . $m[1] . '</code>'), $escaped) ?? $escaped;
        $escaped = preg_replace_callback('/(!?)\[([^\]]+)\]\(([^\s)]+)(?:\s+&quot;[^)]*&quot;)?\)/',
            static function (array $m) use ($store, &$tokens, $sourcePath, $projectRoot): string {
                $label = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $m[2]) ?? $m[2];
                $label = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $label) ?? $label;
                $label = strtr($label, $tokens);
                $url = $m[1] === '!' ? null : PlatformDocsCatalog::resolveLink(html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'), $sourcePath, $projectRoot);
                return $store($url === null ? $label . ($m[1] === '!' ? ' (image unavailable)' : ' (source unavailable)')
                    : '<a href="' . self::escape($url) . '">' . $label . '</a>');
            }, $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/', '<em>$1</em>', $escaped) ?? $escaped;
        return strtr($escaped, $tokens);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
