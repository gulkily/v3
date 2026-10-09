<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Support\ThreadTitle;

final class ThreadTitleTest
{
    public function testDisplayTitlePrefersSubject(): void
    {
        assertSame('Explicit subject', ThreadTitle::displayTitle(' Explicit subject ', 'Body text', 'thread-1'));
    }

    public function testDisplayTitleFallsBackToBodyExcerpt(): void
    {
        assertSame(
            'This is the first meaningful line of the thread body',
            ThreadTitle::displayTitle('', "  This is the first meaningful line\nof the thread body  ", 'thread-1')
        );
    }

    public function testDisplayTitleTruncatesLongBodyAtWordBoundary(): void
    {
        assertSame(
            'This body is long enough to need a deterministic title excerpt for the board...',
            ThreadTitle::displayTitle('', 'This body is long enough to need a deterministic title excerpt for the board listing and RSS output.', 'thread-1')
        );
    }

    public function testDisplayTitleFallsBackToThreadIdWhenSubjectAndBodyAreEmpty(): void
    {
        assertSame('thread-1', ThreadTitle::displayTitle('', '', 'thread-1'));
    }

    public function testDisplayTitleIgnoresBareMediaUrlWhenFlagIsOff(): void
    {
        assertSame(
            'https://www.youtube.com/watch?v=mNLeVUCLrLo',
            ThreadTitle::displayTitle('', 'https://www.youtube.com/watch?v=mNLeVUCLrLo', 'thread-1')
        );
        assertSame(
            'https://www.youtube.com/watch?v=mNLeVUCLrLo',
            ThreadTitle::displayTitle('', 'https://www.youtube.com/watch?v=mNLeVUCLrLo', 'thread-1', 80, false)
        );
    }

    public function testDisplayTitleShowsUntitledForBareMediaUrlWhenFlagIsOn(): void
    {
        assertSame(
            'Untitled',
            ThreadTitle::displayTitle('', 'https://www.youtube.com/watch?v=mNLeVUCLrLo', 'thread-1', 80, true)
        );
        assertSame(
            'Untitled',
            ThreadTitle::displayTitle('', '  https://www.instagram.com/p/Cabc123XYZ/  ', 'thread-1', 80, true)
        );
    }

    public function testDisplayTitleFlagOnLeavesOrdinaryTextExcerptUnchanged(): void
    {
        assertSame(
            'This is the first meaningful line of the thread body',
            ThreadTitle::displayTitle('', "  This is the first meaningful line\nof the thread body  ", 'thread-1', 80, true)
        );
    }

    public function testDisplayTitleFlagOnLeavesExistingSubjectUnchanged(): void
    {
        assertSame(
            'Explicit subject',
            ThreadTitle::displayTitle(' Explicit subject ', 'https://www.youtube.com/watch?v=mNLeVUCLrLo', 'thread-1', 80, true)
        );
    }

    public function testDisplayTitleFlagOnLeavesUrlWithExtraTextUnchanged(): void
    {
        assertSame(
            'https://www.youtube.com/watch?v=mNLeVUCLrLo check this out',
            ThreadTitle::displayTitle('', 'https://www.youtube.com/watch?v=mNLeVUCLrLo check this out', 'thread-1', 80, true)
        );
    }

    public function testBareMediaEmbedMatchReturnsMatchForBareUrlWithNoSubject(): void
    {
        $match = ThreadTitle::bareMediaEmbedMatch('', '  https://www.youtube.com/watch?v=mNLeVUCLrLo  ');

        assertSame('youtube', $match['provider']);
        assertSame('mNLeVUCLrLo', $match['embedId']);
        assertSame('https://www.youtube.com/watch?v=mNLeVUCLrLo', $match['url']);
    }

    public function testBareMediaEmbedMatchReturnsNullWhenSubjectIsPresent(): void
    {
        assertSame(null, ThreadTitle::bareMediaEmbedMatch('Has a subject', 'https://www.youtube.com/watch?v=mNLeVUCLrLo'));
    }

    public function testBareMediaEmbedMatchReturnsNullWhenBodyHasExtraText(): void
    {
        assertSame(null, ThreadTitle::bareMediaEmbedMatch('', 'check this out https://www.youtube.com/watch?v=mNLeVUCLrLo'));
        assertSame(null, ThreadTitle::bareMediaEmbedMatch('', 'https://www.youtube.com/watch?v=mNLeVUCLrLo check this out'));
    }

    public function testBareMediaEmbedMatchReturnsNullForOrdinaryTextOrUnrecognizedUrl(): void
    {
        assertSame(null, ThreadTitle::bareMediaEmbedMatch('', 'Just some ordinary text.'));
        assertSame(null, ThreadTitle::bareMediaEmbedMatch('', 'https://example.com/not-a-recognized-provider'));
        assertSame(null, ThreadTitle::bareMediaEmbedMatch('', ''));
    }
}
