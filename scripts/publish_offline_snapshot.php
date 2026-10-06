<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Offline\OfflineSnapshotPublisher;
use ForumRewrite\PresentationPathResolver;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);

try {
    $options = parseOptions(array_slice($argv, 1));
    if (($options['help'] ?? false) === true) {
        printUsage(STDOUT);
        exit(0);
    }

    $profile = SiteProfileRegistry::active();
    $siteId = $profile['name'];
    $repositoryRoot = (string) ($options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot)));
    $databasePath = (string) ($options['database-path'] ?? (getenv('FORUM_DATABASE_PATH') ?: LocalRepositoryBootstrap::defaultDatabasePath($projectRoot)));
    $staticHtmlRoot = (string) ($options['static-html-root'] ?? (getenv('FORUM_STATIC_HTML_ROOT') ?: PresentationPathResolver::staticHtmlRoot($projectRoot, $profile)));
    $flagState = FeatureFlagEvaluator::forApplication($repositoryRoot, $projectRoot)
        ->evaluate(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY);

    if ($flagState->effectiveValue) {
        fwrite(STDERR, "Offline snapshot publication is unavailable while approved-members-only is enabled ({$flagState->source}).\n");
        exit(2);
    }

    $result = (new OfflineSnapshotPublisher($staticHtmlRoot))->publish($databasePath);
    fwrite(STDOUT, "Offline snapshot published\n");
    fwrite(STDOUT, "Site profile: {$siteId}\n");
    fwrite(STDOUT, "Read-model database: {$databasePath}\n");
    fwrite(STDOUT, "Snapshot path: {$result['path']}\n");
    fwrite(STDOUT, "Generated: {$result['generated_at']}\n");
    fwrite(STDOUT, "Threads: {$result['thread_count']}; posts: {$result['post_count']}; bytes: {$result['size_bytes']}\n");
    fwrite(STDOUT, "This uses the existing read model and does not rebuild data or render static post pages.\n");
    fwrite(STDOUT, "Next: ./v3 offline diagnose\n");
} catch (Throwable $throwable) {
    fwrite(STDERR, 'Error: ' . $throwable->getMessage() . "\n\n");
    printUsage(STDERR);
    exit(1);
}

/**
 * @param list<string> $arguments
 * @return array<string, string|bool>
 */
function parseOptions(array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if ($argument === '--help' || $argument === '-h') {
            $options['help'] = true;
            continue;
        }

        foreach (['repository-root', 'database-path', 'static-html-root'] as $name) {
            $prefix = '--' . $name . '=';
            if (!str_starts_with($argument, $prefix)) {
                continue;
            }
            $value = substr($argument, strlen($prefix));
            if ($value === '') {
                throw new InvalidArgumentException('Option requires a value: --' . $name);
            }
            $options[$name] = $value;
            continue 2;
        }

        throw new InvalidArgumentException('Unknown option: ' . $argument);
    }

    return $options;
}

function printUsage($stream): void
{
    fwrite($stream, <<<'TEXT'
Usage:
  ./v3 offline publish [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--static-html-root=/path/static_html]

Build and atomically publish the bounded, public offline SQLite snapshot from
the current read model. This does not rebuild the read model or render static
HTML pages. It is unavailable when approved-members-only is enabled.

TEXT);
}
