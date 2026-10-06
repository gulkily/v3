<?php

declare(strict_types=1);

use ForumRewrite\PresentationSlotRegistry;
use ForumRewrite\ProfilePresentationContent;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Application;

require __DIR__ . '/../autoload.php';

final class ProfilePresentationContentTest
{
    public function testKnownProfileSlotsSelectBoundedContent(): void
    {
        $profiles = SiteProfileRegistry::all();

        $zenmemes = ProfilePresentationContent::about($profiles['zenmemes']);
        $chouse = ProfilePresentationContent::about($profiles['chouse']);
        $qdb = ProfilePresentationContent::about($profiles['qdb']);

        assertSame('About zenmemes', $zenmemes['title']);
        assertSame(false, $zenmemes['showHackableSection']);
        assertSame('About chouse', $chouse['title']);
        assertSame(true, $chouse['showHackableSection']);
        assertSame('About qdb', $qdb['title']);
        assertSame(false, $qdb['showHackableSection']);
        assertSame('Meme Oven Is Busy', ProfilePresentationContent::busy($profiles['zenmemes'])['heading']);
        assertSame('Temporarily Busy', ProfilePresentationContent::busy($profiles['chouse'])['heading']);
        assertSame('qdb Platform Docs', ProfilePresentationContent::platformDocs($profiles['qdb'])['heading']);
    }

    public function testInvalidContentSlotsUseTheRegisteredEditorialFallback(): void
    {
        $profile = SiteProfileRegistry::all()['chouse'];
        $profile['presentationSlots']['editorial'] = 'untrusted-content.php';

        assertSame('zenmemes', PresentationSlotRegistry::resolve($profile, 'editorial'));
        assertSame('Meme Oven Is Busy', ProfilePresentationContent::busy($profile)['heading']);
        assertSame('chouse public architecture', ProfilePresentationContent::platformDocs($profile)['architectureTitle']);
    }

    public function testAboutAndPlatformDocumentPagesRenderTheActiveProfileContent(): void
    {
        foreach (SiteProfileRegistry::all() as $profileId => $profile) {
            putenv('FORUM_SITE_ID=' . $profileId);
            try {
                $about = $this->render('/about/');
                $docs = $this->render('/docs/');

                assertStringContains('<h1>About ' . $profile['displayName'] . '</h1>', $about);
                assertStringContains('<h1>' . $profile['displayName'] . ' Platform Docs</h1>', $docs);
                assertStringContains('<title id="docs-diagram-title">' . $profile['displayName'] . ' public architecture</title>', $docs);
                assertSame($profileId === 'chouse', str_contains($about, 'Hackable by design'));
            } finally {
                putenv('FORUM_SITE_ID');
            }
        }
    }

    private function render(string $path): string
    {
        $application = new Application(
            dirname(__DIR__),
            __DIR__ . '/fixtures/parity_minimal_v1',
            sys_get_temp_dir() . '/forum-rewrite-profile-content-' . bin2hex(random_bytes(6)) . '.sqlite3',
        );
        ob_start();
        $application->handle('GET', $path);

        return (string) ob_get_clean();
    }
}
