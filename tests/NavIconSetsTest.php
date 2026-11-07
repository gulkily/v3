<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

/**
 * theme-nav-icons.css carries two icon sets keyed to the same links: pixel
 * art (word97, chicago, midnight) and smooth lines (gloss, engagify). A
 * destination added to one set and not the other leaves an empty icon slot,
 * because the sizing rules cover all five themes.
 */
final class NavIconSetsTest
{
    private const PIXEL = ':root:is([data-theme="word97"], [data-theme="chicago"], [data-theme="midnight"])';
    private const SMOOTH = ':root:is([data-theme="gloss"], [data-theme="engagify"])';

    public function testSmoothLineSetCoversEveryPixelArtDestination(): void
    {
        $pixel = $this->glyphSelectors(self::PIXEL);
        $smooth = $this->glyphSelectors(self::SMOOTH);

        assertTrue(count($pixel) > 40, 'Expected the pixel-art set to have glyph rules.');
        $missing = array_values(array_diff($pixel, $smooth));
        assertSame([], $missing, 'Smooth-line set lacks: ' . implode(', ', $missing));
        $extra = array_values(array_diff($smooth, $pixel));
        assertSame([], $extra, 'Smooth-line set has destinations the pixel-art set lacks: ' . implode(', ', $extra));
    }

    /**
     * Selectors, without the theme list, of rules in one set that draw a glyph.
     *
     * @return list<string>
     */
    private function glyphSelectors(string $themes): array
    {
        $css = (string) file_get_contents(__DIR__ . '/../public/assets/theme-nav-icons.css');
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
        preg_match_all('~([^{}]*)\{([^{}]*)\}~', $css, $rules, PREG_SET_ORDER);
        $selectors = [];
        foreach ($rules as [, $selectorList, $body]) {
            if (!str_contains($body, 'data:image/svg')) {
                continue;
            }
            foreach (explode(',', str_replace(', [data-theme', '|[data-theme', $selectorList)) as $selector) {
                $selector = trim(str_replace('|[data-theme', ', [data-theme', $selector));
                if (str_starts_with($selector, $themes . ' ')) {
                    $selectors[] = substr($selector, strlen($themes) + 1);
                }
            }
        }

        return array_values(array_unique($selectors));
    }
}
