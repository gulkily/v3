<?php

declare(strict_types=1);

namespace ForumRewrite\View;

/**
 * A table with its own text-size controls on phones (site.css .zoom-table, table_zoom.js).
 *
 * Fit mode: the table opens at the largest text size at which every column still fits its
 * longest word (--table-min, in em), never above the page's own size, and scrolls sideways
 * once zoomed past that. Buttons: Smaller, Fit, Actual size, Larger; pinching on the table
 * zooms it too.
 *
 * Each kind of table remembers its own size, so post tables can stay at Actual size while
 * Tools tables stay small. layout.php restores every kind before paint.
 */
final class ZoomTable
{
    /** Kind => localStorage key. "post" keeps the key post tables used before kinds existed. */
    public const KINDS = [
        'post' => 'table-zoom',
        'tools' => 'table-zoom-tools',
    ];

    /** Horizontal padding plus border of one table cell, in em (site.css: 0.5em each side). */
    public const CELL_PADDING_EM = 1.1;

    /** Words longer than this many characters may break mid-word, so they count as this long. */
    private const WORD_CAP = 20;

    /**
     * Opens the wrapper, the buttons and the scrolling box; the caller writes the <table>
     * and then close(). $minimumEm comes from minimumEm() or columnMinimumEm().
     */
    public static function open(string $kind, float $minimumEm, string $label = 'Table'): string
    {
        if (!isset(self::KINDS[$kind])) {
            throw new \InvalidArgumentException('Unknown zoom table kind: ' . $kind);
        }
        return '<div class="zoom-table zoom-table--' . $kind . '" data-zoom-kind="' . $kind . '">'
            . ZoomButtons::group('div', 'zoom-table-buttons', 'data-table-zoom', ['smaller' => 'out', 'fit' => 'fit', 'actual' => 'page', 'larger' => 'in'])
            . '<div class="zoom-table-scroll" style="--table-min: ' . number_format(max(1.0, $minimumEm), 1, '.', '') . '"'
            . ' tabindex="0" role="region" aria-label="' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
    }

    public static function close(): string
    {
        return '</div></div>';
    }

    /**
     * The table's narrowest width in em, from its rows of plain-text cells: each column's
     * longest word plus cell padding. The first $headerRows rows and $headerColumns columns
     * are header cells (bold, so wider).
     *
     * @param list<list<string>> $rows
     */
    public static function minimumEm(array $rows, int $headerRows = 1, int $headerColumns = 0): float
    {
        $longest = [];
        foreach (array_values($rows) as $index => $row) {
            foreach (array_values($row) as $column => $cell) {
                $header = $index < $headerRows || $column < $headerColumns;
                $longest[$column] = max($longest[$column] ?? 0.0, self::longestWordEm($cell, $header));
            }
        }
        return self::columnMinimumEm($longest);
    }

    /** @param list<float> $longestWordEm each column's longest word, in em */
    public static function columnMinimumEm(array $longestWordEm): float
    {
        $minimum = 0.0;
        foreach ($longestWordEm as $width) {
            $minimum += $width + self::CELL_PADDING_EM;
        }
        return $minimum;
    }

    /** Estimated width of the longest word in $html, in em; header text is bold and wider. */
    public static function longestWordEm(string $html, bool $header): float
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $longest = 0;
        foreach (preg_split('/[\s\p{Z}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $longest = max($longest, min(self::WORD_CAP, mb_strlen($word, 'UTF-8')));
        }
        return $longest * ($header ? 0.6 : 0.55);
    }
}
