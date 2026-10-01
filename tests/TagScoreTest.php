<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\TagScore;

final class TagScoreTest
{
    public function testUpvoteIsScoredPositively(): void
    {
        assertSame(true, TagScore::isScoredTag('upvote'));
        assertSame(1, TagScore::scoreValueForTag('upvote'));
    }

    public function testDownvoteIsScoredNegatively(): void
    {
        assertSame(true, TagScore::isScoredTag('downvote'));
        assertSame(-1, TagScore::scoreValueForTag('downvote'));
    }

    public function testExistingLikeAndFlagWeightsAreUnchanged(): void
    {
        assertSame(1, TagScore::scoreValueForTag('like'));
        assertSame(-100, TagScore::scoreValueForTag('flag'));
    }

    public function testUnknownTagIsNotScored(): void
    {
        assertSame(false, TagScore::isScoredTag('not-a-real-tag'));
        assertSame(0, TagScore::scoreValueForTag('not-a-real-tag'));
    }

    public function testUpvoteAndDownvoteAreVoteTags(): void
    {
        assertSame(true, TagScore::isVoteTag('upvote'));
        assertSame(true, TagScore::isVoteTag('downvote'));
    }

    public function testLikeAndFlagAreNotVoteTags(): void
    {
        assertSame(false, TagScore::isVoteTag('like'));
        assertSame(false, TagScore::isVoteTag('flag'));
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
                . '.'
            );
        }
    }
}
