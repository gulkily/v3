<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use RuntimeException;

/** The read model cannot serve this request until a queued rebuild finishes. */
final class ReadModelUnavailable extends RuntimeException
{
    /** @param 'updating'|'rebuilding'|'maintenance' $state */
    public function __construct(public readonly string $state, string $reason)
    {
        parent::__construct('Read model unavailable (' . $reason . '); rebuild ' . $state . '.');
    }
}
