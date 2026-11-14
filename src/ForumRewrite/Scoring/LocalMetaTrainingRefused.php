<?php

declare(strict_types=1);

namespace ForumRewrite\Scoring;

/** Training stopped because one class has too few labels; the counts say which. */
final class LocalMetaTrainingRefused extends \RuntimeException
{
    /** @param array<string,int> $counts */
    public function __construct(public readonly array $counts, public readonly int $meta, public readonly int $other, bool $weakNegatives)
    {
        parent::__construct(sprintf(
            'Too few labels to train: %d meta, %d not meta; at least %d of each are needed.%s',
            $meta, $other, LocalMetaTraining::MIN_PER_CLASS,
            $weakNegatives || $other >= LocalMetaTraining::MIN_PER_CLASS ? '' : ' Try --weak-negatives to count untagged, unscored posts as not meta.',
        ));
    }
}
