<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

use RuntimeException;

final class ProviderRequestException extends RuntimeException
{
    /**
     * @param array<string, mixed> $diagnostics
     */
    public function __construct(
        string $message,
        private readonly array $diagnostics,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }
}
