<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Host\StaticArtifactReleasePublisher;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$repositoryRoot = $argv[1] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot));
$databasePath = $argv[2] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3'));
$staticHtmlRoot = $argv[3] ?? (getenv('FORUM_STATIC_HTML_ROOT') ?: ($projectRoot . '/state/static_html'));
$startedAt = microtime(true);

fwrite(STDOUT, "Starting shared static release refresh\n");
fwrite(STDOUT, "Repository: {$repositoryRoot}\n");
fwrite(STDOUT, "Read-model database: {$databasePath}\n");
fwrite(STDOUT, "Static artifact root: {$staticHtmlRoot}\n");
fwrite(STDOUT, "This keeps existing tag, thread, post, and profile pages. It does not rebuild the read model.\n");

try {
    $publisher = new StaticArtifactReleasePublisher($projectRoot, $repositoryRoot, $staticHtmlRoot);
    fwrite(STDOUT, "[1/2] Copying the active static release and refreshing shared pages...\n");
    $releasePath = $publisher->buildSharedRefresh(
        $databasePath,
        static function (string $message): void {
            fwrite(STDOUT, "[1/2] {$message}\n");
        },
    );
    fwrite(STDOUT, "[1/2] Shared static release is ready: {$releasePath}\n");
    fwrite(STDOUT, "[2/2] Activating the shared static release...\n");
    $publisher->activate($releasePath);
    fwrite(STDOUT, "[2/2] Shared static release activated.\n");
    fwrite(STDOUT, "Refreshed and activated shared static release {$releasePath}\n");
    fwrite(STDOUT, sprintf("Elapsed: %.3f seconds\n", microtime(true) - $startedAt));
} catch (Throwable $throwable) {
    fwrite(STDERR, sprintf("Shared static release refresh failed after %.3f seconds: %s\n", microtime(true) - $startedAt, $throwable->getMessage()));
    exit(1);
}
