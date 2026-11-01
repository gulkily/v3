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
     * The launcher's groups, in order, by who uses the tools. Each key is also
     * the group heading's `data-shape` hook.
     *
     * @return array<string, string>
     */
    public static function groups(): array
    {
        return [
            'device' => 'Your device',
            'reading' => 'Reading',
            'community' => 'Community',
            'operator' => 'Operator',
        ];
    }

    /**
     * Single source of truth for every /tools/* destination: the Tools bar
     * (navOptions()) and the /tools/ launcher (registry() consumed directly by
     * ToolsPageController) both build from this list, in this order, so they
     * can't drift apart. `group` places the tool under one of groups();
     * `tone` is its stable theme hook (`data-tone`). `standalone` marks pages
     * that render as full-page views with no Tools bar of their own (Activity,
     * Forte, Offline reading, Platform docs) - they still appear in the
     * launcher and the bar, but never receive `toolNavOptions`.
     *
     * @return list<array{key:string,label:string,href:string,group:string,tone:string,standalone:bool}>
     */
    public static function registry(): array
    {
        return [
            ['key' => 'outbox', 'label' => 'Outbox', 'href' => '/tools/outbox/', 'group' => 'device', 'tone' => 'outbox', 'standalone' => false],
            ['key' => 'offline-reading', 'label' => 'Offline Reading', 'href' => '/offline/', 'group' => 'device', 'tone' => 'offline', 'standalone' => true],
            ['key' => 'bookmarklets', 'label' => 'Bookmarklets', 'href' => '/tools/bookmarklets/', 'group' => 'device', 'tone' => 'bookmarklets', 'standalone' => false],
            ['key' => 'activity', 'label' => 'Activity', 'href' => '/activity/', 'group' => 'reading', 'tone' => 'activity', 'standalone' => true],
            ['key' => 'forte', 'label' => 'Forte', 'href' => '/forte', 'group' => 'reading', 'tone' => 'forte', 'standalone' => true],
            ['key' => 'docs', 'label' => 'Platform Docs', 'href' => '/docs/', 'group' => 'reading', 'tone' => 'docs', 'standalone' => true],
            ['key' => 'user-access', 'label' => 'User Access', 'href' => '/tools/user-access/', 'group' => 'community', 'tone' => 'access', 'standalone' => false],
            ['key' => 'visitor-statistics', 'label' => 'Visitor Statistics', 'href' => '/tools/visitor-statistics/', 'group' => 'community', 'tone' => 'statistics', 'standalone' => false],
            ['key' => 'feature-flags', 'label' => 'Feature Flags', 'href' => '/tools/feature-flags/', 'group' => 'operator', 'tone' => 'flags', 'standalone' => false],
            ['key' => 'codebase', 'label' => 'System status', 'href' => '/tools/codebase/', 'group' => 'operator', 'tone' => 'status', 'standalone' => false],
            ['key' => 'sqlite', 'label' => 'SQLite Viewer', 'href' => '/tools/sqlite/', 'group' => 'operator', 'tone' => 'database', 'standalone' => false],
            ['key' => 'backup', 'label' => 'Backup', 'href' => '/tools/backup/', 'group' => 'operator', 'tone' => 'backup', 'standalone' => false],
            ['key' => 'llm-exchanges', 'label' => 'LLM Exchanges', 'href' => '/tools/llm-exchanges/', 'group' => 'operator', 'tone' => 'llm', 'standalone' => false],
            ['key' => 'fastmod', 'label' => 'Fastmod', 'href' => '/tools/fastmod/', 'group' => 'operator', 'tone' => 'fastmod', 'standalone' => false],
            ['key' => 'operations', 'label' => 'Operations', 'href' => '/tools/operations/', 'group' => 'operator', 'tone' => 'operations', 'standalone' => false],
        ];
    }

    /**
     * The Tools bar: every tool in registry (group) order. `group_start` marks
     * the first tool of each group after the first, where the bar shows a
     * divider.
     *
     * @return list<array{key:string,label:string,href:string,tone:string,is_active:bool,standalone:bool,group_start:bool}>
     */
    public static function navOptions(?string $activeKey): array
    {
        $options = [];
        $previousGroup = null;

        foreach (self::registry() as $tool) {
            $options[] = [
                'key' => $tool['key'],
                'label' => $tool['label'],
                'href' => $tool['href'],
                'tone' => $tool['tone'],
                'is_active' => $activeKey === $tool['key'],
                'standalone' => $tool['standalone'],
                'group_start' => $previousGroup !== null && $previousGroup !== $tool['group'],
            ];
            $previousGroup = $tool['group'];
        }

        return $options;
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
