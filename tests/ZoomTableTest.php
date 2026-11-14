<?php

declare(strict_types=1);

use ForumRewrite\View\ZoomTable;

require __DIR__ . '/../autoload.php';

final class ZoomTableTest
{
    public function testOpenWrapsButtonsAndScrollerForAKind(): void
    {
        $html = ZoomTable::open('tools', 31.24, 'Visitor statistics table') . '<table></table>' . ZoomTable::close();
        zoomTableAssertContains('<div class="zoom-table zoom-table--tools zoom-table--fit" data-zoom-kind="tools"><div class="zoom-buttons zoom-table-buttons" role="group" aria-label="Text size">', $html);
        foreach (['out', 'fit', 'page', 'in'] as $action) {
            zoomTableAssertContains('data-table-zoom="' . $action . '"', $html);
        }
        zoomTableAssertContains('<div class="zoom-table-scroll" style="--table-min: 31.2" tabindex="0" role="region" aria-label="Visitor statistics table"><table></table></div></div>', $html);
    }

    public function testTextModeHasNoFitButtonAndNoMinimumWidth(): void
    {
        $html = ZoomTable::open('calendar', 0, 'November 2026', 'text');
        zoomTableAssertContains('<div class="zoom-table zoom-table--calendar zoom-table--text" data-zoom-kind="calendar">', $html);
        foreach (['out', 'page', 'in'] as $action) {
            zoomTableAssertContains('data-table-zoom="' . $action . '"', $html);
        }
        if (str_contains($html, 'data-table-zoom="fit"') || str_contains($html, '--table-min')) {
            throw new RuntimeException('Text mode should have no Fit button and no --table-min: ' . $html);
        }
        zoomTableAssertContains('<div class="zoom-table-scroll" tabindex="0" role="region" aria-label="November 2026">', $html);
        zoomTableAssertContains('zoom-table--fit', ZoomTable::open('post', 10));
        try {
            ZoomTable::open('post', 10, 'Table', 'scale');
            throw new RuntimeException('Expected InvalidArgumentException for an unknown mode');
        } catch (InvalidArgumentException) {
        }
    }

    public function testRejectsUnknownKindsAndEscapesTheLabel(): void
    {
        try {
            ZoomTable::open('agenda"x', 10);
            throw new RuntimeException('Expected InvalidArgumentException for an unknown kind');
        } catch (InvalidArgumentException) {
        }
        zoomTableAssertContains('aria-label="a &quot;b&quot; &lt;c&gt;"', ZoomTable::open('post', 10, 'a "b" <c>'));
    }

    public function testMinimumWidthCountsEachColumnsLongestWordWithBoldHeaders(): void
    {
        $rows = [
            ['UTC day', 'Estimated clients'],
            ['2026-11-13', '1,204'],
        ];
        // Column 1: "2026-11-13" (10 chars) beats bold "UTC". Column 2: bold "Estimated" (9).
        zoomTableAssertSame(round(10 * 0.55 + 1.1 + 9 * 0.6 + 1.1, 3), round(ZoomTable::minimumEm($rows), 3));
        // As a row-header column, the date is bold too.
        zoomTableAssertSame(round(10 * 0.6 + 1.1 + 9 * 0.6 + 1.1, 3), round(ZoomTable::minimumEm($rows, 1, 1), 3));
        // Long tokens count as at most 20 characters.
        zoomTableAssertSame(round(20 * 0.55 + 1.1, 3), round(ZoomTable::minimumEm([['x'], [str_repeat('y', 60)]]), 3));
    }

    public function testEveryKindIsRestoredBeforePaintAndStyled(): void
    {
        $root = dirname(__DIR__);
        $layout = (string) file_get_contents($root . '/templates/layout.php');
        zoomTableAssertContains('\ForumRewrite\View\ZoomTable::KINDS', $layout);
        $css = (string) file_get_contents($root . '/public/assets/site.css');
        $script = (string) file_get_contents($root . '/public/assets/table_zoom.js');
        foreach (ZoomTable::KINDS as $kind => $key) {
            zoomTableAssertContains('.zoom-table--' . $kind . ' { --table-zoom: var(--table-zoom-' . $kind . ', 1); }', $css);
            zoomTableAssertContains(':root[data-table-size-' . $kind . '="page"] .zoom-table--' . $kind . ' table', $css);
            zoomTableAssertContains($kind . ': "' . $key . '"', $script);
        }
    }

    public function testToolsTablesAndTheCalendarUseTheComponent(): void
    {
        $root = dirname(__DIR__);
        foreach (['visitor_statistics.php' => 'tools', 'llm_exchanges.php' => 'tools', 'calendar.php' => 'calendar'] as $page => $kind) {
            $template = (string) file_get_contents($root . '/templates/pages/' . $page);
            zoomTableAssertContains("ZoomTable::open('" . $kind . "', ", $template);
            zoomTableAssertContains('ZoomTable::close()', $template);
        }
    }
}

function zoomTableAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output contains: ' . $needle . "\nOutput: " . $haystack);
    }
}

function zoomTableAssertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
