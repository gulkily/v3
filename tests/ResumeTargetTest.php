<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Support\ResumeTarget;

final class ResumeTargetTest
{
    public function testRetainsRelativePathAndQuery(): void
    {
        assertSame('/threads/root-001?view=full', ResumeTarget::fromRequestUri('/threads/root-001?view=full'));
        assertSame('/?format=rss', ResumeTarget::fromRequestUri('/?format=rss'));
    }

    public function testRemovesServerUnavailableFragments(): void
    {
        assertSame('/threads/root-001', ResumeTarget::fromRequestUri('/threads/root-001#reply-2'));
    }

    public function testRejectsUnsafeReturnTargets(): void
    {
        foreach ([
            '',
            'https://example.test/',
            '//example.test/',
            '/\\example.test/',
            "/threads/root-001\nLocation: https://example.test/",
            ' /threads/root-001',
        ] as $target) {
            assertSame('/', ResumeTarget::fromRequestUri($target));
        }
    }
}
