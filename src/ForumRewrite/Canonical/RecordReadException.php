<?php

declare(strict_types=1);
namespace ForumRewrite\Canonical;
/** Storage failures must not become content rejection. */
final class RecordReadException extends \RuntimeException {}
