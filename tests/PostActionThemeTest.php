<?php

declare(strict_types=1);

final class PostActionThemeTest
{
    public function testRetroThemePostActionsUseUiTypography(): void
    {
        foreach (['word97', 'chicago', 'midnight'] as $theme) {
            $css = $this->themeCss($theme);
            $scope = ':root[data-theme="' . $theme . '"]';

            assertStringContains($scope . ' .post-card-actions > a[href^="/compose/reply"]', $css);
            assertStringContains($scope . ' .thread-reaction-button', $css);
            assertStringContains('font-family: var(--ui-font);', $css);
            assertStringContains('text-decoration: none;', $css);
        }
    }

    public function testWord97AndMidnightPostActionTogglesUseUiTypography(): void
    {
        foreach (['word97', 'midnight'] as $theme) {
            $css = $this->themeCss($theme);
            $scope = ':root[data-theme="' . $theme . '"]';

            foreach ([
                ' .post-more-summary',
                ' .post-more-actions > summary',
                ' .post-analysis > summary',
                ' .retraction-details > summary',
            ] as $selector) {
                assertStringContains($scope . $selector, $css);
            }
        }
    }

    public function testMidnightLikeAndFlagBevelMatchesWord97(): void
    {
        $css = $this->themeCss('midnight');
        $selector = ':root[data-theme="midnight"] .post-card-actions button.thread-reaction-button'
            . ':not(.theme-swatch):not(.theme-menu__trigger):not(.theme-menu__option):not(.account-row)';

        assertStringContains($selector . ' {', $css);
        assertStringContains($selector . ':active {', $css);
        assertStringContains('padding: 0 0.6em;', $css);
        assertStringContains('border-color: #6a6a6a #000000 #000000 #6a6a6a;', $css);
        assertStringContains('border-color: #000000 #6a6a6a #6a6a6a #000000;', $css);
    }

    public function testOutboxStatusUsesUiFontInEveryTheme(): void
    {
        $css = file_get_contents(dirname(__DIR__) . '/public/assets/site.css');
        assertStringContains("[data-role=\"outbox-status\"] {\n  font-family: var(--ui-font);\n}", (string) $css);
    }

    public function testChicagoLikeAndFlagBevelStaysScopedAndPressable(): void
    {
        $css = $this->themeCss('chicago');
        $scope = ':root[data-theme="chicago"] .post-card-actions button.thread-reaction-button';

        assertStringContains($scope . '[data-tag="like"]', $css);
        assertStringContains($scope . '[data-tag="flag"]', $css);
        assertStringContains($scope . '[data-tag="like"]:active', $css);
        assertStringContains($scope . '[data-tag="flag"]:active', $css);
        assertStringContains('padding: 0 0.6em;', $css);
        assertStringContains('border-color: #ffffff #7b7b7b #7b7b7b #ffffff;', $css);
        assertStringContains('border-color: #7b7b7b #ffffff #ffffff #7b7b7b;', $css);
        assertStringNotContains(
            ':root[data-theme="chicago"] .post-card-actions button.thread-reaction-button:not(',
            $css
        );
    }

    private function themeCss(string $theme): string
    {
        $css = file_get_contents(dirname(__DIR__) . '/public/assets/theme-' . $theme . '.css');
        if ($css === false) {
            throw new RuntimeException('Unable to read ' . $theme . ' theme stylesheet.');
        }

        return $css;
    }
}
