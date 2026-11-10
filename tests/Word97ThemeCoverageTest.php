<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

/**
 * The Word 97 and Midnight themes style every component the app shell, list
 * board, Users, person and Account pages render; see
 * docs/plans/word97_theme_repair_checklist.md.
 * A new shared component that only inherits the theme's variables falls back
 * to grey-on-grey panels, rounded corners and pill chips.
 */
final class Word97ThemeCoverageTest
{
    public function testThemesCoverAppShellListBoardAndPeoplePages(): void
    {
        foreach (['word97', 'midnight'] as $theme) {
            $this->assertThemeCoverage($theme);
        }
    }

    private function assertThemeCoverage(string $theme): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../public/assets/theme-' . $theme . '.css');
        $scope = ':root[data-theme="' . $theme . '"]';

        $selectors = [
            // App shell header: title bar, toolbar row, ruler, buttons, account menu.
            '.eyebrow .site-brand',
            '.site-header--app',
            '.site-header--app .eyebrow',
            '.site-header--app::after',
            '[data-shell="app"] .new-post-button',
            '.account-menu__trigger',
            '.account-menu__panel',
            // List board.
            '.board-toolbar',
            '.stack > .board-filter',
            '.board-sort__menu',
            '.thread-rows',
            '.thread-row + .thread-row',
            '.section-label',
            '.section-label + .thread-rows',
            '.chip',
            // Users and person pages.
            '.account-card',
            '.account-group',
            '.account-row--link::after',
            '.account-avatar',
            '.account-key-header',
            'select',
        ];
        foreach ($selectors as $selector) {
            assertStringContains($scope . ' ' . $selector, $css);
        }

        // The phone tab bar is themed too.
        assertStringContains('@media (max-width: 639px)', $css);
        assertStringContains($scope . ' [data-shell="app"] .site-header--app .site-header-actions', $css);
    }
}
