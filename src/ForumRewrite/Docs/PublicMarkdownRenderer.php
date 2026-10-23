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
        $tableStandalone = true;
        $inComment = false;
        $skipThrough = -1;

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

        $flushTable = static function () use (&$html, &$tableLines, &$tableStandalone, $sourcePath, $projectRoot): void {
            if ($tableLines !== []) {
                $html[] = self::table($tableLines, $tableStandalone, $sourcePath, $projectRoot);
                $tableLines = [];
                $tableStandalone = true;
            }
        };
        foreach ($lines as $lineNumber => $line) {
            if ($lineNumber <= $skipThrough) { continue; }
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
            if (self::listItem($line) !== null && self::listItem($line)['indent'] === 0
                && preg_match('/^\s*(?:[-*_]\s*){3,}$/', $line) !== 1) {
                [$block, $last] = self::listBlock($lines, $lineNumber);
                $block[0] = $line;
                $list = self::flatList($block, $sourcePath, $projectRoot);
                if ($list !== null) {
                    $flushParagraph(); $flushList(); $flushTable();
                    $html[] = $list;
                    $skipThrough = $last;
                    continue;
                }
            }
            if (preg_match('/^\s*<img\b[^>]*>\s*$/i', $line) === 1
                && preg_match('/\balt\s*=\s*(["\'])(.*?)\1/is', $line, $alt) === 1) {
                $flushParagraph(); $flushList(); $flushTable();
                $html[] = '<p>' . self::escape(html_entity_decode($alt[2], ENT_QUOTES | ENT_HTML5, 'UTF-8') . ' (image unavailable)') . '</p>';
                continue;
            }
            $nextLine = $lines[$lineNumber + 1] ?? '';
            $blockStart = preg_match('/^ {0,3}(?:#{1,6}\s|>\s?|[-+*]\s|\d+[.)]\s)/', $line) === 1;
            $continuesTable = $tableLines !== [] && str_contains($line, '|') && !$blockStart;
            $startsTable = str_starts_with(ltrim($line), '|')
                || (str_contains($line, '|') && preg_match('/^[ \t|:\-]+$/', $nextLine) === 1 && str_contains($nextLine, '|'));
            if ($continuesTable || $startsTable) {
                if ($tableLines === []) {
                    $tableStandalone = $listType === null && !$blockStart;
                }
                $tableStandalone = $tableStandalone && preg_match('/^(?: {2}|\t)/', $line) !== 1;
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

    private static function listItem(string $line): ?array
    {
        if (preg_match('/^( *)([-+*]|[0-9]+[.)])( +)(.*)$/', $line, $m) !== 1) { return null; }
        $ordered = ctype_digit($m[2][0]);
        return ['indent' => strlen($m[1]), 'column' => strlen($m[1] . $m[2] . $m[3]),
            'kind' => $ordered ? substr($m[2], -1) : $m[2], 'tag' => $ordered ? 'ol' : 'ul',
            'number' => $ordered ? substr($m[2], 0, -1) : null, 'text' => $m[4]];
    }

    /** Keep only adjacent items and indented continuations; standalone content stays outside. */
    private static function listBlock(array $lines, int $start): array
    {
        $last = $start;
        for ($i = $start + 1, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if (trim($line) === '') {
                $next = $i + 1;
                while ($next < $count && trim($lines[$next]) === '') { $next++; }
                if ($next === $count || (self::listItem($lines[$next]) === null && preg_match('/^[ \t]+\S/', $lines[$next]) !== 1)) { break; }
            } elseif (self::listItem($line) === null && preg_match('/^[ \t]+\S/', $line) !== 1) {
                break;
            }
            if (preg_match('/^\s*(?:[-*_]\s*){3,}$/', $line) === 1) { break; }
            $last = $i;
        }
        return [array_slice($lines, $start, $last - $start + 1), $last];
    }

    private static function flatList(array $lines, ?string $sourcePath, string $projectRoot): ?string
    {
        $html = ''; $kind = null; $tag = null; $openItem = false; $paragraph = []; $column = 0;
        $flush = static function () use (&$html, &$paragraph, $sourcePath, $projectRoot): void {
            if ($paragraph !== []) { $html .= '<p>' . self::inline(implode("\n", $paragraph), $sourcePath, $projectRoot) . '</p>'; $paragraph = []; }
        };
        foreach ($lines as $line) {
            if (str_contains($line, '<!--') || str_contains($line, "\t")) { return null; }
            if (trim($line) === '') { $flush(); continue; }
            $item = self::listItem($line);
            if ($item !== null) {
                if ($item['indent'] !== 0 || strlen($item['number'] ?? '') > 9) { return null; }
                $flush();
                if ($openItem) { $html .= '</li>'; }
                if ($kind !== $item['kind']) {
                    if ($tag !== null) { $html .= '</' . $tag . '>'; }
                    $tag = $item['tag']; $kind = $item['kind'];
                    $html .= '<' . $tag . ($tag === 'ol' ? ' start="' . (int) $item['number'] . '"' : '') . '>';
                }
                $html .= '<li' . ($tag === 'ol' ? ' value="' . (int) $item['number'] . '"' : '') . '>';
                $openItem = true; $column = $item['column']; $line = $item['text'];
            } else {
                $indent = strlen($line) - strlen(ltrim($line, ' '));
                if ($indent < $column || $indent >= $column + 4) { return null; }
                $line = substr($line, $column);
            }
            if (preg_match('/^(?:[|>#]|`{3}|~{3})/', ltrim($line)) === 1) { return null; }
            $paragraph[] = $line;
        }
        $flush();
        return $html . '</li></' . $tag . '>';
    }

    private static function table(array $lines, bool $standalone, ?string $sourcePath, string $projectRoot): string
    {
        $fallback = static fn (): string => '<pre class="docs-table-source" tabindex="0" role="region" aria-label="Table source"><code>'
            . self::escape(implode("\n", $lines)) . '</code></pre>';
        $rows = array_map(self::tableCells(...), $lines);
        if (!$standalone || in_array(null, $rows, true) || count($rows) < 2 || count($rows[0]) < 2 || count($rows[1]) !== count($rows[0])) { return $fallback(); }
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

    /** Split only structural pipes; uncertain code spans retain the complete source block. */
    private static function tableCells(string $line): ?array
    {
        $line = trim($line);
        $cells = [];
        $cell = '';
        $inCode = false;
        $trailingPipe = false;
        for ($i = 0, $length = strlen($line); $i < $length; $i++) {
            $char = $line[$i];
            $trailingPipe = false;
            if ($char === '\\' && !$inCode && $i + 1 < $length && in_array($line[$i + 1], ['|', '\\'], true)) {
                // Consume slash pairs together, so an even run never escapes a delimiter.
                $cell .= $line[$i + 1] === '|' ? '|' : '\\\\';
                $i++;
                continue;
            }
            if ($char === '`') {
                if (($line[$i + 1] ?? '') === '`') { return null; }
                $inCode = !$inCode;
            }
            if ($char === '|' && !$inCode) {
                $cells[] = trim($cell);
                $cell = '';
                $trailingPipe = true;
            } else {
                $cell .= $char;
            }
        }
        if ($inCode) { return null; }
        $cells[] = trim($cell);
        if (str_starts_with($line, '|')) { array_shift($cells); }
        if ($trailingPipe) { array_pop($cells); }
        return $cells;
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
