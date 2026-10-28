<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Http\ApiRouteCatalog;
use ForumRewrite\ReadModel\ReadModelBuilder;

require __DIR__ . '/../autoload.php';

final class ApiRouteCatalogTest
{
    public function testCatalogMatchesRouterApiPaths(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../src/ForumRewrite/Application.php');
        preg_match_all("#'(/api/[a-z_/]*)'#", $source, $matches);
        $routed = array_values(array_unique($matches[1]));
        // Prefix literals (str_starts_with checks) end in '/' or '_'.
        $prefixes = array_values(array_filter($routed, static fn (string $p): bool => $p !== '/api/' && preg_match('#[/_]$#', $p) === 1));
        $exact = array_values(array_diff($routed, $prefixes));
        $catalog = ApiRouteCatalog::paths();

        $unlisted = array_values(array_diff($exact, $catalog));
        assertSame([], $unlisted);
        foreach ($prefixes as $prefix) {
            assertTrue(array_filter($catalog, static fn (string $p): bool => str_starts_with($p, $prefix)) !== [], 'No catalog route under ' . $prefix);
        }
        $unrouted = array_values(array_filter(
            array_diff($catalog, $exact),
            static fn (string $p): bool => !str_starts_with($p, '/api/private_messages/history_sync/'),
        ));
        assertSame([], $unrouted);
    }

    public function testEntriesUseKnownAccessAndFormat(): void
    {
        foreach (ApiRouteCatalog::entries() as $entry) {
            assertTrue(isset(ApiRouteCatalog::ACCESS_LEGEND[$entry['access']]), 'Unknown access for ' . $entry['path']);
            assertTrue(in_array($entry['format'], ['text', 'json'], true), 'Unknown format for ' . $entry['path']);
            assertTrue(preg_match('#^(GET|POST)(\|(GET|POST))?$#', $entry['method']) === 1, 'Bad method for ' . $entry['path']);
        }
    }

    public function testIndexMarksAccessAndLinksAgentGuide(): void
    {
        $repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';
        $databasePath = sys_get_temp_dir() . '/api-route-catalog-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            (new ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot)))->rebuild();
            ob_start();
            (new Application(dirname(__DIR__), $repositoryRoot, $databasePath))->handle('GET', '/api/');
            $index = (string) ob_get_clean();
        } finally {
            @unlink($databasePath);
        }

        assertStringContains('/llms.txt', $index);
        assertStringContains('POST /api/create_thread [anonymous, text] Unsigned thread; anonymous unless author_identity_id is sent.', $index);
        assertStringContains('POST /api/create_prepared_post [signed, json]', $index);
        assertStringContains('GET /api/version [public, text]', $index);
        assertStringContains('Private conversation lists require an approved authenticated identity', $index);
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting that values are identical. Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

if (!function_exists('assertTrue')) {
    function assertTrue(bool $condition, string $message = 'Failed asserting that condition is true.'): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output contains: ' . $needle);
        }
    }
}
