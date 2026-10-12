<?php

declare(strict_types=1);

namespace ForumRewrite\Operator;

use PDO;

final class OperatorPresentation
{
    public static function account(PDO $pdo, string $token, string $actorId, ?array $state = null): array
    {
        try {
            $state ??= OperatorAuthority::load($pdo);
        } catch (\Throwable) {
            return ['actorIdentityId' => $actorId, 'status' => 'unavailable', 'token' => $token, 'canManage' => false, 'target' => null, 'grants' => []];
        }
        $actor = $state['profiles'][$actorId] ?? null;
        $target = null;
        foreach ($state['profiles'] as $profile) {
            if ($profile['username_token'] === $token && (int) $profile['is_approved'] === 1) {
                $target ??= $profile;
            }
        }
        $status = isset($state['roots'][$token]) ? 'root operator'
            : (isset($state['accounts'][$token]) ? 'operator' : (isset($state['protected'][$token]) ? 'suspended operator' : 'member'));
        $grants = array_filter($state['grants'], static fn ($grant) => $grant['to'] === $token);
        foreach ($grants as &$grant) {
            $grant['effective'] = !$grant['revoked'] && isset($state['accounts'][$grant['from']]);
        }
        unset($grant);
        return [
            'actorIdentityId' => $actorId, 'status' => $status, 'token' => $token, 'target' => $target,
            'canManage' => $actor !== null && (int) $actor['is_approved'] === 1 && isset($state['accounts'][$actor['username_token']]),
            'grants' => $grants,
        ];
    }
}
