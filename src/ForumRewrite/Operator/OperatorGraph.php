<?php

declare(strict_types=1);

namespace ForumRewrite\Operator;

use RuntimeException;

/** Pure authority derivation. Events must have verified detached signatures. */
final class OperatorGraph
{
    public static function derive(array $profiles, array $rootIdentityIds, array $events): array
    {
        $roots = [];
        foreach ($rootIdentityIds as $id) {
            if (isset($profiles[$id])) {
                $roots[$profiles[$id]['username_token']] = true;
            }
        }
        $grants = [];
        $protected = $roots;
        $accounts = $roots;
        usort($events, static fn (array $a, array $b): int => $a['sequence'] <=> $b['sequence']);
        $sequence = 0;
        foreach ($events as $event) {
            if ($event['sequence'] !== ++$sequence) {
                throw new RuntimeException('Operator history has duplicate or missing sequence numbers.');
            }
            $actor = $profiles[$event['author_identity_id']] ?? null;
            $target = $profiles[$event['target_identity_id']] ?? null;
            if ($actor === null || $target === null || (int) ($actor['is_approved'] ?? 0) !== 1
                || (int) ($target['is_approved'] ?? 0) !== 1
                || !isset($accounts[$actor['username_token']])
                || $event['account'] !== $target['username_token']
                || $event['thread_id'] !== $target['bootstrap_thread_id']
                || $event['parent_id'] !== $target['bootstrap_post_id']) {
                throw new RuntimeException('Operator history contains an unauthorized or incorrectly bound action.');
            }
            if ($event['action'] === 'grant') {
                if (isset($grants[$event['post_id']])) {
                    throw new RuntimeException('Invalid operator grant.');
                }
                $grants[$event['post_id']] = $event + [
                    'from' => $actor['username_token'], 'to' => $event['account'], 'revoked' => false,
                ];
                $protected[$event['account']] = true;
            } elseif ($event['action'] === 'revoke') {
                $id = $event['grant_id'];
                if (!isset($grants[$id]) || $grants[$id]['revoked'] || $grants[$id]['to'] !== $event['account']) {
                    throw new RuntimeException('Operator revocation does not reference an active grant.');
                }
                $grants[$id]['revoked'] = true;
                $grants[$id]['revocation_id'] = $event['post_id'];
            } else {
                throw new RuntimeException('Unknown operator action.');
            }
            $accounts = self::reachable($roots, $grants);
        }
        return compact('roots', 'accounts', 'protected', 'grants', 'sequence');
    }

    public static function reachable(array $roots, array $grants): array
    {
        $accounts = $roots;
        do {
            $changed = false;
            foreach ($grants as $grant) {
                if (!$grant['revoked'] && isset($accounts[$grant['from']]) && !isset($accounts[$grant['to']])) {
                    $accounts[$grant['to']] = true;
                    $changed = true;
                }
            }
        } while ($changed);
        ksort($accounts);
        return $accounts;
    }

    public static function preview(array $state, string $grantId): array
    {
        if (!isset($state['grants'][$grantId]) || $state['grants'][$grantId]['revoked']) {
            throw new RuntimeException('Grant not found or already revoked.');
        }
        $grants = $state['grants'];
        $grants[$grantId]['revoked'] = true;
        $remaining = self::reachable($state['roots'], $grants);
        return array_keys(array_diff_key($state['accounts'], $remaining));
    }
}
