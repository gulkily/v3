<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Support\ThreadTitle;
use ForumRewrite\View\TemplateRenderer;

final class MediaEmbedBeaconTemplatesTest
{
    private const BARE_YOUTUBE_URL = 'https://www.youtube.com/watch?v=mNLeVUCLrLo';

    public function testTikTokThreadsKeepTheirCardsAndRequestATitle(): void
    {
        $url = 'https://www.tiktok.com/@scout2015/video/6718335390845095173';
        foreach ([$this->renderThreadCard(true, '', $url, 'tiktok-thread'), $this->renderThreadRootCard(true, '', $url, 'tiktok-thread')] as $html) {
            assertSame(true, str_contains($html, 'data-provider="tiktok"'));
            assertSame(true, str_contains($html, 'provider=tiktok&amp;url=' . rawurlencode($url) . '&amp;thread_id=tiktok-thread'));
            assertSame(false, str_contains($html, '>Untitled<'));
        }
        // A subject that is set never asks for a title.
        assertSame(false, str_contains($this->renderThreadCard(true, 'Set by hand', $url, 'tiktok-thread'), 'data-media-embed-warm-beacon'));
    }

    public function testSpotifyThreadsRenderCardsAndRequestATitleWhileShowingTheLink(): void
    {
        foreach (['track', 'album', 'playlist'] as $kind) {
            $url = 'https://open.spotify.com/' . $kind . '/11dFghVXANMlKmJXsNCbNl';
            foreach ([$this->renderThreadCard(true, '', $url, 'spotify-thread'), $this->renderThreadRootCard(true, '', $url, 'spotify-thread')] as $html) {
                assertSame(true, str_contains($html, 'data-provider="spotify"'));
                assertSame(true, str_contains($html, 'provider=spotify&amp;'));
                assertSame(false, str_contains($html, '>Untitled<'));
            }
        }
    }

    public function testThreadCardEmitsNoBeaconWhenFlagIsOff(): void
    {
        $html = $this->renderThreadCard(false, '', self::BARE_YOUTUBE_URL, 'thread-no-subject');

        assertSame(false, str_contains($html, 'data-media-embed-warm-beacon'));
    }

    public function testThreadCardEmitsNoBeaconForOrdinarySubjectWhenFlagIsOn(): void
    {
        $html = $this->renderThreadCard(true, 'A real subject', self::BARE_YOUTUBE_URL, 'thread-with-subject');

        assertSame(false, str_contains($html, 'data-media-embed-warm-beacon'));
    }

    public function testThreadCardEmitsNoBeaconForOrdinaryTextWhenFlagIsOn(): void
    {
        $html = $this->renderThreadCard(true, '', 'Just some ordinary text, nothing special here.', 'thread-ordinary-text');

        assertSame(false, str_contains($html, 'data-media-embed-warm-beacon'));
    }

    public function testThreadCardEmitsBeaconForBareUrlNoSubjectWhenFlagIsOn(): void
    {
        $html = $this->renderThreadCard(true, '', self::BARE_YOUTUBE_URL, 'thread-bare-url');

        assertSame(1, substr_count($html, 'data-media-embed-warm-beacon'));
        assertSame(true, str_contains($html, 'provider=youtube'));
        assertSame(true, str_contains($html, 'thread_id=thread-bare-url'));
        assertSame(true, str_contains($html, rawurlencode(self::BARE_YOUTUBE_URL)));
    }

    public function testThreadRootCardEmitsNoBeaconWhenFlagIsOff(): void
    {
        $html = $this->renderThreadRootCard(false, '', self::BARE_YOUTUBE_URL, 'thread-no-subject');

        assertSame(false, str_contains($html, 'data-media-embed-warm-beacon'));
    }

    public function testThreadRootCardEmitsNoBeaconForOrdinarySubjectWhenFlagIsOn(): void
    {
        $html = $this->renderThreadRootCard(true, 'A real subject', self::BARE_YOUTUBE_URL, 'thread-with-subject');

        assertSame(false, str_contains($html, 'data-media-embed-warm-beacon'));
    }

