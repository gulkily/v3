<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Support\ThreadTitle;
use ForumRewrite\View\TemplateRenderer;

final class MediaEmbedBeaconTemplatesTest
{
    private const BARE_YOUTUBE_URL = 'https://www.youtube.com/watch?v=mNLeVUCLrLo';

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
