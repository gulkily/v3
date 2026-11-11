<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\View\ThemeCatalog;
use ForumRewrite\View\ThemeRegistry;

final class ThemeCatalogPageTest
{
    public function testPreviewPropertiesComeFromTheMenuOptionRuleOnly(): void
    {
        $css = <<<'CSS'
/* --ink: #commented; */
:root[data-theme="demo"],
.theme-menu__option[data-theme-option="demo"] {
  color-scheme: dark;
  --page-bg: #101010;
  --ink:
    #fafafa;
}
:root[data-theme="demo"] .card { --page-bg: #ignored; }
.theme-menu__option[data-theme-option="demo"] { --ink-soft: #999; --bad: x}y; }
.theme-menu__option[data-theme-option="demolition"] { --page-bg: #other; }
CSS;

        themeCatalogAssertSame(
            ['--page-bg' => '#101010', '--ink' => '#fafafa'],
            array_intersect_key(ThemeCatalog::previewProperties($css, 'demo'), ['--page-bg' => 1, '--ink' => 1]),
        );
        themeCatalogAssertSame('#999', ThemeCatalog::previewProperties($css, 'demo')['--ink-soft'] ?? null);
        themeCatalogAssertSame([], ThemeCatalog::previewProperties($css, 'missing'));
    }

    public function testEveryPermittedThemeHasPreviewColours(): void
    {
        $entries = ThemeCatalog::entries(dirname(__DIR__));
        $expected = array_values(array_filter(
            array_column(ThemeRegistry::permitted(\ForumRewrite\SiteProfileRegistry::active()['permittedThemes']), 'name'),
            static fn (string $name): bool => $name !== 'auto',
        ));

        themeCatalogAssertSame($expected, array_column($entries, 'name'));
        foreach ($entries as $entry) {
            foreach (['--page-bg', '--ink', '--panel', '--line'] as $property) {
                themeCatalogAssertSame(true, isset($entry['properties'][$property]), $entry['name'] . ' ' . $property);
            }
        }
    }

    public function testPageListsThemesWithUseButtonsAndCurrentMarker(): void
    {
        $html = $this->render('/themes');

        foreach (ThemeCatalog::entries(dirname(__DIR__)) as $entry) {
            themeCatalogAssertContains('data-theme-card="' . $entry['name'] . '"', $html);
            themeCatalogAssertContains('data-theme-catalog-select="' . $entry['name'] . '"', $html);
            themeCatalogAssertContains('theme: ' . $entry['name'], $html);
            themeCatalogAssertContains(':root[data-resolved-theme="' . $entry['name'] . '"] [data-theme-card="' . $entry['name'] . '"]', $html);
        }
        themeCatalogAssertContains('/assets/theme_catalog.', $html);
        themeCatalogAssertContains('/assets/themes', $html);
        themeCatalogAssertContains('data-theme-card="vapor"', $this->render('/themes/'));
    }

    public function testThemeToggleExposesSelectionForTheCatalog(): void
    {
        themeCatalogAssertContains('window.forumSelectTheme = function (theme)', (string) file_get_contents(dirname(__DIR__) . '/public/assets/theme_toggle.js'));
    }

    private function render(string $path): string
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-theme-catalog-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/fixtures/parity_minimal_v1', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $target = $root . '/' . $items->getSubPathName();
            $item->isDir() ? (is_dir($target) || mkdir($target, 0777, true)) : copy($item->getPathname(), $target);
        }
        foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"', 'git add .', 'git -c commit.gpgsign=false commit -m init'] as $command) {
            exec('cd ' . escapeshellarg($root) . ' && ' . $command . ' 2>&1', $output, $status);
            if ($status !== 0) {
                throw new RuntimeException('Command failed: ' . $command);
            }
        }
        $artifacts = $root . '-public';
        mkdir($artifacts, 0777, true);
        $application = new Application(dirname(__DIR__), $root, $root . '.sqlite3', $artifacts);
        $_GET = [];
        http_response_code(200);
        ob_start();
        try {
            $application->handle('GET', $path);
        } finally {
            $html = (string) ob_get_clean();
        }
        themeCatalogAssertSame(200, http_response_code());

        return $html;
    }
}

function themeCatalogAssertSame(mixed $expected, mixed $actual, string $context = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Failed asserting identical values' . ($context !== '' ? ' (' . $context . ')' : '') . '. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function themeCatalogAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output contains: ' . $needle);
    }
}
