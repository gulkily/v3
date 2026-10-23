<?php

declare(strict_types=1);

namespace ForumRewrite\Activity;

/** Human summaries of existing evidence; never reconstruct missing acceptance facts. */
final class AccessHistoryPresenter
{
    public static function present(array $item): array
    {
        $context = $item['security_review']['context'] ?? [];
        $target = $context['target_username'] ?? $item['security_account'] ?? null;
        $actor = $context['actor_username'] ?? $item['author_label'] ?? 'unknown';
        $kind = $item['kind'] ?? '';
        $title = match ($kind) {
            'key_revocation' => 'Account key revoked' . (isset($item['security_revocation']['username']) ? ' for ' . $item['security_revocation']['username'] : ''),
            'approval' => 'Account key approved' . ($target ? ' for ' . $target : ''),
            'invitation' => 'Invitation accepted' . ($target ? ' by ' . $target : ''),
            default => (string) ($item['label'] ?? 'Access change'),
        };
        if ($kind === 'site_feature_flag' && preg_match('/FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=(true|false)(?:\s|$)/', (string) ($item['label'] ?? ''), $match)) {
            $title = $match[1] === 'true' ? 'Cross-account approvals enabled' : 'Cross-account approvals restricted';
        }
        $classification = match ($context['classification'] ?? '') {
            'new_account' => 'new account',
            'own_account_key' => 'own account key',
            'cross_account_key' => 'cross-account key',
            default => '',
        };
        return [
            'title' => $title,
            'byline' => 'By ' . ($actor !== '' ? $actor : 'unknown') . ($classification !== '' ? ' · ' . $classification : ''),
            'created_at' => (string) ($item['created_at'] ?? ''),
            'icon' => match ($kind) { 'key_revocation' => '−', 'approval' => '+', 'invitation' => '✓', default => '↔' },
        ];
    }
}