    public function testThreadRootCardEmitsNoBeaconForOrdinaryTextWhenFlagIsOn(): void
    {
        $html = $this->renderThreadRootCard(true, '', 'Just some ordinary text, nothing special here.', 'thread-ordinary-text');

        assertSame(false, str_contains($html, 'data-media-embed-warm-beacon'));
    }

    public function testThreadRootCardEmitsBeaconForBareUrlNoSubjectWhenFlagIsOn(): void
    {
        $html = $this->renderThreadRootCard(true, '', self::BARE_YOUTUBE_URL, 'thread-bare-url');

        assertSame(1, substr_count($html, 'data-media-embed-warm-beacon'));
        assertSame(true, str_contains($html, 'provider=youtube'));
        assertSame(true, str_contains($html, 'thread_id=thread-bare-url'));
        assertSame(true, str_contains($html, rawurlencode(self::BARE_YOUTUBE_URL)));
    }

    public function testFirstLineBeaconOnlyWithItsFlag(): void
    {
        $body = self::BARE_YOUTUBE_URL . "\nthis one goes hard";
        assertSame(false, str_contains($this->renderThreadCard(true, '', $body, 'note-thread'), 'data-media-embed-warm-beacon'));
        putenv('FORUM_MEDIA_EMBEDS_FIRST_LINE_TITLES_ENABLED=true');
        try {
            assertSame(true, str_contains($this->renderThreadCard(true, '', $body, 'note-thread'), 'thread_id=note-thread'));
            assertSame(true, str_contains($this->renderThreadRootCard(true, '', $body, 'note-thread'), 'thread_id=note-thread'));
        } finally {
            putenv('FORUM_MEDIA_EMBEDS_FIRST_LINE_TITLES_ENABLED');
        }
    }

    private function renderThreadCard(bool $flagEnabled, string $subject, string $bodyPreview, string $rootPostId): string
    {
        $data = [
            'thread' => [
                'root_post_id' => $rootPostId,
                'subject' => $subject,
                'body_preview' => $bodyPreview,
                'root_post_created_at' => '2026-04-10T12:00:00Z',
                'last_activity_at' => '2026-04-10T12:00:00Z',
                'reply_count' => 0,
                'thread_labels' => [],
            ],
        ];

        return $this->withFlag($flagEnabled, static fn (TemplateRenderer $renderer): string => $renderer->renderFragment('partials/thread_card.php', $data));
    }

    private function renderThreadRootCard(bool $flagEnabled, string $subject, string $body, string $rootPostId): string
    {
        $data = [
            'thread' => [
                'root_post_id' => $rootPostId,
                'subject' => $subject,
                'score_total' => 0,
                'vote_count' => 0,
                'thread_labels' => [],
                'last_activity_at' => '2026-04-10T12:00:00Z',
                'reply_count' => 0,
            ],
            'post' => [
                'post_id' => $rootPostId,
                'thread_id' => $rootPostId,
                'author_label' => 'guest',
                'created_at' => '2026-04-10T12:00:00Z',
                'body' => $body,
            ],
            'title' => ThreadTitle::displayTitle($subject, $body, $rootPostId, 80, $flagEnabled),
            'trueReplyCount' => 0,
            'metaVisible' => true,
        ];

        return $this->withFlag($flagEnabled, static fn (TemplateRenderer $renderer): string => $renderer->renderFragment('partials/thread_root_card.php', $data));
    }

    /**
     * @param callable(TemplateRenderer): string $render
     */
    private function withFlag(bool $flagEnabled, callable $render): string
    {
        if (!$flagEnabled) {
            return $render(new TemplateRenderer(dirname(__DIR__) . '/templates'));
        }

        putenv('FORUM_MEDIA_EMBEDS_ENABLED=true');
        try {
            return $render(new TemplateRenderer(dirname(__DIR__) . '/templates'));
        } finally {
            putenv('FORUM_MEDIA_EMBEDS_ENABLED');
        }
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
            );
        }
    }
}
