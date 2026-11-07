<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Support\PostText;
use ForumRewrite\Support\ThreadTitle;
use ForumRewrite\View\TemplateRenderer;

final class ThreadRootCardRepeatedTitleTest
{
    public function testMarkdownHeadingFirstLineIsNotShownAgainUnderTheTitle(): void
    {
        $html = $this->renderRootCard("# New theme: Engagify\n\nSome visitors find ZenMemes disorienting.", 'markdown');

        assertSame(1, substr_count($html, 'New theme: Engagify'));
        assertSame(true, str_contains($html, 'Some visitors find ZenMemes disorienting.'));
    }

    public function testPlainFirstLineIsStillRemovedWhenItRepeatsTheTitle(): void
    {
        $html = $this->renderRootCard("New theme: Engagify\n\nSome visitors find ZenMemes disorienting.", null);

        assertSame(1, substr_count($html, 'New theme: Engagify'));
        assertSame(true, str_contains($html, 'Some visitors find ZenMemes disorienting.'));
    }

    public function testPlainHashLineIsKeptBecauseItIsNotTheTitle(): void
    {
        // A plain post's "# tag" line is its own words; its derived title keeps the "#".
        $html = $this->renderRootCard("# not a heading\n\nBody text.", null);

        assertSame(1, substr_count($html, '# not a heading'));
    }

    private function renderRootCard(string $body, ?string $format): string
    {
        $rootPostId = 'thread-repeated-title';
        $data = [
            'thread' => [
                'root_post_id' => $rootPostId,
                'subject' => '',
                'score_total' => 0,
                'vote_count' => 0,
                'thread_labels' => [],
                'last_activity_at' => '2026-11-07T17:36:46Z',
                'reply_count' => 0,
            ],
            'post' => [
                'post_id' => $rootPostId,
                'thread_id' => $rootPostId,
                'author_label' => 'guest',
                'created_at' => '2026-11-07T17:36:46Z',
                'body' => $body,
                'body_format' => $format,
            ],
            // As the thread page does: the stored preview is the body's first line.
            'title' => ThreadTitle::displayTitle('', PostText::plainPreview((string) strtok($body, "\n"), $format), $rootPostId),
            'trueReplyCount' => 0,
            'metaVisible' => true,
        ];

        return (new TemplateRenderer(dirname(__DIR__) . '/templates'))->renderFragment('partials/thread_root_card.php', $data);
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
