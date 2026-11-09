<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use RuntimeException;

/** A finished read-model candidate misses commits that landed while it was being built. */
final class ReadModelCandidateBehindHead extends RuntimeException
{
}
