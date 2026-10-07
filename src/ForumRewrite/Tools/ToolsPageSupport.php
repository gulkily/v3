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
     * Single source of truth for every /tools/* destination: the sub-nav
     * (navOptions()) and the /tools/ index table (registry() consumed
     * directly by ToolsPageController) both build from this list, so they
     * can't drift apart the way they did when each kept its own hardcoded
     * array. `standalone` marks pages that render as full-page views with
     * no sub-nav of their own (Activity, Forte) - they still appear in the
     * index and (set apart) in the sub-nav, but never receive
     * `toolNavOptions`. Account is intentionally absent: it lives in the
     * main top nav already.
     *
     * @return list<array{key:string,label:string,href:string,description:string,standalone:bool}>
     */
    public static function registry(): array
    {
        return [
            [
                'key' => 'activity',
                'label' => 'Activity',
                'href' => '/activity/',
                'description' => 'Recent forum activity across content, approvals, and identity events.',
                'standalone' => true,
            ],
            [
                'key' => 'forte',
                'label' => 'Forte',
                'href' => '/forte',
                'description' => 'Classic three-pane newsreader view of the whole board - folders, thread list, and preview.',
                'standalone' => true,
            ],
            [
                'key' => 'offline-reading',
                'label' => 'Offline Reading',
                'href' => '/offline/',
                'description' => 'Check whether this browser is ready to read saved public content offline.',
                'standalone' => true,
            ],
            [
                'key' => 'docs',
                'label' => 'Platform Docs',
                'href' => '/docs/',
                'description' => 'How the site is built, operated, and extended, with repository source paths.',
                'standalone' => true,
            ],
            [
                'key' => 'bookmarklets',
                'label' => 'Bookmarklets',
                'href' => '/tools/bookmarklets/',
                'description' => 'Bookmarklet links for clipping URLs and selections straight into Compose Thread.',
                'standalone' => false,
            ],
            [
                'key' => 'outbox',
                'label' => 'Outbox',
                'href' => '/tools/outbox/',
                'description' => 'Review local drafts and queued offline actions on this device.',
                'standalone' => false,
            ],
            [
                'key' => 'backup',
                'label' => 'Backup',
                'href' => '/tools/backup/',
                'description' => 'Portable downloads of the repository and current read-model database.',
                'standalone' => false,
            ],
            [
                'key' => 'sqlite',
                'label' => 'SQLite Viewer',
                'href' => '/tools/sqlite/',
                'description' => 'Inspect the published SQLite read model in your browser.',
                'standalone' => false,
            ],
            [
                'key' => 'llm-exchanges',
                'label' => 'LLM Exchanges',
                'href' => '/tools/llm-exchanges/',
                'description' => 'Review private LLM prompts and responses chronologically.',
                'standalone' => false,
            ],
            [
                'key' => 'codebase',
                'label' => 'System State',
                'href' => '/tools/codebase/',
                'description' => 'Current application version, repository head, and read-model health.',
                'standalone' => false,
            ],
            [
                'key' => 'visitor-statistics',
                'label' => 'Visitor Statistics',
                'href' => '/tools/visitor-statistics/',
                'description' => 'Privacy-preserving recent aggregate visits, client estimates, and authenticated-user counts.',
                'standalone' => false,
            ],
            [
                'key' => 'feature-flags',
                'label' => 'Feature Flags',
                'href' => '/tools/feature-flags/',
                'description' => 'Registered site feature flags, defaults, effective values, and override sources.',
                'standalone' => false,
            ],
        ];
    }

    /**
     * In-page tools first (in registry order), then standalone tools -
     * templates render a divider before the first standalone entry.
     *
     * @return list<array{key:string,label:string,href:string,is_active:bool,standalone:bool}>
     */
    public static function navOptions(?string $activeKey): array
    {
        $inPage = [];
        $standalone = [];

        foreach (self::registry() as $tool) {
            $option = [
                'key' => $tool['key'],
                'label' => $tool['label'],
                'href' => $tool['href'],
                'is_active' => $activeKey === $tool['key'],
                'standalone' => $tool['standalone'],
            ];

            if ($tool['standalone']) {
                $standalone[] = $option;
            } else {
                $inPage[] = $option;
            }
        }

        return [...$inPage, ...$standalone];
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
