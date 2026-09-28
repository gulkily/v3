<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\SiteConfig;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\OperatorStatusCollector;
use ForumRewrite\Tools\ToolsPageSupport;
use PDO;

/**
 * Eighth Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md
 * and docs/plans/codebase_cleanup_audit_findings_v1.md): /tools/codebase/,
 * the last of the /tools/* pages. Deferred from the "simple /tools pages"
 * slice for its half-dozen collaborators - the most of any page in that
 * group, though each individual piece turned out small once traced.
 *
 * ExecutionLock and ReadModelStaleMarker are passed in already-constructed
 * (Application's own executionLock()/staleMarker() build a fresh instance
 * on every call already - no cache to preserve by passing a closure
 * instead). latestRepositoryCommit() and readMetadata() moved to
 * ReadModelMetadata alongside its existing repositoryHead()/
 * repositoryShortCommit() - readModelTableExists()/readModelRowCounts()
 * had no callers left outside this page and moved here wholesale.
 *
 * apiStatus() (added for the /api/read_model_status slice - see
 * docs/plans/codebase_cleanup_audit_plan_v1.md) is the plain-text twin of
 * render(): same read-model/staleness/lock domain, so it lives here rather
 * than with the unrelated /api text formatters in ApiTextController. Both
 * surfaces use OperatorStatusCollector so CLI and web health terminology
 * cannot drift.
 */
final class CodebaseStateController
{
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly string $queuePath,
        private readonly ExecutionLock $executionLock,
        private readonly ReadModelStaleMarker $staleMarker,
    ) {
    }

    public function render(): string
    {
        return $this->routeServices->renderPageTemplate(
            'codebase_state.php',
            [
                'state' => $this->collectState(),
                'toolNavOptions' => ToolsPageSupport::navOptions('codebase'),
                'siteName' => SiteConfig::siteName(),
                'admins' => ProfileRepository::approvedDirectoryUsers($this->routeServices->pdo()),
            ],
            'System State',
            'tools',
        );
    }

    public function apiStatus(): string
    {
        $snapshot = $this->operatorStatus()->collect();
        $readModel = $snapshot['read_model'];
        $taskQueue = $snapshot['task_queue'];

        return 'status=' . $readModel['status'] . "\n"
            . 'schema_version=' . $readModel['schema_version'] . "\n"
            . 'repository_root=' . $readModel['repository_root'] . "\n"
            . 'repository_head=' . $readModel['repository_head'] . "\n"
            . 'current_repository_head=' . $readModel['current_repository_head'] . "\n"
            . 'rebuilt_at=' . $readModel['rebuilt_at'] . "\n"
            . 'lock_status=' . $readModel['lock_status'] . "\n"
            . 'stale_marker=' . $readModel['stale_marker'] . "\n"
            . 'stale_reason=' . $readModel['stale_reason'] . "\n"
            . 'stale_commit_sha=' . $readModel['stale_commit_sha'] . "\n"
            . 'rebuild_reason=' . $readModel['rebuild_reason'] . "\n"
            . 'commits_capability=' . $readModel['commits_capability'] . "\n"
            . 'rebuild_required=' . ($readModel['status'] === 'ready' ? 'no' : 'yes') . "\n"
            . 'task_queue_status=' . $taskQueue['status'] . "\n"
            . 'task_queue_queued=' . $taskQueue['queued'] . "\n"
            . 'task_queue_running=' . $taskQueue['running'] . "\n"
            . 'task_queue_failed=' . $taskQueue['failed'] . "\n"
            . 'rebuild_task_status=' . $taskQueue['rebuild_task_status'] . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function collectState(): array
    {
        $snapshot = $this->operatorStatus()->collect();
        $readModel = $snapshot['read_model'];
        $rowCounts = [];

        if ($readModel['database_exists']) {
            try {
                $pdo = $this->routeServices->pdo();
                $rowCounts = $this->readModelRowCounts($pdo);
            } catch (\Throwable) {
                $rowCounts = [];
            }
        }

        $overallStatus = $readModel['freshness_status'] === 'ready' ? 'ready' : 'stale';
        if ($readModel['lock_status'] === 'locked') {
            $overallStatus = 'locked';
        } elseif (!$readModel['database_exists'] || !$readModel['metadata_readable']) {
            $overallStatus = 'configuration issue';
        }

        return [
            'overall_status' => $overallStatus,
            'app_version' => ReadModelMetadata::repositoryHead($this->repositoryRoot),
            'repository' => [
                'root_label' => basename($this->repositoryRoot),
                'git_exists' => is_dir($this->repositoryRoot . '/.git') ? 'yes' : 'no',
                'records_exists' => is_dir($this->repositoryRoot . '/records') ? 'yes' : 'no',
                'head' => $readModel['current_repository_head'],
                'short_head' => ReadModelMetadata::repositoryShortCommit($this->repositoryRoot),
                'latest_commit' => ReadModelMetadata::latestRepositoryCommit($this->repositoryRoot),
            ],
            'read_model' => [
                'database_label' => basename($this->databasePath),
                'database_exists' => $readModel['database_exists'] ? 'yes' : 'no',
                'metadata_status' => $readModel['metadata_readable'] ? 'readable' : 'unreadable',
                'schema_version' => $readModel['schema_version'],
                'expected_schema_version' => ReadModelMetadata::SCHEMA_VERSION,
                'repository_root' => $readModel['repository_root'],
                'repository_head' => $readModel['repository_head'],
                'current_repository_head' => $readModel['current_repository_head'],
                'rebuilt_at' => $readModel['rebuilt_at'],
                'rebuild_reason' => $readModel['rebuild_reason'],
                'lock_status' => $readModel['lock_status'],
                'stale_marker' => $readModel['stale_marker'],
                'stale_reason' => $readModel['stale_reason'],
                'stale_commit_sha' => $readModel['stale_commit_sha'],
                'row_counts' => $rowCounts,
            ],
            'downloads' => [
                ['href' => '/downloads/repository.tar.gz', 'label' => 'Content repository (.tar.gz)'],
                ['href' => '/downloads/repository.zip', 'label' => 'Content repository (.zip)'],
                ['href' => '/downloads/read_model.sqlite3', 'label' => 'SQLite index database'],
            ],
        ];
    }

    private function operatorStatus(): OperatorStatusCollector
    {
        return new OperatorStatusCollector(
            $this->repositoryRoot,
            $this->databasePath,
            $this->queuePath,
            $this->executionLock,
            $this->staleMarker,
            $this->routeServices->pdo(...),
        );
    }

    /**
     * @return array<string, string>
     */
    private function readModelRowCounts(PDO $pdo): array
    {
        $counts = [];
        foreach ([
            'posts' => 'Posts',
            'threads' => 'Threads',
            'profiles' => 'Profiles',
            'username_routes' => 'Username routes',
            'activity' => 'Activity rows',
            'post_analyses' => 'Post analyses',
            'post_unicode_risks' => 'Unicode risk rows',
            'post_generated_responses' => 'Generated responses',
        ] as $table => $label) {
            if (!$this->readModelTableExists($pdo, $table)) {
                $counts[$label] = 'missing';
                continue;
            }

            $counts[$label] = (string) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
        }

        if ($this->readModelTableExists($pdo, 'profiles')) {
            $counts['Approved profiles'] = (string) $pdo->query('SELECT COUNT(*) FROM profiles WHERE is_approved = 1')->fetchColumn();
        }

        return $counts;
    }

    private function readModelTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name");
        $stmt->execute(['name' => $table]);

        return $stmt->fetchColumn() !== false;
    }
}
