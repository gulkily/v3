<?php

declare(strict_types=1);

namespace ForumRewrite\Host;

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Docs\PlatformDocsCatalog;
use ForumRewrite\Docs\DocsPublication;
use ForumRewrite\Offline\PublicOfflineSnapshotBuilder;
use ForumRewrite\Qdb\QdbQuoteNumbers;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\View\FaviconRegistry;
use PDO;
use RuntimeException;

final class StaticArtifactBuilder
{
    private ?array $retractionToken = null;
    private array $metaToken = [];
    private ?array $docsInputs = null;
    private array $copiedAssets = [];
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
        private readonly string $artifactRoot,
        private readonly ?\Closure $progressReporter = null,
        private readonly ?string $docsReuseSource = null,
    ) {
    }

    public function build(): void
    {
        $builder = new ReadModelBuilder(
            $this->repositoryRoot,
            $this->databasePath,
            new CanonicalRecordRepository($this->repositoryRoot),
        );
        $builder->rebuild();

        $this->buildFromReadModel();
    }

    public function buildFromReadModel(): void
    {
        $this->copiedAssets = [];
        $this->metaToken = \ForumRewrite\Scoring\FastmodMetaPublication::token($this->projectRoot);
        $this->retractionToken = \ForumRewrite\Retraction\PublicationGuard::begin($this->databasePath, $this->repositoryRoot);
        if (!is_dir($this->artifactRoot)) {
            mkdir($this->artifactRoot, 0777, true);
        }

        $application = $this->application();

        $this->buildSharedPages($application);
        if (SiteProfileRegistry::active()['name'] === 'qdb') {
            $this->buildQdbPages($application);
        }

        $this->renderRouteBatch('tag pages', $this->fetchVisibleTagRoutes(), function (string $route) use ($application): void {
            $artifactPaths = $this->artifactPathsForRoute($route);
            $artifactPath = $artifactPaths[0] ?? null;
            if ($artifactPath === null) {
                throw new RuntimeException('Unable to resolve artifact path for route: ' . $route);
            }

            $this->writeRouteArtifact($application, $route, $artifactPath);
        });
        if ($this->prebuildDetailPages()) {
            $this->renderRouteBatch('thread pages', $this->fetchVisibleThreadIds(), function (string $threadId) use ($application): void {
                $this->writeRouteArtifact($application, '/threads/' . $threadId, $this->artifactRoot . '/threads/' . $threadId . '.html');
                $this->writeQdbNumericQuoteAlias($threadId);
            });
            $this->renderRouteBatch('post pages', $this->fetchVisiblePostIds(), function (string $postId) use ($application): void {
                $this->writeRouteArtifact($application, '/posts/' . $postId, $this->artifactRoot . '/posts/' . $postId . '.html');
            });
        } else {
            $this->reportProgress('Skipping individual thread and post pages (FORUM_STATIC_DETAIL_PAGES_ENABLED is disabled).');
        }
        $this->renderRouteBatch('profile pages', $this->fetchIds('SELECT profile_slug FROM profiles ORDER BY profile_slug'), function (string $profileSlug) use ($application): void {
            $this->writeRouteArtifact($application, '/profiles/' . $profileSlug, $this->artifactRoot . '/profiles/' . $profileSlug . '.html');
        });
        $this->reportProgress('Building public offline reading snapshot...');
        (new PublicOfflineSnapshotBuilder($this->projectRoot))->build(
            $this->databasePath,
            $this->artifactRoot . '/offline/snapshot.sqlite3',
        );
        $this->reportProgress('Fingerprinting and copying referenced assets complete.');
        $this->assertDocumentationInputs();
    }

    /**
     * Renders only the shared public routes into an existing static release.
     *
     * The caller must seed the artifact root from a complete release first, so
     * thread, post, profile, and tag artifacts remain available unchanged.
     */
    public function refreshSharedPagesFromReadModel(): void
    {
        $this->copiedAssets = [];
        $this->metaToken = \ForumRewrite\Scoring\FastmodMetaPublication::token($this->projectRoot);
        $this->retractionToken = \ForumRewrite\Retraction\PublicationGuard::begin($this->databasePath, $this->repositoryRoot);
        if (!is_dir($this->artifactRoot)) {
            throw new RuntimeException('Shared static refresh requires an existing release directory.');
        }

        $this->buildSharedPages($this->application());
        $this->assertDocumentationInputs();
    }

    /** Run only in an isolated renderer process: no visitor globals or active session. */
    public function renderHighTrafficRoute(string $route): string
    {
        if (HighTrafficRoute::canonical($route) === null || $_COOKIE !== [] || session_status() === PHP_SESSION_ACTIVE) {
            throw new RuntimeException('Shared rendering requires an eligible route and isolated session context.');
        }
        if (FeatureFlagEvaluator::forApplication($this->repositoryRoot, $this->projectRoot)
            ->isEnabled(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY)) {
            throw new RuntimeException('High-traffic rendering is unavailable on private instances.');
        }
        http_response_code(200);
        ob_start();
        try {
            $this->application()->handle('GET', $route);
            $html = (string) ob_get_contents();
        } finally { ob_end_clean(); }
        if (http_response_code() !== 200 || !str_contains(strtolower($html), '</head>')) {
            throw new RuntimeException('Only successful HTML pages can enter the shared cache.');
        }
        foreach (headers_list() as $header) {
            if (preg_match('/^(set-cookie|location):/i', $header)) throw new RuntimeException('Shared render emitted session or redirect headers.');
        }
        $script = AssetFingerprint::fingerprintedPath($this->projectRoot . '/public', '/assets/high_traffic.js');
        $html = str_replace('</head>', '<script defer src="' . htmlspecialchars($script, ENT_QUOTES) . '"></script></head>', $html);
        preg_match_all('#/assets/[A-Za-z0-9_./-]+\.[a-f0-9]{12}\.[A-Za-z0-9]+#',$this->assetReferenceMarkup($html),$references);
        $assetBytes=0;
        foreach (array_unique($references[0]) as $reference) {
            $source=AssetFingerprint::sourcePathForFingerprint($this->projectRoot . '/public',$reference);
            if ($source!==null) $assetBytes+=filesize($source);
        }
        $configuredBytes=getenv('FORUM_HIGH_TRAFFIC_BYTES');
        $assetLimit=$configuredBytes!==false && ctype_digit($configuredBytes) && (int)$configuredBytes>0 ? min(2684354560,(int)$configuredBytes) : 268435456;
        if ($assetBytes>$assetLimit) throw new RuntimeException('Referenced assets exceed cache capacity.');
        $this->copyReferencedAssets($html);
        $this->assertFingerprintReferencesAvailable($html);
        $stamp = '<meta name="v3-generated-at" content="' . gmdate('Y-m-d\TH:i:s\Z') . '">';
        return str_replace('</head>', $stamp . '</head>', $html);
    }

    private function buildSharedPages(Application $application): void
    {
        $publication = new DocsPublication($this->projectRoot, $this->repositoryRoot);
        $signature = null;
        if ($publication->isReusableContext()) {
            try {
                $signature = $publication->inputSignature();
            } catch (\RuntimeException) {
                // Unknown input trees still get a complete render, without reuse evidence.
            }
        }
        $version = $publication->version();
        $this->docsInputs = $signature === null ? null : [$signature, $version];
        PlatformDocsCatalog::withSnapshot($this->projectRoot, function () use ($application, $publication, $signature, $version): void {
            $this->renderSharedPages($application);
            if ($signature !== null && $this->docsReuseSource !== null
                && $publication->reuseInto($this->docsReuseSource, $this->artifactRoot, $signature, $version)) {
                $this->reportProgress('Reusing documentation (' . count(PlatformDocsCatalog::inventory($this->projectRoot)) . ' documents).');
                return;
            }
            $publication->clearOwned($this->artifactRoot);
            $this->renderDocumentation($application);
            if ($signature !== null) {
                $publication->recordPublication($this->artifactRoot, $signature, $version);
            }
        });
    }

    private function assertDocumentationInputs(): void
    {
        if ($this->docsInputs !== null) {
            (new DocsPublication($this->projectRoot, $this->repositoryRoot))->assertInputs(...$this->docsInputs);
        }
    }

    private function renderSharedPages(Application $application): void
    {
        $this->reportProgress('Fingerprinting and copying assets referenced by rendered pages...');
        $this->copyOfflineRuntimeFiles();
        $totalSharedPages = 10;
        $this->reportProgress("Rendering shared pages (0/{$totalSharedPages}).");
        $this->reportProgress("Rendering shared pages (1/{$totalSharedPages}): /.");
        $this->writeRouteArtifact($application, '/', $this->artifactRoot . '/index.html');
        $this->reportProgress("Rendering shared pages (2/{$totalSharedPages}): /threads/.");
        $this->writeRouteArtifacts($application, '/threads/', [
            $this->artifactRoot . '/threads.html',
            $this->artifactRoot . '/threads/index.html',
        ]);
        $this->reportProgress("Rendering shared pages (3/{$totalSharedPages}): /about/.");
        $this->writeRouteArtifacts($application, '/about/', [
            $this->artifactRoot . '/about.html',
            $this->artifactRoot . '/about/index.html',
        ]);
        $this->reportProgress("Rendering shared pages (4/{$totalSharedPages}): /instance/.");
        $this->writeRouteArtifact($application, '/instance/', $this->artifactRoot . '/instance.html');
        $this->reportProgress("Rendering shared pages (5/{$totalSharedPages}): /activity/.");
        $this->writeRouteArtifact($application, '/activity/', $this->artifactRoot . '/activity.html');
        $this->reportProgress("Rendering shared pages (6/{$totalSharedPages}): /users/.");
        $this->writeRouteArtifact($application, '/users/', $this->artifactRoot . '/users.html');
        $this->reportProgress("Rendering shared pages (7/{$totalSharedPages}): /tools/.");
        $this->writeRouteArtifacts($application, '/tools/', [
            $this->artifactRoot . '/tools.html',
            $this->artifactRoot . '/tools/index.html',
        ]);
        $this->reportProgress("Rendering shared pages (8/{$totalSharedPages}): /tools/bookmarklets/.");
        $this->writeRouteArtifact($application, '/tools/bookmarklets/', $this->artifactRoot . '/tools/bookmarklets.html');
        $this->reportProgress("Rendering shared pages (9/{$totalSharedPages}): /tools/feature-flags/.");
        $this->writeRouteArtifact($application, '/tools/feature-flags/', $this->artifactRoot . '/tools/feature-flags.html');
        $this->reportProgress("Rendering shared pages (10/{$totalSharedPages}): /tags/.");
        $this->writeRouteArtifacts($application, '/tags/', [
            $this->artifactRoot . '/tags.html',
            $this->artifactRoot . '/tags/index.html',
        ]);
        $this->reportProgress("Rendering shared pages complete ({$totalSharedPages}/{$totalSharedPages}).");
    }

    private function renderDocumentation(Application $application): void
    {
        $docsInventory = PlatformDocsCatalog::inventory($this->projectRoot);
        $browseQueries = PlatformDocsCatalog::browseQueries($docsInventory, PlatformDocsCatalog::PAGE_SIZE);
        $totalDocs = 3 + count($docsInventory) + count($browseQueries);
        $this->reportProgress("Rendering documentation (0/{$totalDocs}).");
        $this->reportProgress("Rendering documentation (1/{$totalDocs}): /docs/.");
        $this->writeRouteArtifacts($application, '/docs/', [
            $this->artifactRoot . '/docs.html',
            $this->artifactRoot . '/docs/index.html',
        ]);
        foreach (PlatformDocsCatalog::inventory($this->projectRoot) as $index => $entry) {
            $route = PlatformDocsCatalog::routeForPath($entry['path']);
            if ($route === null) {
                throw new RuntimeException('Unable to resolve platform docs route: ' . $entry['path']);
            }

            $completed = $index + 2;
            $this->reportProgress("Rendering documentation ({$completed}/{$totalDocs}): {$route}.");
            $this->writeRouteArtifact($application, $route, $this->artifactRoot . $entry['href'] . '.html');
        }
        $completed = count($docsInventory) + 2;
        $this->reportProgress("Rendering documentation ({$completed}/{$totalDocs}): /docs/_missing.html.");
        $this->writeRouteArtifact($application, '/docs/_missing.html', $this->artifactRoot . '/docs/_missing.html');
        // Browse pages let readers without JavaScript filter and page the static library;
        // anything else (search text, combined filters) is served the browser search shell.
        $completed++;
        $this->reportProgress("Rendering documentation ({$completed}/{$totalDocs}): /docs/_search.html.");
        $this->writeRouteArtifact($application, '/docs/_search.html', $this->artifactRoot . '/docs/_search.html');
        foreach ($browseQueries as $index => $browseQuery) {
            $route = '/docs/?' . http_build_query($browseQuery);
            $completed = 4 + count($docsInventory) + $index;
            $this->reportProgress("Rendering documentation ({$completed}/{$totalDocs}): {$route}.");
            $this->writeRouteArtifact($application, $route, $this->artifactRoot . PlatformDocsCatalog::browsePath($browseQuery));
        }
        foreach (PlatformDocsCatalog::inventory($this->projectRoot) as $entry) {
            $source = PlatformDocsCatalog::resolveSource($this->projectRoot, $entry['path']);
            $destination = $this->artifactRoot . '/docs/_download/' . $entry['path'] . '.bin';
            if (!is_dir(dirname($destination))) { mkdir(dirname($destination), 0777, true); }
            if ($source === null || !copy($source, $destination) || hash_file('sha256', $source) !== $entry['hash']
                || hash_file('sha256', $destination) !== $entry['hash']) {
                throw new RuntimeException('Documentation changed during publication; retry the build: ' . $entry['path']);
            }
        }
        $index = json_encode(['entries' => PlatformDocsCatalog::inventory($this->projectRoot)], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        if (file_put_contents($this->artifactRoot . '/docs/_search.json', $index) === false) {
            throw new RuntimeException('Unable to publish documentation search index.');
        }
        $this->reportProgress("Rendering documentation complete ({$totalDocs}/{$totalDocs}).");
    }

    private function buildQdbPages(Application $application): void
    {
        foreach (['/latest', '/top', '/leetness'] as $route) {
            $name = ltrim($route, '/');
            $this->writeRouteArtifacts($application, $route, [
                $this->artifactRoot . '/' . $name . '.html',
                $this->artifactRoot . '/' . $name . '/index.html',
            ]);
        }
    }

    private function prebuildDetailPages(): bool
    {
        return FeatureFlagEvaluator::forApplication($this->repositoryRoot, $this->projectRoot)
            ->isEnabled(FeatureFlagRegistry::STATIC_DETAIL_PAGES_ENABLED);
    }

    private function writeQdbNumericQuoteAlias(string $threadId): void
    {
        if (SiteProfileRegistry::active()['name'] !== 'qdb') {
            return;
        }

        $number = QdbQuoteNumbers::fromThreadId($threadId);
        if ($number === null) {
            return;
        }

        $sourcePath = $this->artifactRoot . '/threads/' . $threadId . '.html';
        $targetPath = $this->artifactRoot . '/qdb/quotes/' . $number . '.html';
        $targetDirectory = dirname($targetPath);
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
            throw new RuntimeException('Unable to create QDB numeric quote artifact directory.');
        }

        \ForumRewrite\Retraction\PublicationGuard::publish($this->retractionToken, $targetPath, function () use ($sourcePath, $targetPath): void {
            \ForumRewrite\Scoring\FastmodMetaPublication::publish($this->metaToken,$targetPath,static function () use ($sourcePath,$targetPath): void {
            if (!link($sourcePath, $targetPath) && !copy($sourcePath, $targetPath)) throw new RuntimeException('Unable to create quote alias.');
            });
        });
    }

    private function copyOfflineRuntimeFiles(): void
    {
        $workerSource = file_get_contents($this->projectRoot . '/public/service_worker.js');
        if ($workerSource === false) {
            throw new RuntimeException('Unable to read offline worker source.');
        }
        $profile = SiteProfileRegistry::active();
        $runtimeFiles = [
            '/service_worker.js' => BrowserRuntimeAssetRenderer::serviceWorker($workerSource, $profile),
            '/manifest.webmanifest' => BrowserRuntimeAssetRenderer::manifest($profile),
        ];
        foreach ($runtimeFiles as $requestPath => $contents) {
            $targetPath = $this->artifactRoot . $requestPath;
            if (!file_put_contents($targetPath, $contents)) {
                throw new RuntimeException('Unable to write static runtime file: ' . $requestPath);
            }
        }

        $faviconPaths = array_unique(['/favicon.ico', '/favicon.gif', FaviconRegistry::resolve($profile)]);
        foreach ($faviconPaths as $requestPath) {
            $sourcePath = $this->projectRoot . '/public' . $requestPath;
            if (!is_file($sourcePath)) {
                continue;
            }

            $targetPath = $this->artifactRoot . $requestPath;
            $targetDirectory = dirname($targetPath);
            if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
                throw new RuntimeException('Unable to create static runtime directory.');
            }

            if (!copy($sourcePath, $targetPath)) {
                throw new RuntimeException('Unable to copy static runtime file: ' . $requestPath);
            }
        }
    }

    public function buildSingleRoute(string $route): bool
    {
        $this->metaToken = \ForumRewrite\Scoring\FastmodMetaPublication::token($this->projectRoot);
        $this->retractionToken = \ForumRewrite\Retraction\PublicationGuard::begin($this->databasePath, $this->repositoryRoot);
        $normalizedRoute = $this->normalizeRoute($route);
        if ($normalizedRoute === null) {
            return false;
        }

        $artifactPaths = $this->artifactPathsForRoute($normalizedRoute);
        if ($artifactPaths === []) {
            return false;
        }

        return $this->writeRouteArtifactsWithLock($this->application(), $normalizedRoute, $artifactPaths);
    }

    /**
     * @return list<string>
     */
    private function fetchIds(string $sql): array
    {
        $pdo = \ForumRewrite\Scoring\FastmodMetaRead::forApplication((new ReadModelConnection($this->databasePath))->open(),$this->projectRoot,$this->repositoryRoot);
        /** @var list<string> $rows */
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function fetchVisibleThreadIds(): array
    {
        $pdo = \ForumRewrite\Scoring\FastmodMetaRead::forApplication((new ReadModelConnection($this->databasePath))->open(),$this->projectRoot,$this->repositoryRoot);
        $rows = $pdo->query('SELECT root_post_id, board_tags_json FROM threads ORDER BY root_post_id')->fetchAll();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['root_post_id'],
            array_filter($rows, fn (array $row): bool => !$this->isHiddenBootstrapBoardTagsJson((string) $row['board_tags_json']))
        ));
    }

    /**
     * @return list<string>
     */
    private function fetchVisiblePostIds(): array
    {
        $pdo = \ForumRewrite\Scoring\FastmodMetaRead::forApplication((new ReadModelConnection($this->databasePath))->open(),$this->projectRoot,$this->repositoryRoot);
        $rows = $pdo->query('SELECT post_id, board_tags_json FROM posts ORDER BY post_id')->fetchAll();

        return array_values(array_map(
            static fn (array $row): string => (string) $row['post_id'],
            array_filter($rows, fn (array $row): bool => !$this->isHiddenBootstrapBoardTagsJson((string) $row['board_tags_json']))
        ));
    }

    private function writeRouteArtifact(Application $application, string $route, string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        ob_start();
        try {
            $application->handle('GET', $route);
            $contents = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        if (str_starts_with($route, '/docs/') && http_response_code() >= 400) {
            throw new RuntimeException('Documentation rendering failed for ' . $route . '; retain the prior complete release and restore the application package.');
        }
        $this->copyReferencedAssets($contents);
        $this->assertFingerprintReferencesAvailable($contents);
        $temporaryPath = tempnam($directory, 'artifact-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create temporary artifact path in ' . $directory);
        }

        try {
            \ForumRewrite\Retraction\PublicationGuard::publish($this->retractionToken, $path, function () use ($temporaryPath, $path, $contents): void {
                \ForumRewrite\Scoring\FastmodMetaPublication::publish($this->metaToken,$path,static function () use ($temporaryPath,$path,$contents): void {
                if (file_put_contents($temporaryPath, $contents) === false) throw new RuntimeException('Unable to write temporary artifact.');
                if (!rename($temporaryPath, $path)) throw new RuntimeException('Unable to publish artifact.');
                });
            });
        } finally { if (is_file($temporaryPath)) @unlink($temporaryPath); }
    }

    /**
     * @param list<string> $paths
     */
    private function writeRouteArtifacts(Application $application, string $route, array $paths): void
    {
        ob_start();
        $application->handle('GET', $route);
        $contents = (string) ob_get_clean();

        foreach ($paths as $path) {
            $this->writeContentsArtifact($path, $contents);
        }
    }

    private function writeContentsArtifact(string $path, string $contents): void
    {
        $this->copyReferencedAssets($contents);
        $this->assertFingerprintReferencesAvailable($contents);
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $temporaryPath = tempnam($directory, 'artifact-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create temporary artifact path in ' . $directory);
        }

        try {
            \ForumRewrite\Retraction\PublicationGuard::publish($this->retractionToken, $path, function () use ($temporaryPath, $path, $contents): void {
                \ForumRewrite\Scoring\FastmodMetaPublication::publish($this->metaToken,$path,static function () use ($temporaryPath,$path,$contents): void {
                if (file_put_contents($temporaryPath, $contents) === false) throw new RuntimeException('Unable to write temporary artifact.');
                if (!rename($temporaryPath, $path)) throw new RuntimeException('Unable to publish artifact.');
                });
            });
        } finally { if (is_file($temporaryPath)) @unlink($temporaryPath); }
    }

    /**
     * @param list<string> $items
     * @param callable(string):void $renderer
     */
    private function renderRouteBatch(string $description, array $items, callable $renderer): void
    {
        $total = count($items);
        $this->reportProgress("Rendering {$description} (0/{$total}).");

        foreach ($items as $index => $item) {
            $renderer($item);
            $completed = $index + 1;
            if ($completed === $total || $completed % 25 === 0) {
                $this->reportProgress("Rendering {$description} ({$completed}/{$total}).");
            }
        }
    }

    private function reportProgress(string $message): void
    {
        if ($this->progressReporter !== null) {
            ($this->progressReporter)($message);
        }
    }

    private function assetReferenceMarkup(string $contents): string
    {
        // Prose/code examples may name nonexistent hashes. Only markup and runtime
        // script configuration reference assets that the release must actually ship.
        preg_match_all('~<script\b[^>]*>.*?</script>|<[a-z][^>]*>~is', $contents, $markup);
        return implode(' ', $markup[0]);
    }

    private function assertFingerprintReferencesAvailable(string $contents): void
    {
        if (preg_match_all('#/assets/[A-Za-z0-9_./-]+\.[a-f0-9]{12}\.[A-Za-z0-9]+#', $this->assetReferenceMarkup($contents), $matches) === false) {
            return;
        }

        foreach (array_unique($matches[0]) as $assetPath) {
            if (!is_file($this->artifactRoot . $assetPath)) {
                throw new RuntimeException('Static artifact references missing asset: ' . $assetPath);
            }
        }
    }

    private function copyReferencedAssets(string $contents): void
    {
        if (preg_match_all('#/assets/[A-Za-z0-9_./-]+\.[a-f0-9]{12}\.[A-Za-z0-9]+#', $this->assetReferenceMarkup($contents), $matches) === false) {
            return;
        }

        $sourcePaths = [];
        $copied = [];
        foreach (array_unique($matches[0]) as $fingerprintedPath) {
            if (isset($this->copiedAssets[$fingerprintedPath]) && is_file($this->artifactRoot . $fingerprintedPath)
                && !is_link($this->artifactRoot . $fingerprintedPath)) {
                continue;
            }
            $sourcePath = AssetFingerprint::sourcePathForFingerprint($this->projectRoot . '/public', $fingerprintedPath);
            if ($sourcePath === null) {
                continue;
            }
            $sourcePaths[] = substr($sourcePath, strlen($this->projectRoot . '/public'));
            $copied[$fingerprintedPath] = true;
        }

        AssetFingerprint::copyReferencedFingerprintedAssets($this->projectRoot . '/public', $this->artifactRoot, $sourcePaths);
        $this->copiedAssets += $copied;
    }

    /**
     * @param list<string> $paths
     */
    private function writeRouteArtifactsWithLock(Application $application, string $route, array $paths): bool
    {
        $lockPath = $this->lockPathForArtifact($paths[0]);
        $lockDirectory = dirname($lockPath);
        if (!is_dir($lockDirectory) && !mkdir($lockDirectory, 0777, true) && !is_dir($lockDirectory)) {
            throw new RuntimeException('Unable to create artifact lock directory: ' . $lockDirectory);
        }

        $lockHandle = fopen($lockPath, 'c+');
        if ($lockHandle === false) {
            throw new RuntimeException('Unable to open artifact lock file: ' . $lockPath);
        }

        try {
            if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
                return false;
            }

            try {
                if ($this->allArtifactPathsExist($paths)) {
                    return true;
                }

                $this->writeRouteArtifacts($application, $route, $paths);
                return true;
            } finally {
                flock($lockHandle, LOCK_UN);
            }
        } finally {
            fclose($lockHandle);
        }
    }

    private function application(): Application
    {
        return new Application(
            $this->projectRoot,
            $this->repositoryRoot,
            $this->databasePath,
            null,
            routeSource: 'static-html',
            separateDocsVersion: false,
        );
    }

    private function lockPathForArtifact(string $path): string
    {
        return $this->artifactRoot . '/.locks/' . sha1($path) . '.lock';
    }

    private function normalizeRoute(string $route): ?string
    {
        $path = parse_url($route, PHP_URL_PATH) ?: '/';
        $query = (string) (parse_url($route, PHP_URL_QUERY) ?? '');
        if ($query !== '') {
            return null;
        }

        if ($path === '' || $path === '/') {
            return '/';
        }

        if ($path === '/threads' || $path === '/threads/') {
            return '/threads/';
        }

        if ($path === '/about' || $path === '/about/') {
            return '/about/';
        }

        if ($path === '/docs' || $path === '/docs/') {
            return '/docs/';
        }

        if (str_starts_with($path, '/docs/')) {
            $source = PlatformDocsCatalog::sourceForRoute(rtrim(substr($path, 6), '/'));
            return $source !== null && PlatformDocsCatalog::resolveSource($this->projectRoot, $source) !== null
                ? PlatformDocsCatalog::routeForPath($source) : null;
        }

        if ($path === '/instance' || $path === '/instance/' || $path === '/backup' || $path === '/backup/' || $path === '/tools/backup' || $path === '/tools/backup/') {
            return '/instance/';
        }

        if ($path === '/activity' || $path === '/activity/') {
            return '/activity/';
        }

        if ($path === '/users' || $path === '/users/') {
            return '/users/';
        }

        if ($path === '/tools' || $path === '/tools/') {
            return '/tools/';
        }

        if ($path === '/tools/bookmarklets' || $path === '/tools/bookmarklets/') {
            return '/tools/bookmarklets/';
        }

        if ($path === '/tags' || $path === '/tags/') {
            return '/tags/';
        }

        if ($path === '/users/pending' || $path === '/users/pending/') {
            return null;
        }

        if (preg_match('#^/tags/([a-zA-Z0-9]+(?:-[a-zA-Z0-9]+)*)/?$#', $path, $matches) === 1) {
            return '/tags/' . strtolower($matches[1]);
        }

        if (preg_match('#^/(threads|posts|profiles)/([^/]+)/?$#', $path, $matches) !== 1) {
            return null;
        }

        $identifier = (string) $matches[2];
        if ($identifier === '' || !preg_match('/^[A-Za-z0-9._:-]+$/', $identifier)) {
            return null;
        }

        $kind = (string) $matches[1];
        if ($kind === 'threads' && !$this->threadExists($identifier)) {
            return null;
        }

        if ($kind === 'posts' && !$this->postExists($identifier)) {
            return null;
        }

        if ($kind === 'profiles' && !$this->profileExists($identifier)) {
            return null;
        }

        return '/' . $kind . '/' . $identifier;
    }

    /**
     * @return list<string>
     */
    private function artifactPathsForRoute(string $route): array
    {
        if ($route === '/') {
            return [$this->artifactRoot . '/index.html'];
        }

        if ($route === '/threads/') {
            return [
                $this->artifactRoot . '/threads.html',
                $this->artifactRoot . '/threads/index.html',
            ];
        }

        if ($route === '/about/') {
            return [
                $this->artifactRoot . '/about.html',
                $this->artifactRoot . '/about/index.html',
            ];
        }

        if ($route === '/docs/') {
            return [
                $this->artifactRoot . '/docs.html',
                $this->artifactRoot . '/docs/index.html',
            ];
        }

        if (str_starts_with($route, '/docs/')) {
            $source = PlatformDocsCatalog::sourceForRoute(substr($route, 6));
            return $source !== null && PlatformDocsCatalog::resolveSource($this->projectRoot, $source) !== null
                ? [$this->artifactRoot . PlatformDocsCatalog::routeForPath($source) . '.html'] : [];
        }

        if ($route === '/instance/') {
            return [$this->artifactRoot . '/instance.html'];
        }

        if ($route === '/activity/') {
            return [$this->artifactRoot . '/activity.html'];
        }

        if ($route === '/users/') {
            return [$this->artifactRoot . '/users.html'];
        }

        if ($route === '/tools/') {
            return [
                $this->artifactRoot . '/tools.html',
                $this->artifactRoot . '/tools/index.html',
            ];
        }

        if ($route === '/tools/bookmarklets/') {
            return [$this->artifactRoot . '/tools/bookmarklets.html'];
        }

        if ($route === '/tools/feature-flags/') {
            return [$this->artifactRoot . '/tools/feature-flags.html'];
        }

        if ($route === '/tags/') {
            return [
                $this->artifactRoot . '/tags.html',
                $this->artifactRoot . '/tags/index.html',
            ];
        }

        if (preg_match('#^/(threads|posts|profiles)/([^/]+)$#', $route, $matches) === 1) {
            return [$this->artifactRoot . '/' . $matches[1] . '/' . $matches[2] . '.html'];
        }

        if (preg_match('#^/tags/([a-z0-9]+(?:-[a-z0-9]+)*)$#', $route, $matches) === 1) {
            return [$this->artifactRoot . '/tags/' . $matches[1] . '.html'];
        }

        return [];
    }

    /**
     * @param list<string> $paths
     */
    private function allArtifactPathsExist(array $paths): bool
    {
        foreach ($paths as $path) {
            if (!is_file($path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function fetchVisibleTagRoutes(): array
    {
        $pdo = \ForumRewrite\Scoring\FastmodMetaRead::forApplication((new ReadModelConnection($this->databasePath))->open(),$this->projectRoot,$this->repositoryRoot);
        $rows = $pdo->query('SELECT board_tags_json, thread_labels_json FROM threads ORDER BY root_post_id')->fetchAll();
        $routes = [];

        foreach ($rows as $row) {
            if ($this->isHiddenBootstrapBoardTagsJson((string) $row['board_tags_json'])) {
                continue;
            }

            $tags = array_values(array_unique(array_merge(
                $this->decodeStringList((string) $row['board_tags_json']),
                $this->decodeStringList((string) $row['thread_labels_json']),
            )));

            foreach ($tags as $tag) {
                $routes['/tags/' . strtolower($tag)] = true;
            }
        }

        $paths = array_keys($routes);
        sort($paths);

        return $paths;
    }

    private function threadExists(string $threadId): bool
    {
        return $this->rowExists('SELECT 1 FROM threads WHERE root_post_id = :value LIMIT 1', $threadId);
    }

    private function postExists(string $postId): bool
    {
        return $this->rowExists('SELECT 1 FROM posts WHERE post_id = :value LIMIT 1', $postId);
    }

    private function profileExists(string $profileSlug): bool
    {
        return $this->rowExists('SELECT 1 FROM profiles WHERE profile_slug = :value LIMIT 1', $profileSlug);
    }

    private function rowExists(string $sql, string $value): bool
    {
        $pdo = \ForumRewrite\Scoring\FastmodMetaRead::forApplication((new ReadModelConnection($this->databasePath))->open(),$this->projectRoot,$this->repositoryRoot);
        $statement = $pdo->prepare($sql);
        if ($statement === false) {
            throw new RuntimeException('Unable to prepare existence query.');
        }

        $statement->execute(['value' => $value]);

        return $statement->fetchColumn() !== false;
    }

    private function isHiddenBootstrapBoardTagsJson(string $boardTagsJson): bool
    {
        $boardTags = json_decode($boardTagsJson, true);
        if (!is_array($boardTags)) {
            return false;
        }

        return in_array('identity', $boardTags, true);
    }

    /**
     * @return list<string>
     */
    private function decodeStringList(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $values = [];
        foreach ($decoded as $value) {
            if (is_string($value) && $value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }
}
