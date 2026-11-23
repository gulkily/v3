<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Support\Tagsets;

final class TagsetsTest
{
    public function testShippedFileUnlocksIssueResolution(): void
    {
        $sets = Tagsets::fromFile(__DIR__ . '/../resources/tagsets.txt');
        assertSame(['claimed', 'resolved', 'closed', 'open'], $sets->unlockedFor(['general', 'issue']));
        assertSame([], $sets->unlockedFor(['general']));
        assertSame([], $sets->unlockedFor(['general', 'meta']), '#meta is a topic; only #issue unlocks the work states.');
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
        assertSame(['claimed', 'resolved', 'closed', 'open'], Tagsets::fromFile(__DIR__ . '/../resources/tagsets.txt')->unlockedFor(['issue'], Tagsets::TARGET_ROOT));
    }

    public function testMissingFileUnlocksNothing(): void
    {
        assertSame([], Tagsets::fromFile('/nonexistent/tagsets.txt')->unlockedFor(['meta']));
    }

    public function testCheckAcceptsTheShippedFileAndEmptyText(): void
    {
        assertSame([], Tagsets::check((string) file_get_contents(__DIR__ . '/../resources/tagsets.txt')));
        assertSame([], Tagsets::check(''));
        assertSame([], Tagsets::check("Parent: event\nUnlocks: attend\n\nParent: meetup\nUnlocks: attend\n"), 'a plain tag may sit under two parents');
    }

    public function testCheckNamesTheLineOfEachProblem(): void
    {
        $errors = Tagsets::check(implode("\n", [
            'Schema: tagsets-v2',          // 1
            '',
            'Parent: issue',               // 3
            'Unlocks: claimed, like',      // 4
            'Exclusive: maybe',            // 5
            '',
            'Parent: bad tag!',            // 7
            'Unlocks: ok',
            '',
            'Parent: work',                // 10
            'Unlocks: done',
            'Exclusive: yes',
            'Colour: red',                 // 13
            '',
            'Parent: chore',               // 15
            'Target: thread',              // 16
            'Unlocks: x',
            '',
            'Parent: todo',                // 19
            'Unlocks: done',
            '',
            'Parent: empty',               // 22
            'no colon here',               // 23
            '',
            'Target: reply',               // 25
            '',
            'Parent: lonely',              // 27
        ]));
        assertSame([
            'Line 1: unsupported schema "tagsets-v2" (expected tagsets-v1).',
            'Line 4: like is a built-in tag and cannot be unlocked.',
            'Line 5: Exclusive must be yes or no.',
            'Line 7: "bad tag!" is not a tag name (letters, digits, - and _).',
            'Line 13: unknown key "Colour" (use Parent, Target, Unlocks or Exclusive).',
            'Line 16: Target must be reply, root, or both.',
            'Line 23: expected "Key: value".',
            'Line 22: this block has no Unlocks.',
            'Line 25: this block has no Parent.',
            'Line 27: this block has no Unlocks.',
        ], $errors, 'an invalid block (line 13) claims no tags, so line 19 is not a conflict');
        assertSame(['Line 5: tag done is also unlocked by #work (line 1); a state tag can belong to only one tagset.'],
            Tagsets::check("Parent: work\nUnlocks: done\nExclusive: yes\n\nParent: todo\nUnlocks: done, later\n"));
    }

    public function testValidSiteRecordReplacesTheShippedFileAndABadOneFallsBack(): void
    {
        $root = sys_get_temp_dir() . '/forum-tagsets-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/instance', 0777, true);
        try {
            $state = Tagsets::stateFor($root);
            assertSame(Tagsets::SOURCE_DEFAULT, $state['source']);
            assertSame(['claimed', 'resolved', 'closed', 'open'], $state['tagsets']->unlockedFor(['issue']));

            file_put_contents($root . '/' . Tagsets::SITE_RECORD_PATH, "Parent: event\nUnlocks: attend, interested\n");
            Tagsets::forget();
            $state = Tagsets::stateFor($root);
            assertSame(Tagsets::SOURCE_SITE, $state['source']);
            assertSame(['attend', 'interested'], Tagsets::forRepository($root)->unlockedFor(['event']));
            assertSame([], $state['tagsets']->unlockedFor(['issue']), 'the site record replaces the shipped file entirely');

            Tagsets::useRepository($root);
            assertSame(['attend', 'interested'], Tagsets::forApplication()->unlockedFor(['event']));

            file_put_contents($root . '/' . Tagsets::SITE_RECORD_PATH, "Parent: event\nUnlocks: like\n");
            Tagsets::forget();
            $state = Tagsets::active();
            assertSame(Tagsets::SOURCE_DEFAULT, $state['source']);
            assertSame('Line 2: like is a built-in tag and cannot be unlocked.', $state['error']);
            assertSame(['claimed', 'resolved', 'closed', 'open'], Tagsets::forApplication()->unlockedFor(['issue']));
        } finally {
            Tagsets::useRepository(null);
            Tagsets::forget();
            exec('rm -rf ' . escapeshellarg($root));
        }
    }
}
