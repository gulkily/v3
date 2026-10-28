<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Http\ApiRouteCatalog;
use ForumRewrite\Http\LlmsTxtRenderer;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry as Registry;

require __DIR__ . '/../autoload.php';

final class LlmsTxtRendererTest
{
    public function testEachInstanceFactFollowsItsFlag(): void
    {
        $cases = [
            Registry::UNICODE_AUTHORED_TEXT => ['Text: Unicode is accepted', 'Text: ASCII only.'],
            Registry::APPROVED_MEMBERS_ONLY => ['Posting: members only.', 'Posting: open to any new identity'],
            Registry::IMAGE_CONTENT_ENABLED => ['Images: enabled for approved identities.', 'Images: disabled.'],
            Registry::USERNAME_CHANGES_ENABLED => ['Username changes: enabled', 'Username changes: disabled.'],
            Registry::VERIFIED_ATTRIBUTION => ['Attribution: verified.', 'count as that identity\'s. Signing is still recommended.'],
        ];
        foreach ($cases as $flag => [$whenOn, $whenOff]) {
            $on = $this->render([$flag => true]);
            $off = $this->render([$flag => false]);
            assertStringContains($whenOn, $on);
            assertStringNotContains($whenOff, $on);
            assertStringContains($whenOff, $off);
            assertStringNotContains($whenOn, $off);
        }
    }

    public function testEmojiFactOnlyAppearsWithUnicode(): void
    {
        assertStringContains('including emoji.', $this->render([Registry::UNICODE_AUTHORED_TEXT => true, Registry::EMOJI_AUTHORED_TEXT => true]));
        assertStringContains('emoji are not.', $this->render([Registry::UNICODE_AUTHORED_TEXT => true, Registry::EMOJI_AUTHORED_TEXT => false]));
        assertStringNotContains('emoji', $this->render([Registry::UNICODE_AUTHORED_TEXT => false, Registry::EMOJI_AUTHORED_TEXT => true]));
    }

    public function testPolicyFlowAndLinks(): void
    {
        $text = $this->render([]);
        assertStringContains('Agents are welcome.', $text);
        assertStringContains('Label agent accounts as agent accounts', $text);
        assertStringNotContains('rate limit', strtolower($text));
        assertStringContains('6. GET /api/get_post?post_id=<post_id>', $text);
        assertStringContains('create_thread and create_reply are unsigned: without author_identity_id the post is anonymous', $text);
        assertStringContains('(/api/version)', $text);
        assertStringContains('(/api/)', $text);
        assertStringContains('(' . LlmsTxtRenderer::REFERENCE_PATH . ')', $text);
    }

    public function testListedRoutesComeFromTheCatalog(): void
    {
        $text = $this->render([]);
        preg_match_all('#^- (?:GET|POST)(?:/POST)? (/api/[a-z_/]+)#m', $text, $matches);
        assertTrue(count($matches[1]) >= 10, 'Expected the agent routes to be listed.');
        foreach ($matches[1] as $path) {
            assertTrue(in_array($path, ApiRouteCatalog::paths(), true), 'Not in catalog: ' . $path);
        }
        assertStringNotContains('/api/create_thread (', $text);
    }

    public function testReferenceNamesOnlyCatalogRoutesAndRenders(): void
    {
        $reference = (string) file_get_contents(dirname(__DIR__) . LlmsTxtRenderer::REFERENCE_PATH);
        preg_match_all('#/api/([a-z_]+)#', $reference, $paths);
        preg_match_all('#`((?:prepare|create|finalize|get|list|inspect|auth|authenticate)_[a-z_]+)`#', $reference, $names);
        $named = array_diff(array_unique(array_merge($paths[1], $names[1])), ['prepare_token']);
        assertTrue(count($named) >= 10, 'Expected the reference to name routes.');
        foreach ($named as $name) {
            assertTrue(in_array('/api/' . $name, ApiRouteCatalog::paths(), true), 'Reference names unknown route: ' . $name);
        }

        $root = sys_get_temp_dir() . '/agent-api-reference-' . bin2hex(random_bytes(6));
        $repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';
        try {
            (new ReadModelBuilder($repositoryRoot, $root . '.sqlite3', new CanonicalRecordRepository($repositoryRoot)))->rebuild();
            ob_start();
            (new Application(dirname(__DIR__), $repositoryRoot, $root . '.sqlite3'))->handle('GET', LlmsTxtRenderer::REFERENCE_PATH);
            $page = (string) ob_get_clean();
        } finally {
            @unlink($root . '.sqlite3');
        }
        assertTrue(preg_match('#<h1[^>]*>Agent API reference</h1>#', $page) === 1, 'Reference heading missing.');
        assertStringContains('Repository source: <code>docs/reference/agent_api.md</code>', $page);
    }

    public function testPagesAdvertiseLlmsTxtWithoutVisibleCopy(): void
    {
        $repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';
        $databasePath = sys_get_temp_dir() . '/llms-discovery-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            (new ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot)))->rebuild();
            foreach (['/', '/account/key/', '/forte/users/'] as $path) {
                ob_start();
                (new Application(dirname(__DIR__), $repositoryRoot, $databasePath))->handle('GET', $path);
                $page = (string) ob_get_clean();
                $head = strstr($page, '</head>', true);
                assertTrue($head !== false && str_contains($head, '<link rel="alternate" type="text/plain" href="/llms.txt"'), 'No llms.txt link in head of ' . $path);
                assertTrue(substr_count($page, 'href="/llms.txt"') === 1, 'Unexpected visible llms.txt link on ' . $path);
            }
        } finally {
            @unlink($databasePath);
        }
    }

    public function testMembersOnlyInstanceServesAgentDocsButNotContent(): void
    {
        $root = sys_get_temp_dir() . '/llms-members-only-' . bin2hex(random_bytes(6));
        $repositoryRoot = $root . '/repo';
        $databasePath = $root . '/read.sqlite3';
        mkdir($root, 0700);
        exec('cp -r ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1') . ' ' . escapeshellarg($repositoryRoot));
        @mkdir($repositoryRoot . '/records/instance', 0700, true);
        file_put_contents($repositoryRoot . '/records/instance/feature-flags.txt', "Schema: site-feature-flags-v1\n\nFORUM_APPROVED_MEMBERS_ONLY: true\n");
        try {
            (new ReadModelBuilder($repositoryRoot, $databasePath, new CanonicalRecordRepository($repositoryRoot)))->rebuild();
            $get = static function (string $path) use ($repositoryRoot, $databasePath): string {
                ob_start();
                (new Application(dirname(__DIR__), $repositoryRoot, $databasePath))->handle('GET', $path);
                return (string) ob_get_clean();
            };
            assertStringContains('Posting: members only.', $get('/llms.txt'));
            assertStringContains('Plain-text API. Agents start at /llms.txt.', $get('/api/'));
            assertStringNotContains('root-001', $get('/api/list_index'));
            assertStringContains('<link rel="alternate" type="text/plain" href="/llms.txt"', $get('/'));
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }

    /**
     * @param array<string, bool> $siteValues
     */
    private function render(array $siteValues): string
    {
        return (new LlmsTxtRenderer(new FeatureFlagEvaluator(siteValues: $siteValues)))->render();
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

if (!function_exists('assertStringNotContains')) {
    function assertStringNotContains(string $needle, string $haystack): void
    {
        if (str_contains($haystack, $needle)) {
            throw new RuntimeException('Failed asserting that output does not contain: ' . $needle);
        }
    }
}
