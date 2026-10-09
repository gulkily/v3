<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\View\TemplateRenderer;

final class TemplateRendererMediaEmbedsScriptTest
{
    public function testRenderStandalonePageIncludesScriptOnlyWhenFlagEnabled(): void
    {
        $renderer = new TemplateRenderer(dirname(__DIR__) . '/templates');
        $off = $renderer->renderStandalonePage('message.php', ['heading' => 'Test', 'message' => 'Test'], 'Title');
        assertSame(false, str_contains($off, 'media_embed_inline_player'));

        putenv('FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED=true');
        putenv('FORUM_MEDIA_EMBEDS_ENABLED=true');
        try {
            $rendererOn = new TemplateRenderer(dirname(__DIR__) . '/templates');
            $on = $rendererOn->renderStandalonePage('message.php', ['heading' => 'Test', 'message' => 'Test'], 'Title');
        } finally {
            putenv('FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED');
            putenv('FORUM_MEDIA_EMBEDS_ENABLED');
        }

        assertSame(true, str_contains($on, 'media_embed_inline_player'));
    }

    public function testRenderLayoutIncludesScriptOnlyWhenFlagEnabled(): void
    {
        $renderer = new TemplateRenderer(dirname(__DIR__) . '/templates');
        $off = $renderer->renderLayout('Title', '<p>content</p>', 'board');
        assertSame(false, str_contains($off, 'media_embed_inline_player'));

        putenv('FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED=true');
        putenv('FORUM_MEDIA_EMBEDS_ENABLED=true');
        try {
            $rendererOn = new TemplateRenderer(dirname(__DIR__) . '/templates');
            $on = $rendererOn->renderLayout('Title', '<p>content</p>', 'board');
        } finally {
            putenv('FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED');
            putenv('FORUM_MEDIA_EMBEDS_ENABLED');
        }

        assertSame(true, str_contains($on, 'media_embed_inline_player'));
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
