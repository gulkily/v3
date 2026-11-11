<?php

declare(strict_types=1);

use ForumRewrite\View\ZoomButtons;

require __DIR__ . '/../autoload.php';

final class ZoomButtonsTest
{
    public function testGroupRendersSmallerFitLargerWithSharedLabels(): void
    {
        $html = ZoomButtons::group('div', 'post-table-zoom', 'data-table-zoom', ['smaller' => 'out', 'fit' => 'fit', 'larger' => 'in']);
        zoomAssertContains('<div class="zoom-buttons post-table-zoom" role="group" aria-label="Text size">', $html);
        zoomAssertContains('<button type="button" class="zoom-button" data-table-zoom="out" aria-label="Smaller" title="Smaller"><svg', $html);
        zoomAssertContains('<button type="button" class="zoom-button" data-table-zoom="fit" title="Fit to screen">Fit</button>', $html);
        zoomAssertContains('<button type="button" class="zoom-button" data-table-zoom="in" aria-label="Larger" title="Larger"><svg', $html);
        if (strpos($html, '"out"') > strpos($html, '"fit"') || strpos($html, '"fit"') > strpos($html, '"in"')) {
            throw new RuntimeException('Expected Smaller, Fit, Larger order: ' . $html);
        }
        zoomAssertEndsWith('</div>', $html);
    }

    public function testGroupWithoutFitHasOnlyTwoButtons(): void
    {
        $html = ZoomButtons::group('span', 'sqlite-dialog-zoom', 'data-query-zoom', ['smaller' => '-1', 'larger' => '1']);
        zoomAssertContains('<span class="zoom-buttons sqlite-dialog-zoom" role="group" aria-label="Text size">', $html);
        if (substr_count($html, '<button type="button" class="zoom-button"') !== 2 || str_contains($html, '>Fit<')) {
            throw new RuntimeException('Expected exactly Smaller and Larger: ' . $html);
        }
        // Larger's icon has the extra vertical bar of the plus sign; Smaller's does not.
        $larger = substr($html, strpos($html, 'data-query-zoom="1"'));
        zoomAssertContains('<line x1="7" y1="5" x2="7" y2="9"', $larger);
        $smaller = substr($html, 0, strpos($html, 'data-query-zoom="1"'));
        if (str_contains($smaller, '<line x1="7" y1="5" x2="7" y2="9"')) {
            throw new RuntimeException('Smaller button should not have the plus bar: ' . $smaller);
        }
    }

    public function testSingleButtonCarriesExtraClassForToolbars(): void
    {
        $html = ZoomButtons::button('larger', 'data-paned-zoom', '1', 'paned-toolbar-btn paned-toolbar-zoom');
        zoomAssertContains('<button type="button" class="zoom-button paned-toolbar-btn paned-toolbar-zoom" data-paned-zoom="1" aria-label="Larger" title="Larger">', $html);
    }

    public function testRejectsUnsafeAttributeNamesAndUnknownDirections(): void
    {
        foreach ([
            static fn () => ZoomButtons::button('bigger', 'data-x', '1'),
            static fn () => ZoomButtons::button('smaller', 'onclick', '1'),
            static fn () => ZoomButtons::button('smaller', 'data-x" onclick="y', '1'),
            static fn () => ZoomButtons::group('section', '', 'data-x', ['smaller' => '-1', 'larger' => '1']),
        ] as $call) {
            try {
                $call();
            } catch (InvalidArgumentException) {
                continue;
            }
            throw new RuntimeException('Expected InvalidArgumentException');
        }
        zoomAssertContains('data-x="&quot;&gt;"', ZoomButtons::button('smaller', 'data-x', '">'));
    }

    public function testNoTemplateOrRendererKeepsItsOwnCopyOfTheZoomIcons(): void
    {
        $root = dirname(__DIR__);
        $files = array_merge(
            glob($root . '/templates/*.php') ?: [],
            glob($root . '/templates/*/*.php') ?: [],
            [$root . '/src/ForumRewrite/Docs/PublicMarkdownRenderer.php'],
        );
        foreach ($files as $file) {
            if (str_contains((string) file_get_contents($file), '<circle cx="7" cy="7" r="4.5"')) {
                throw new RuntimeException('Use ForumRewrite\View\ZoomButtons instead of copying the zoom icon in ' . $file);
            }
        }
    }
}

function zoomAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output contains: ' . $needle . "\nOutput: " . $haystack);
    }
}

function zoomAssertEndsWith(string $suffix, string $haystack): void
{
    if (!str_ends_with($haystack, $suffix)) {
        throw new RuntimeException('Failed asserting that output ends with: ' . $suffix);
    }
}
