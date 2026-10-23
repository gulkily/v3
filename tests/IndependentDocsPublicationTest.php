<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/DocsTestProject.php';

use ForumRewrite\Docs\DocsReleaseLocator;
use ForumRewrite\Docs\DocsReleasePublisher;

final class IndependentDocsPublicationTest
{
    public function testPublicationAndRepeatDoNotTouchForumStateOrFollowRecordVersions(): void
    {
        [$w, $project, $repo, $static] = $this->workspace();
        $publisher = new DocsReleasePublisher($project, $repo, $static);
        $locator = new DocsReleaseLocator($static);
        $release = $publisher->publish();
        $active = $locator->active();
        assertSame($release, $active['root']);
        assertSame(2, $active['documents']);
        assertStringContains('/api/docs-version', file_get_contents($release . '/docs/index.html'));
        assertStringContains('forum_pending_docs_version', file_get_contents($release . '/docs/index.html'));
        assertTrue(is_file($release . '/docs/_search.json'));
        assertSame(file_get_contents($project . '/docs/guide.md'), file_get_contents($release . '/docs/_download/docs/guide.md.bin'));
        $w->put($repo . '/records/posts/later.txt', $w->post('later'));
        $w->git($repo);
        $progress = [];
        $next = $publisher->publish(static function ($line) use (&$progress): void { $progress[] = $line; });
        assertStringContains('Reusing documentation (2 documents).', implode("\n", $progress));
        assertSame($active['version'], $locator->active()['version']);
        assertSame(file_get_contents($release . '/docs/index.html'), file_get_contents($next . '/docs/index.html'));
        assertSame(file_get_contents($release . '/service_worker.js'), file_get_contents($next . '/service_worker.js'));
        $this->assertForumUnchanged($w, $static);
    }

    public function testCliReadableJsonHelpErrorsAndRecovery(): void
    {
        [$w, $project, $repo, $static] = $this->workspace();
        $options = ['--repository-root=' . $repo, '--static-html-root=' . $static];
        [$code, $help] = $w->command([$project . '/v3', 'docs', 'publish', '--help']);
        assertSame(0, $code, $help);
        assertStringContains('initial static docs setup', $help);
        [$code, $out] = $w->command([$project . '/v3', 'docs', 'publish', '--json', ...$options]);
        assertSame(0, $code, $out);
        $result = json_decode($out, true, flags: JSON_THROW_ON_ERROR);
        assertSame('complete', $result['status']);
        assertSame(2, $result['documents']);
        $locator = new DocsReleaseLocator($static);
        $before = $locator->active();
        $template = $project . '/templates/pages/platform_docs_document.php';
        $original = file_get_contents($template);
        file_put_contents($template, '<?php throw new RuntimeException("Injected docs failure");');
        [$code, $out] = $w->command([$project . '/v3', 'docs', 'publish', '--json', ...$options]);
        assertSame(1, $code, $out);
        $failure = json_decode($out, true, flags: JSON_THROW_ON_ERROR);
        assertSame('error', $failure['status']);
        assertStringContains('retry ./v3 docs publish', $failure['next_action']);
        assertSame($before, $locator->active());
        assertSame([], glob($locator->root() . '/.candidate-*'));
        file_put_contents($template, $original);
        $w->put($project . '/docs/guide.md', '# Changed guide');
        [$code, $out] = $w->command([$project . '/v3', 'docs', 'publish', ...$options]);
        assertSame(0, $code, $out);
        assertStringContains('Documentation published:', $out);
        assertTrue($before['version'] !== $locator->active()['version']);
        assertStringContains('Changed guide', file_get_contents($locator->active()['root'] . '/docs/guide.md.html'));
        [$code, $out] = $w->command([$project . '/v3', 'docs', 'publish', '--json', '--bad-option']);
        assertSame(1, $code, $out);
        assertSame('error', json_decode($out, true, flags: JSON_THROW_ON_ERROR)['status']);
        $this->assertForumUnchanged($w, $static);
    }

    public function testSourceChangesAndUnsafeStorageDoNotActivatePartialDocs(): void
    {
        [$w, $project, $repo, $static] = $this->workspace();
        $publisher = new DocsReleasePublisher($project, $repo, $static);
        $publisher->publish();
        $locator = new DocsReleaseLocator($static);
        $before = $locator->active();
        $w->put($project . '/docs/guide.md', '# Changed');
        $changed = false;
        try {
            $publisher->publish(function ($line) use ($w, $project, &$changed): void {
                if (!$changed) {
                    $changed = true;
                    $w->put($project . '/docs/added.md', '# Added while rendering');
                }
            });
            throw new LogicException('Expected failure');
        } catch (RuntimeException) {
            assertTrue($changed);
        }
        assertSame($before, $locator->active());
        assertSame([], glob($locator->root() . '/.candidate-*'));
        $unsafe = $w->root . '/unsafe';
        symlink($w->root, $unsafe . '-docs');
        try {
            (new DocsReleasePublisher($project, $repo, $unsafe))->publish();
            throw new LogicException('Expected unsafe-storage failure');
        } catch (RuntimeException $error) {
            assertStringContains('symlinks', $error->getMessage());
        }
        $this->assertForumUnchanged($w, $static);
    }

    public function testPrivateProfilePublishesOnlyAnonymousDocsWithoutADatabase(): void
    {
        [$w, $project, $repo, $static] = $this->workspace();
        $old = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
        try {
            $release = (new DocsReleasePublisher($project, $repo, $static))->publish();
            assertStringContains('Project guide', file_get_contents($release . '/docs/_project/README.md.html'));
            assertTrue(!is_dir($release . '/posts'));
            $this->assertForumUnchanged($w, $static);
        } finally {
            putenv($old === false ? 'FORUM_APPROVED_MEMBERS_ONLY' : 'FORUM_APPROVED_MEMBERS_ONLY=' . $old);
        }
    }

    private function workspace(): array
    {
        $w = new ImportTestWorkspace();
        $project = DocsTestProject::create($w);
        $repo = $w->repository('records', true);
        $w->git($repo);
        $static = $w->root . '/static';
        $w->put($static . '/releases/forum/index.html', 'forum release');
        symlink('releases/forum', $static . '/current');
        $w->put($static . '/offline/snapshot.sqlite3', 'offline sentinel');
        $w->put($w->root . '/forum.sqlite3', 'read model sentinel');
        return [$w, $project, $repo, $static];
    }

    private function assertForumUnchanged(ImportTestWorkspace $w, string $static): void
    {
        assertSame('forum release', file_get_contents($static . '/current/index.html'));
        assertSame('releases/forum', readlink($static . '/current'));
        assertSame('offline sentinel', file_get_contents($static . '/offline/snapshot.sqlite3'));
        assertSame('read model sentinel', file_get_contents($w->root . '/forum.sqlite3'));
        assertTrue(!is_dir($w->root . '/application/state/cache'));
    }
}
