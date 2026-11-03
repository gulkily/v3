<?php

declare(strict_types=1);

use ForumRewrite\Support\PostText;

require __DIR__ . '/../autoload.php';

final class PostTextTest
{
    public function testPlainAndLegacyPostsPassThroughUnchanged(): void
    {
        $body = "## not a heading\n**kept** `as typed`";
        assertSame($body, PostText::plainPreview($body, null));
        assertSame($body, PostText::plainPreview($body, 'plain'));
    }

    public function testMarkdownLosesMarkupButKeepsWords(): void
    {
        $body = "## Deploys\n\n**Bold** and *em* with `code` and [a link](https://example.com).\n> quoted\n- [x] done\n- item\n\n```\nraw *code*\n```\n\n| A | B |\n| --- | --- |\n| 1 | 2 |\n\n---\n2 * 3 * 4";
        assertSame(
            "Deploys\n\nBold and em with code and a link.\nquoted\n- done\n- item\n\n\nraw *code*\n\n\nA B\n\n1 2\n\n\n2 * 3 * 4",
            PostText::plainPreview($body, 'markdown')
        );
    }

    public function testFirstLinePreviewSkipsBareFences(): void
    {
        assertSame("\ngit pull\n", PostText::plainPreview("```\ngit pull\n```", 'markdown'));
    }
}
