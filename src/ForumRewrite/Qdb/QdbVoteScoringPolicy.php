<?php

declare(strict_types=1);

namespace ForumRewrite\Qdb;

use ForumRewrite\TagScore;

final class QdbVoteScoringPolicy
{
    public function __construct(private readonly QdbVoteCaptionCatalog $catalog)
    {
    }

    public static function forReadModel(string $readModelDatabasePath): self
    {
        return new self(QdbVoteCaptionCatalog::forReadModel($readModelDatabasePath));
    }

    public function isScoredTag(string $tag): bool
    {
        return TagScore::isScoredTag($tag) || $this->catalog->isKnownTag($tag);
    }

    public function scoreValueForTag(string $tag): int
    {
        $captionScore = $this->catalog->scoreForTag($tag);
        return $captionScore !== 0 ? $captionScore : TagScore::scoreValueForTag($tag);
    }

    public function countsTowardVoteTotal(string $tag): bool
    {
        return TagScore::countsTowardVoteTotal($tag) || $this->catalog->isKnownTag($tag);
    }

    public function isVoteTag(string $tag): bool
    {
        return $this->catalog->isKnownTag($tag) || in_array($tag, ['upvote', 'downvote', 'like'], true);
    }
}
