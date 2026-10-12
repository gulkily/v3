<?php

declare(strict_types=1);

namespace ForumRewrite\Operator;

use PDO;
use RuntimeException;

final class OperatorKeyPolicy
{
    public static function permits(array $state, ?array $actor, array $target): bool
    {
        if ($actor === null || (int) ($actor['is_approved'] ?? 0) !== 1) {
            return false;
        }
        return !isset($state['protected'][$target['username_token']])
            || $actor['username_token'] === $target['username_token']
            || isset($state['accounts'][$actor['username_token']]);
    }

    public static function allows(PDO $pdo, ?array $actor, array $target): bool
    {
        try { return self::permits(OperatorAuthority::load($pdo), $actor, $target); }
        catch (\Throwable) { return false; }
    }

    public static function assertAllowed(PDO $pdo, string $actorId, string $targetId): void
    {
        $state = OperatorAuthority::load($pdo);
        $target = $state['profiles'][$targetId] ?? null;
        if ($target === null || !self::permits($state, $state['profiles'][$actorId] ?? null, $target)) {
            throw new RuntimeException('This account key requires approval by an operator or an approved key of the same account.');
        }
    }
}
