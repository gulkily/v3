<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/** Infrastructure/resource failure, never an assertion that content or a signature is invalid. */
final class OperationLimitException extends \RuntimeException {}
