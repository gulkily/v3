<?php

declare(strict_types=1);
require __DIR__ . '/../autoload.php';

use ForumRewrite\View\MediaEmbedDetector;
use ForumRewrite\View\MediaEmbedRenderer;

final class MusicMediaEmbedTest
{
    public function testSoundcloudTrackValidationAndRendering(): void
    {
        $d = new MediaEmbedDetector();
        $r = new MediaEmbedRenderer();
        $url = 'https://www.soundcloud.com/forss/flickermood/?si=tracking&t=4#section';
        assertSame('soundcloud', $d->classify($url)['provider']);
        foreach (['https://soundcloud.com/discover/sets', 'https://soundcloud.com/artist/tracks', 'https://soundcloud.com/a/b/s-secret', 'https://soundcloud.com/a/b?secret_token=x', 'https://soundcloud.com/a/b#token=x', 'https://soundcloud.com/a/b/extra', 'https://soundcloud.com.evil.test/a/b', 'https://on.soundcloud.com/example', 'https://soundcloud.com/a/%62'] as $bad) {
            assertSame(null, $d->classify($bad));
        }
        assertSame(null, \ForumRewrite\Support\ThreadTitle::bareMediaEmbedMatch('', $url));
        assertSame(false, \ForumRewrite\Support\ThreadTitle::displayTitle('', $url, 'id', 200, true) === 'Untitled');
        $html = $r->render($url, true, true);
        assertSame(true, str_contains($html, 'url=https%3A%2F%2Fsoundcloud.com%2Fforss%2Fflickermood&amp;auto_play=false'));
        assertSame(true, str_contains($html, 'src=""'));
        assertSame(true, strpos($html, 'Open on SoundCloud') < strpos($html, '<details'));
        assertSame(true, str_contains($html, '?t=4#section'));
        assertSame(false, str_contains($r->render($url, true, false), '<iframe'));
        assertSame(htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $r->render($url, false, true));
    }
}
