<?php

declare(strict_types=1);

namespace ForumRewrite\Statistics;

use DateTimeImmutable;
use ForumRewrite\Support\PrivateConfig;
use PDO;
use Throwable;

final class VisitorStatisticsObserver
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function record(string $method, string $path, ?string $authenticatedIdentityId = null): void
    {
        if (!$this->isEligiblePageRequest($method, $path) || $this->isRecognizableAutomation()) {
            return;
        }

        try {
            $path = VisitorStatisticsDatabaseConfig::path($this->projectRoot, $this->privateConfig());
            $directory = dirname($path);
            if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
                return;
            }
            (new VisitorStatisticsStore(new PDO('sqlite:' . $path)))->recordVisit(
                new DateTimeImmutable('now'),
                $this->clientKey(),
                $authenticatedIdentityId,
            );
        } catch (Throwable) {
            // Statistics must never make a visitor request fail.
        }
    }

    public function isEligiblePageRequest(string $method, string $path): bool
    {
        if ($method !== 'GET' || str_starts_with($path, '/api/') || str_starts_with($path, '/assets/')) {
            return false;
        }

        return !in_array($path, ['/tools/visitor-statistics', '/tools/visitor-statistics/'], true);
    }

    private function isRecognizableAutomation(): bool
    {
        $agent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

        return $agent !== '' && preg_match('/(?:bot|crawler|spider|slurp|curl|wget)/', $agent) === 1;
    }

    private function clientKey(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '') . "\0" . (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    /** @return array<string, mixed> */
    private function privateConfig(): array
    {
        return PrivateConfig::load($this->projectRoot);
    }
}
