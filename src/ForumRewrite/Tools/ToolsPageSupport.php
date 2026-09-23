<?php

declare(strict_types=1);

namespace ForumRewrite\Tools;

use PDO;

/**
 * Small, self-contained pieces shared by every /tools/* page (bookmarklets,
 * backup, SQLite viewer, LLM exchanges, codebase state, feature flags), so
 * route-group controllers extracted out of Application.php (see
 * docs/plans/codebase_cleanup_audit_plan_v1.md, Phase 2) don't each need
 * their own copy.
 */
final class ToolsPageSupport
{
    /**
     * @return list<array{key:string,label:string,href:string,is_active:bool}>
     */
    public static function navOptions(?string $activeKey): array
    {
        return [
            [
                'key' => 'bookmarklets',
                'label' => 'Bookmarklets',
                'href' => '/tools/bookmarklets/',
                'is_active' => $activeKey === 'bookmarklets',
            ],
            [
                'key' => 'backup',
                'label' => 'Backup',
                'href' => '/tools/backup/',
                'is_active' => $activeKey === 'backup',
            ],
            [
                'key' => 'sqlite',
                'label' => 'SQLite Viewer',
                'href' => '/tools/sqlite/',
                'is_active' => $activeKey === 'sqlite',
            ],
            [
                'key' => 'llm-exchanges',
                'label' => 'LLM Exchanges',
                'href' => '/tools/llm-exchanges/',
                'is_active' => $activeKey === 'llm-exchanges',
            ],
            [
                'key' => 'codebase',
                'label' => 'System State',
                'href' => '/tools/codebase/',
                'is_active' => $activeKey === 'codebase',
            ],
            [
                'key' => 'feature-flags',
                'label' => 'Feature Flags',
                'href' => '/tools/feature-flags/',
                'is_active' => $activeKey === 'feature-flags',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function fetchSeedApprovedUsers(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT username_token, MIN(username) AS username
             FROM profiles
             WHERE approved_by_label = \'root\'
             GROUP BY username_token
             ORDER BY username_token ASC'
        );

        return $stmt->fetchAll();
    }
}
