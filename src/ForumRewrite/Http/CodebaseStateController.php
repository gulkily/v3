<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ProfileRepository;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\SiteConfig;
use ForumRewrite\Support\ExecutionLock;
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
 * than with the unrelated /api text formatters in ApiTextController.
 * commitsCapabilityAvailable() and taskQueueStatus() stay on Application
 * (the former is memoized there and shared with other routes; the latter
 * shares that memoization's task-queue-store instance) and are passed in
 * as bound closures.
 */
final class CodebaseStateController
{
    /**
     * @param \Closure(): bool $commitsCapabilityAvailable
     * @param \Closure(): array{status:string,queued:int,running:int,completed:int,failed:int} $taskQueueStatus
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly ExecutionLock $executionLock,
        private readonly ReadModelStaleMarker $staleMarker,
        private readonly \Closure $commitsCapabilityAvailable,
        private readonly \Closure $taskQueueStatus,
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
        $metadata = [];
        if (is_file($this->databasePath)) {
            try {
                $metadata = ReadModelMetadata::readMetadata($this->routeServices->pdo());
            } catch (\Throwable) {
                $metadata = [];
            }
        }

        $currentRepositoryHead = ReadModelMetadata::repositoryHead($this->repositoryRoot);
        $staleMarker = $this->staleMarker->read();
        $commitsAvailable = ($this->commitsCapabilityAvailable)();
        $status = (($metadata['repository_root'] ?? null) === $this->repositoryRoot)
            && (($metadata['schema_version'] ?? null) === ReadModelMetadata::SCHEMA_VERSION)
            && (($metadata['repository_head'] ?? null) === $currentRepositoryHead)
            && $staleMarker === null
            && $commitsAvailable
            ? 'ready'
            : 'stale';
        $taskQueue = ($this->taskQueueStatus)();

        return "status={$status}\n"
            . 'schema_version=' . ($metadata['schema_version'] ?? 'missing') . "\n"
            . 'repository_root=' . ($metadata['repository_root'] ?? 'missing') . "\n"
            . 'repository_head=' . ($metadata['repository_head'] ?? 'missing') . "\n"
            . 'current_repository_head=' . $currentRepositoryHead . "\n"
            . 'rebuilt_at=' . ($metadata['rebuilt_at'] ?? 'missing') . "\n"
            . 'lock_status=' . ($this->executionLock->isLocked() ? 'locked' : 'unlocked') . "\n"
            . 'stale_marker=' . ($staleMarker === null ? 'absent' : 'present') . "\n"
            . 'stale_reason=' . ($staleMarker['reason'] ?? 'none') . "\n"
            . 'stale_commit_sha=' . ($staleMarker['commit_sha'] ?? 'none') . "\n"
            . 'rebuild_reason=' . ($metadata['rebuild_reason'] ?? 'missing') . "\n"
            . 'commits_capability=' . ($commitsAvailable ? 'available' : 'unavailable') . "\n"
            . 'rebuild_required=' . ($status === 'ready' ? 'no' : 'yes') . "\n"
            . 'task_queue_status=' . $taskQueue['status'] . "\n"
            . 'task_queue_queued=' . $taskQueue['queued'] . "\n"
            . 'task_queue_running=' . $taskQueue['running'] . "\n"
            . 'task_queue_failed=' . $taskQueue['failed'] . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function collectState(): array
    {
        $metadata = [];
        $metadataReadable = false;
        $rowCounts = [];
        $databaseExists = is_file($this->databasePath);

        if ($databaseExists) {
            try {
                $pdo = $this->routeServices->pdo();
                $metadata = ReadModelMetadata::readMetadata($pdo);
                $metadataReadable = true;
                $rowCounts = $this->readModelRowCounts($pdo);
            } catch (\Throwable) {
                $metadata = [];
                $rowCounts = [];
            }
        }

        $currentRepositoryHead = ReadModelMetadata::repositoryHead($this->repositoryRoot);
        $staleMarker = $this->staleMarker->read();
        $readModelReady = $metadataReadable
            && (($metadata['repository_root'] ?? null) === $this->repositoryRoot)
            && (($metadata['schema_version'] ?? null) === ReadModelMetadata::SCHEMA_VERSION)
            && (($metadata['repository_head'] ?? null) === $currentRepositoryHead)
            && $staleMarker === null;
        $lockStatus = $this->executionLock->isLocked() ? 'locked' : 'unlocked';

        $overallStatus = $readModelReady ? 'ready' : 'stale';
        if ($lockStatus === 'locked') {
            $overallStatus = 'locked';
        } elseif (!$databaseExists || !$metadataReadable) {
            $overallStatus = 'configuration issue';
        }

        return [
            'overall_status' => $overallStatus,
            'app_version' => ReadModelMetadata::repositoryHead($this->repositoryRoot),
            'repository' => [
                'root_label' => basename($this->repositoryRoot),
                'git_exists' => is_dir($this->repositoryRoot . '/.git') ? 'yes' : 'no',
                'records_exists' => is_dir($this->repositoryRoot . '/records') ? 'yes' : 'no',
                'head' => $currentRepositoryHead,
                'short_head' => ReadModelMetadata::repositoryShortCommit($this->repositoryRoot),
                'latest_commit' => ReadModelMetadata::latestRepositoryCommit($this->repositoryRoot),
            ],
            'read_model' => [
                'database_label' => basename($this->databasePath),
                'database_exists' => $databaseExists ? 'yes' : 'no',
                'metadata_status' => $metadataReadable ? 'readable' : 'unreadable',
                'schema_version' => $metadata['schema_version'] ?? 'missing',
                'expected_schema_version' => ReadModelMetadata::SCHEMA_VERSION,
                'repository_root' => $metadata['repository_root'] ?? 'missing',
                'repository_head' => $metadata['repository_head'] ?? 'missing',
                'current_repository_head' => $currentRepositoryHead,
                'rebuilt_at' => $metadata['rebuilt_at'] ?? 'missing',
                'rebuild_reason' => $metadata['rebuild_reason'] ?? 'missing',
                'lock_status' => $lockStatus,
                'stale_marker' => $staleMarker === null ? 'absent' : 'present',
                'stale_reason' => $staleMarker['reason'] ?? 'none',
                'stale_commit_sha' => $staleMarker['commit_sha'] ?? 'none',
                'row_counts' => $rowCounts,
            ],
            'downloads' => [
                ['href' => '/downloads/repository.tar.gz', 'label' => 'Content repository (.tar.gz)'],
                ['href' => '/downloads/repository.zip', 'label' => 'Content repository (.zip)'],
                ['href' => '/downloads/read_model.sqlite3', 'label' => 'SQLite index database'],
            ],
        ];
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
