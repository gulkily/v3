<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\View\MediaEmbedRenderer;

final class MediaEmbedRendererTest
{
    private function todaysBrOutput(string $value): string
    {
        return nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    public function testFlagOffIsByteIdenticalToTodaysBrOutputEvenWithAMatchableUrl(): void
    {
        $renderer = new MediaEmbedRenderer();
        $body = "Check this out:\nhttps://youtu.be/dQw4w9WgXcQ <script>oops</script>";

        assertSame($this->todaysBrOutput($body), $renderer->render($body, false));
    }

    public function testFlagOnWithNoMatchIsByteIdenticalToTodaysBrOutput(): void
    {
        $renderer = new MediaEmbedRenderer();
        $body = "Just plain text with <b>no</b> embeddable link here.\nSecond line.";

        assertSame($this->todaysBrOutput($body), $renderer->render($body, true));
    }

    public function testMatchEmitsCardAndEscapesSurroundingTextWithoutLeakingRawMarkup(): void
    {
        $renderer = new MediaEmbedRenderer();
        $body = 'Check <script>this</script> out: https://youtu.be/dQw4w9WgXcQ cool';

        $html = $renderer->render($body, true);

        assertSame(true, str_contains($html, '&lt;script&gt;this&lt;/script&gt;'));
        assertSame(false, str_contains($html, '<script>this</script>'));
        assertSame(true, str_contains($html, 'data-media-embed-card'));
        assertSame(true, str_contains($html, 'data-provider="youtube"'));
        assertSame(true, str_contains($html, 'YouTube'));
        assertSame(true, str_contains($html, 'href="https://youtu.be/dQw4w9WgXcQ"'));
        assertSame(true, str_contains($html, ' cool'));
    }

    public function testCardLinkUsesTrackingStrippedDisplayUrl(): void
    {
        $renderer = new MediaEmbedRenderer();
        $body = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&si=abc123';

        $html = $renderer->render($body, true);

        assertSame(true, str_contains($html, 'href="https://www.youtube.com/watch?v=dQw4w9WgXcQ"'));
        assertSame(false, str_contains($html, 'si=abc123'));
    }

    public function testMultipleMatchesEachGetTheirOwnCardAndBetweenTextIsPreserved(): void
    {
        $renderer = new MediaEmbedRenderer();
        $body = 'First https://youtu.be/dQw4w9WgXcQ then https://www.instagram.com/p/Cabc123XYZ/ done';

        $html = $renderer->render($body, true);

        assertSame(2, substr_count($html, 'data-media-embed-card'));
        assertSame(true, str_contains($html, 'data-provider="youtube"'));
        assertSame(true, str_contains($html, 'data-provider="instagram"'));
        assertSame(true, str_contains($html, '</span> then <'));
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
