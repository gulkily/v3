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
        $lines = self::withoutProseComments(preg_split('/\r\n|\r|\n/', $markdown) ?: []);
        $html = [];
        $paragraph = [];
        $inCodeBlock = false;
        $codeLines = [];
        $codeLanguage = '';
        $headings = [];
        $tableLines = [];
        $tableStandalone = true;
        $skipThrough = -1;

        $flushParagraph = static function () use (&$html, &$paragraph, $sourcePath, $projectRoot): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . self::inline(implode("\n", $paragraph), $sourcePath, $projectRoot) . '</p>';
                $paragraph = [];
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

            if (self::listStart($line) && preg_match('/^\s*(?:[-*_]\s*){3,}$/', $line) !== 1) {
                [$block, $last] = self::listBlock($lines, $lineNumber);
                $flushParagraph(); $flushTable();
                $html[] = self::structuredList($block, $sourcePath, $projectRoot)
                    ?? '<pre class="docs-list-source" tabindex="0" role="region" aria-label="List source"><code>'
                        . self::escape(implode("\n", $block)) . '</code></pre>';
                $skipThrough = $last;
                continue;
            }
            if (preg_match('/^\s*<img\b[^>]*>\s*$/i', $line) === 1
                && preg_match('/\balt\s*=\s*(["\'])(.*?)\1/is', $line, $alt) === 1) {
                $flushParagraph(); $flushTable();
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
                    $tableStandalone = !$blockStart;
                }
                $tableStandalone = $tableStandalone && preg_match('/^(?: {2}|\t)/', $line) !== 1;
                $flushParagraph();
                $tableLines[] = $line;
                continue;
            }
            $flushTable();
            if (trim($line) === '') {
                $flushParagraph();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $matches) === 1) {
                $flushParagraph();
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
                $html[] = '<hr>';
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $line, $matches) === 1) {
                $flushParagraph();
                $html[] = '<blockquote><p>' . self::inline($matches[1], $sourcePath, $projectRoot) . '</p></blockquote>';
                continue;
            }

            $paragraph[] = $line;
        }

        $flushParagraph();
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
            'number' => $ordered ? substr($m[2], 0, -1) : null, 'padding' => strlen($m[3]), 'text' => $m[4]];
    }

    private static function listStart(string $line): bool
    {
        return preg_match('/^[ \t]*(?:[-+*]|[0-9]+[.)])[ \t]+/', $line) === 1;
    }

    private static function fence(string $line): ?array
    {
        if (preg_match('/^(?:[ \t]*(?:[-+*]|[0-9]+[.)])[ \t]+)?[ \t]*(`{3,}|~{3,})(.*)$/', $line, $m) !== 1) { return null; }
        return ['char' => $m[1][0], 'length' => strlen($m[1]), 'tail' => trim($m[2])];
    }

    /** Comments in prose stay private; code bytes, including example comments, remain verbatim. */
    private static function withoutProseComments(array $lines): array
    {
        $inComment = false; $fence = null;
        foreach ($lines as &$line) {
            $marker = self::fence($line);
            if ($fence !== null) {
                if ($marker !== null && $marker['char'] === $fence['char'] && $marker['length'] >= $fence['length'] && $marker['tail'] === '' && preg_match('/^[ \t]*[`~]+[ \t]*$/', $line) === 1) { $fence = null; }
                continue;
            }
            if (!$inComment && $marker !== null) { $fence = $marker; continue; }
            $clean = ''; $cursor = 0; $length = strlen($line);
            while ($cursor < $length) {
                if ($inComment) {
                    $end = strpos($line, '-->', $cursor);
                    if ($end === false) { break; }
                    $cursor = $end + 3; $inComment = false;
                } else {
                    $start = strpos($line, '<!--', $cursor);
                    if ($start === false) { $clean .= substr($line, $cursor); break; }
                    $clean .= substr($line, $cursor, $start - $cursor);
                    $cursor = $start + 4; $inComment = true;
                }
            }
            $line = $clean;
            if (!$inComment && ($marker = self::fence($line)) !== null) { $fence = $marker; }
        }
        unset($line);
        return $lines;
    }

    /** Fenced examples in unsupported lists belong to the source block even across dedents. */
    private static function listBlock(array $lines, int $start): array
    {
        $last = $start; $fence = null;
        for ($i = $start, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i]; $marker = self::fence($line);
            if ($fence !== null) {
                $last = $i;
                if ($marker !== null && $marker['char'] === $fence['char'] && $marker['length'] >= $fence['length'] && $marker['tail'] === '' && preg_match('/^[ \t]*[`~]+[ \t]*$/', $line) === 1) { $fence = null; }
                continue;
            }
            if ($i > $start) {
                if (trim($line) === '') {
                    $next = $i + 1;
                    while ($next < $count && trim($lines[$next]) === '') { $next++; }
                    if ($next === $count || (!self::listStart($lines[$next]) && preg_match('/^[ \t]+\S/', $lines[$next]) !== 1)) { break; }
                } elseif (!self::listStart($line) && preg_match('/^[ \t]+\S/', $line) !== 1) { break; }
                if (preg_match('/^(?:[-*_]\s*){3,}$/', $line) === 1) { break; }
            }
            $last = $i;
            if ($marker !== null) { $fence = $marker; }
        }
        return [array_slice($lines, $start, $last - $start + 1), $last];
    }

    private static function structuredList(array $lines, ?string $sourcePath, string $projectRoot): ?string
    {
        $index = 0;
        $html = self::listLevel($lines, $index, 0, 1, $sourcePath, $projectRoot);
        return $index === count($lines) ? $html : null;
    }

    private static function listLevel(array $lines, int &$index, int $indent, int $depth, ?string $sourcePath, string $projectRoot): ?string
    {
        if ($depth > 8) { return null; }
        $html = ''; $kind = null; $tag = null;
        while ($index < count($lines)) {
            $item = self::listItem($lines[$index]);
            if ($item === null || $item['indent'] !== $indent) { break; }
            if (strlen($item['number'] ?? '') > 9 || $item['padding'] > 4) { return null; }
            if ($kind !== $item['kind']) {
                if ($tag !== null) { $html .= '</' . $tag . '>'; }
                $tag = $item['tag']; $kind = $item['kind'];
                $html .= '<' . $tag . ($tag === 'ol' ? ' start="' . (int) $item['number'] . '"' : '') . '>';
            }
            $html .= '<li' . ($tag === 'ol' ? ' value="' . (int) $item['number'] . '"' : '') . '>';
            $paragraph = []; $childIndent = null;
            $flush = static function () use (&$html, &$paragraph, $sourcePath, $projectRoot): void {
                if ($paragraph !== []) { $html .= '<p>' . self::inline(implode("\n", $paragraph), $sourcePath, $projectRoot) . '</p>'; $paragraph = []; }
            };
            $line = $item['text'];
            $index++;
            while (true) {
                if (str_contains($line, "\t") || preg_match('/^(?:[|>#]|`{3}|~{3})/', ltrim($line)) === 1) { return null; }
                if (str_contains($line, '|') && isset($lines[$index]) && preg_match('/^[ \t|:\-]+$/', $lines[$index]) === 1 && str_contains($lines[$index], '|')) { return null; }
                if (trim($line) !== '') { $paragraph[] = $line; } else { $flush(); }
                if ($index >= count($lines)) { break; }
                $line = $lines[$index];
                $next = self::listItem($line);
                if ($next !== null && $next['indent'] <= $indent) { break; }
                if ($next !== null) {
                    if ($next['indent'] < $item['column'] || ($childIndent !== null && $childIndent !== $next['indent'])) { return null; }
                    $childIndent = $next['indent']; $flush();
                    $child = self::listLevel($lines, $index, $childIndent, $depth + 1, $sourcePath, $projectRoot);
                    if ($child === null) { return null; }
                    $html .= $child;
                    // Re-enter without manufacturing a paragraph after the child list.
                    $line = '';
                    continue;
                }
                if (trim($line) !== '') {
                    $spaces = strlen($line) - strlen(ltrim($line, ' '));
                    if ($spaces <= $indent) { break; }
                    if ($spaces !== $item['column']) { return null; }
                    $line = substr($line, $item['column']);
                }
                $index++;
            }
            $flush(); $html .= '</li>';
        }
        return $tag === null ? null : $html . '</' . $tag . '>';
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
