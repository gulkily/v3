<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

final class TestingSelfApproval
{
    public function __construct(private readonly string $projectRoot) {}

    public function isEnabledForRequest(): bool
    {
        $marker = $this->projectRoot . '/.test-self-approval';
        clearstatcache(true, $marker);
        return is_file($marker)
            && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            && preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]+)?$/iD', (string) ($_SERVER['HTTP_HOST'] ?? '')) === 1
            && empty($_SERVER['HTTP_FORWARDED'])
            && empty($_SERVER['HTTP_X_FORWARDED_FOR'])
            && empty($_SERVER['HTTP_X_FORWARDED_HOST']);
    }
}
