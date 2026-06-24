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
}
