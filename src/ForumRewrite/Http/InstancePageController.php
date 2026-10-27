<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\SiteConfig;
use ForumRewrite\Tools\ToolsPageSupport;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use PDO;
use RuntimeException;

/**
 * Second Phase 2 slice of the Application.php decomposition (see
 * docs/plans/codebase_cleanup_audit_plan_v1.md and
 * docs/plans/codebase_cleanup_audit_findings_v1.md) - the /instance,
 * /backup, /tools/backup page and the three /downloads/* handlers, deferred
 * from slice 1 once their real shared-business-logic coupling
 * (fetchActivity(), shared with /activity) was found.
 *
 * fetchActivity() stays on Application (still shared with the un-extracted
 * /activity route) and is passed in as a bound closure rather than moved,
 * same seam AboutPageController used for renderPageTemplate() in slice 1.
 */
final class InstancePageController
{
    private const BACKUP_PREVIEW_LIMIT = 5;

    /**
     * @param \Closure(string, string, string, ?array): array $fetchActivity
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly string $projectRoot,
        private readonly \Closure $fetchActivity,
    ) {
    }

    public function renderBackup(): string
    {
        $imageStore = new \ForumRewrite\Images\ImageStore($this->repositoryRoot);
        $imageBackupAvailable = $this->backupEnabled(FeatureFlagRegistry::IMAGE_BACKUP_ENABLED) && (is_dir($imageStore->root) || \ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator::forApplication($this->repositoryRoot, $this->projectRoot)->isEnabled(\ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry::IMAGE_CONTENT_ENABLED));
        $revision = ReadModelMetadata::repositoryHead($this->repositoryRoot);
        $revisionQuery = $imageBackupAvailable ? '?revision=' . rawurlencode($revision) : '';
        return $this->routeServices->renderPageTemplate(
            'instance.php',
            [
                'imageBackupAvailable' => $imageBackupAvailable,
                'imageBackupHref' => '/downloads/images.tar' . $revisionQuery,
                'toolNavOptions' => ToolsPageSupport::navOptions('backup'),
                'downloads' => $this->backupEnabled(FeatureFlagRegistry::TEXT_BACKUP_ENABLED) ? [
                    [
                        'href' => '/downloads/repository.tar.gz' . $revisionQuery,
                        'label' => 'Content (.tar.gz)',
                    ],
                    [
                        'href' => '/downloads/repository.zip' . $revisionQuery,
                        'label' => 'Content (.zip)',
                    ],
                    [
                        'href' => '/downloads/read_model.sqlite3',
                        'label' => 'SQLite index (.sqlite3)',
                    ],
                ] : [],
            ],
            'Backup',
            'tools',
        );
    }

    public function downloadRepository(string $method, string $format, ?string $revision = null): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendHtml($this->methodNotAllowedPage(), 405);
            return;
        }

        $this->downloadImageBackup($method, $format, $revision);
    }

    public function downloadImageBackup(string $method, string $kind = 'images', ?string $revision = null): void
    {
        if ($method !== 'GET') { $this->routeServices->sendText("Only GET is supported.\n", 405); return; }
        if (!$this->allowBackup($kind === 'images' ? FeatureFlagRegistry::IMAGE_BACKUP_ENABLED : FeatureFlagRegistry::TEXT_BACKUP_ENABLED)) { return; }
        try {
            $download = (new \ForumRewrite\Images\ImageBackup($this->repositoryRoot, $this->databasePath))->export($kind, $revision);
        } catch (\ForumRewrite\Images\ArchiveBusyException $error) {
            $this->routeServices->sendText($error->getMessage() . "\n", 503, ['Cache-Control: private, no-store', 'Retry-After: 5']);
            return;
        } catch (\ForumRewrite\Images\ArchiveConflictException $error) {
            $this->routeServices->sendText("Repository changed. Refresh Backup and download both artifacts again.\n", 409, ['Cache-Control: private, no-store']);
            return;
        } catch (RuntimeException $error) {
            error_log('Archive export failed: ' . $error->getMessage());
            $this->routeServices->sendText("Backup could not be prepared. Ask the site operator to check archive storage and source integrity, then retry.\n", 500, ['Cache-Control: private, no-store']);
            return;
        }
        if (!headers_sent()) { header('Cache-Control: private, no-store'); }
        $type = $kind === 'images' ? 'application/x-tar' : ($kind === 'zip' ? 'application/zip' : 'application/gzip');
        try {
            $this->routeServices->sendDownloadStream($download['stream'], $type, SiteConfig::siteName() . '-' . ($kind === 'images' ? 'images' : 'repository') . '-' . $download['revision'] . '.' . $download['extension']);
        } finally {
            fclose($download['stream']);
        }
    }

    public function downloadReadModelDatabase(string $method): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendHtml($this->methodNotAllowedPage(), 405);
            return;
        }

        if (!$this->allowBackup(FeatureFlagRegistry::TEXT_BACKUP_ENABLED)) { return; }

        if (!is_file($this->databasePath)) {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage('Not Found', 'Not Found', 'Read-model database is not available yet.', 'instance'),
                404
            );
            return;
        }

        $this->routeServices->sendDownload($this->databasePath, 'application/x-sqlite3', SiteConfig::siteName() . '-read-model.sqlite3');
    }

    public function downloadSqliteQueryCatalog(string $method): void
    {
        if ($method !== 'GET') {
            $this->routeServices->sendHtml($this->methodNotAllowedPage(), 405);
            return;
        }

        $path = $this->projectRoot . '/public/assets/sqlite_query_catalog.sql';
        if (!is_file($path)) {
            $this->routeServices->sendHtml(
                $this->routeServices->renderMessagePage('Not Found', 'Not Found', 'SQLite query catalog is not available yet.', 'instance'),
                404
            );
            return;
        }

        $this->routeServices->sendDownload($path, 'application/sql; charset=utf-8', SiteConfig::siteName() . '-sqlite-query-catalog.sql');
    }

    /**
     * @return array{generated_at:string,repository_head:string,items:array<int,array<string,mixed>>}
     */
    private function fetchBackupSnapshot(): array
    {
        $metadata = [];
        $pdo = $this->routeServices->pdo();
        if ($this->readModelTableExists($pdo, 'metadata')) {
            $rows = $pdo->query('SELECT key, value FROM metadata')->fetchAll();
            foreach ($rows as $row) {
                $metadata[(string) $row['key']] = (string) $row['value'];
            }
        }

        return [
            'generated_at' => $metadata['rebuilt_at'] ?? '',
            'repository_head' => $metadata['repository_head'] ?? ReadModelMetadata::repositoryHead($this->repositoryRoot),
            'items' => array_map(
                fn (array $item): array => $item + ['link' => $this->routeServices->activityService()->activityItemLink($item)],
                array_slice(($this->fetchActivity)('content', 'date', 'desc')['items'], 0, self::BACKUP_PREVIEW_LIMIT),
            ),
        ];
    }

    private function readModelTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name");
        $stmt->execute(['name' => $table]);

        return $stmt->fetchColumn() !== false;
    }

    private function backupEnabled(string $flag): bool
    {
        return FeatureFlagEvaluator::forApplication($this->repositoryRoot, $this->projectRoot)->isEnabled($flag);
    }

    private function allowBackup(string $flag): bool
    {
        if ($this->backupEnabled($flag)) { return true; }
        $this->routeServices->sendText("Backup unavailable.\n", 403, ['Cache-Control: private, no-store']);
        return false;
    }

    private function methodNotAllowedPage(): string
    {
        return $this->routeServices->renderMessagePage('Method Not Allowed', 'Method Not Allowed', 'Only GET is supported for downloads.', 'none');
    }

}
