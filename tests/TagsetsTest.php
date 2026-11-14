<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Support\Tagsets;

final class TagsetsTest
{
    public function testShippedFileUnlocksMetaResolution(): void
    {
        $sets = Tagsets::fromFile(__DIR__ . '/../resources/tagsets.txt');
        assertSame(['claimed', 'resolved', 'closed', 'open'], $sets->unlockedFor(['general', 'meta']));
        assertSame([], $sets->unlockedFor(['general']));
        assertSame(['claimed', 'resolved', 'closed', 'open'], $sets->exclusiveGroupOf('closed'));
        assertSame(true, $sets->isUnlockable('resolved'));
        assertSame(false, $sets->isUnlockable('like'));
    }

    public function testParsesBlocksCommentsAndCase(): void
    {
        $sets = Tagsets::parse("Schema: tagsets-v1\n\n# comment\nParent: #Event\nUnlocks: attend, Interested, attend, event\n\nParent: meta\nUnlocks: done\nExclusive: yes\n");
        assertSame(['attend', 'interested'], $sets->unlockedFor(['EVENT']));
        assertSame(null, $sets->exclusiveGroupOf('attend'));
        assertSame(['done'], $sets->exclusiveGroupOf('done'));
        assertSame(['done'], $sets->exclusiveTags());
        assertSame(['attend', 'interested', 'done'], $sets->unlockedFor(['event', 'meta']));
    }

    public function testMalformedOrUnsupportedBlocksUnlockNothing(): void
    {
        $sets = Tagsets::parse("Parent: meta\nthis line has no colon\nUnlocks: resolved\n\nParent: thread\nTarget: thread\nUnlocks: open\n\nParent: x\nUnlocks:\n\nParent: bad tag!\nUnlocks: ok\n");
        assertSame([], $sets->unlockedFor(['meta', 'thread', 'x']));
        assertSame(false, $sets->isUnlockable('ok'));
    }

    public function testTargetsReplyRootOrBoth(): void
    {
        $sets = Tagsets::parse("Parent: meta\nTarget: reply, root\nUnlocks: resolved\n\nParent: event\nUnlocks: attend\n\nParent: poll\nTarget: root\nUnlocks: vote\n");
        assertSame(['resolved', 'attend'], $sets->unlockedFor(['meta', 'event', 'poll']));
        assertSame(['resolved', 'vote'], $sets->unlockedFor(['meta', 'event', 'poll'], Tagsets::TARGET_ROOT));
        assertSame(['claimed', 'resolved', 'closed', 'open'], Tagsets::fromFile(__DIR__ . '/../resources/tagsets.txt')->unlockedFor(['meta'], Tagsets::TARGET_ROOT));
    }

    public function testMissingFileUnlocksNothing(): void
    {
        assertSame([], Tagsets::fromFile('/nonexistent/tagsets.txt')->unlockedFor(['meta']));
    }
}
