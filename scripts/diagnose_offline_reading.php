<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

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

    $siteId = SiteProfileRegistry::active()['name'];
    $repositoryRoot = (string) ($options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot)));
    $databasePath = (string) ($options['database-path'] ?? (getenv('FORUM_DATABASE_PATH') ?: LocalRepositoryBootstrap::defaultDatabasePath($projectRoot)));
    $profileDefaultRoot = $projectRoot . '/state/static_html' . ($siteId === 'zenmemes' ? '' : '_' . $siteId);
    $staticHtmlRoot = (string) ($options['static-html-root'] ?? (getenv('FORUM_STATIC_HTML_ROOT') ?: $profileDefaultRoot));
    $flagState = FeatureFlagEvaluator::forApplication($repositoryRoot, $projectRoot)
        ->evaluate(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY);
    $snapshot = inspectSnapshot($staticHtmlRoot);
    $runtime = inspectRuntime($projectRoot);
    $snapshotEndpoint = isset($options['url']) ? inspectPublicResource((string) $options['url'], '/offline/snapshot.sqlite3') : null;
    $runtimeEndpoint = isset($options['url']) ? inspectPublicResource((string) $options['url'], '/assets/sql-wasm.wasm', 'GET') : null;

    fwrite(STDOUT, "Offline reading diagnosis\n");
    fwrite(STDOUT, "Site profile: {$siteId}\n");
    fwrite(STDOUT, 'Approved-members-only: ' . ($flagState->effectiveValue ? 'enabled' : 'disabled') . " ({$flagState->source})\n");
    fwrite(STDOUT, "Repository: {$repositoryRoot} (" . (is_dir($repositoryRoot . '/records') ? 'ready' : 'missing records/') . ")\n");
    fwrite(STDOUT, "Read-model database: {$databasePath} (" . (is_file($databasePath) ? 'present' : 'missing') . ")\n");
    fwrite(STDOUT, "Static artifact root: {$staticHtmlRoot}\n");
    fwrite(STDOUT, 'Active static release: ' . ($snapshot['release'] ?? 'missing') . "\n");
    fwrite(STDOUT, 'Local offline snapshot: ' . snapshotDescription($snapshot) . "\n");
    fwrite(STDOUT, 'Local SQLite runtime: ' . runtimeDescription($runtime) . "\n");

    if ($snapshotEndpoint !== null && $runtimeEndpoint !== null) {
        fwrite(STDOUT, "Public snapshot URL: {$snapshotEndpoint['url']}\n");
        fwrite(STDOUT, 'Public snapshot response: ' . responseDescription($snapshotEndpoint) . "\n");
        fwrite(STDOUT, "Public SQLite runtime URL: {$runtimeEndpoint['url']}\n");
        fwrite(STDOUT, 'Public SQLite runtime response: ' . responseDescription($runtimeEndpoint) . "\n");
    }

    fwrite(STDOUT, "\nAssessment:\n");
    $problems = assessment($flagState->effectiveValue, $snapshot, $runtime, $snapshotEndpoint, $runtimeEndpoint);
    foreach ($problems as $problem) {
        fwrite(STDOUT, "- {$problem}\n");
    }

    fwrite(STDOUT, "\nNext command:\n");
    fwrite(STDOUT, buildCommand($siteId, $staticHtmlRoot) . "\n");
    if ($snapshotEndpoint === null) {
        fwrite(STDOUT, "Re-run with --url=https://your-public-domain to check the anonymous live endpoints.\n");
    }

    exit(hasFailure($flagState->effectiveValue, $snapshot, $runtime, $snapshotEndpoint, $runtimeEndpoint) ? 2 : 0);
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

        foreach (['url', 'repository-root', 'database-path', 'static-html-root'] as $name) {
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

/** @return array{release:?string,path:?string,size:?int,sqlite:bool} */
function inspectSnapshot(string $staticHtmlRoot): array
{
    $currentPath = $staticHtmlRoot . '/current';
    clearstatcache(true, $currentPath);
    $release = realpath($currentPath);
    if ($release === false || !is_dir($release)) {
        return ['release' => null, 'path' => null, 'size' => null, 'sqlite' => false];
    }

    $path = $release . '/offline/snapshot.sqlite3';
    if (!is_file($path)) {
        return ['release' => $release, 'path' => null, 'size' => null, 'sqlite' => false];
    }

    $handle = fopen($path, 'rb');
    $header = $handle === false ? false : fread($handle, 16);
    if ($handle !== false) {
        fclose($handle);
    }

    return [
        'release' => $release,
        'path' => $path,
        'size' => filesize($path) ?: 0,
        'sqlite' => $header === "SQLite format 3\000",
    ];
}

/** @return array{path:string,size:?int} */
function inspectRuntime(string $projectRoot): array
{
    $path = $projectRoot . '/public/assets/sql-wasm.wasm';

    return ['path' => $path, 'size' => is_file($path) ? (filesize($path) ?: 0) : null];
}

/** @return array{url:string,status:?int} */
function inspectPublicResource(string $baseUrl, string $path, string $method = 'HEAD'): array
{
    $url = publicUrl($baseUrl, $path);
    $headers = "Connection: close\r\n";
    if ($method === 'GET') {
        $headers .= "Range: bytes=0-0\r\n";
    }
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'timeout' => 10,
            'follow_location' => 0,
            'header' => $headers,
        ],
    ]);
    @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $first = $headers[0] ?? '';
    preg_match('#\s(\d{3})(?:\s|$)#', $first, $matches);

    return ['url' => $url, 'status' => isset($matches[1]) ? (int) $matches[1] : null];
}

function publicUrl(string $baseUrl, string $requiredPath): string
{
    $parts = parse_url($baseUrl);
    if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        throw new InvalidArgumentException('--url must be an http(s) origin or offline snapshot URL.');
    }

    $origin = $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $path = (string) ($parts['path'] ?? '');
    if (str_ends_with($path, $requiredPath)) {
        return $origin . $requiredPath;
    }

    return $origin . $requiredPath;
}

/** @param array{release:?string,path:?string,size:?int,sqlite:bool} $snapshot */
function snapshotDescription(array $snapshot): string
{
    if ($snapshot['path'] === null) {
        return 'missing';
    }

    return sprintf('%s, %d bytes%s', $snapshot['path'], $snapshot['size'], $snapshot['sqlite'] ? ', SQLite header valid' : ', invalid SQLite header');
}

/** @param array{path:string,size:?int} $runtime */
function runtimeDescription(array $runtime): string
{
    return $runtime['size'] === null ? 'missing (' . $runtime['path'] . ')' : $runtime['path'] . ', ' . $runtime['size'] . ' bytes';
}

/** @param array{url:string,status:?int} $response */
function responseDescription(array $response): string
{
    return $response['status'] !== null ? 'HTTP ' . $response['status'] : 'unreachable';
}

/**
 * @param array{release:?string,path:?string,size:?int,sqlite:bool} $snapshot
 * @param array{path:string,size:?int} $runtime
 * @param array{url:string,status:?int}|null $snapshotEndpoint
 * @param array{url:string,status:?int}|null $runtimeEndpoint
 * @return list<string>
 */
function assessment(bool $approvedMembersOnly, array $snapshot, array $runtime, ?array $snapshotEndpoint, ?array $runtimeEndpoint): array
{
    if ($approvedMembersOnly) {
        return ['Approved-members-only is enabled, so public offline snapshots are intentionally unavailable.'];
    }
    if ($snapshot['path'] === null || !$snapshot['sqlite']) {
        return ['The CLI-selected active release has no valid offline snapshot. Build and activate a release using the static root printed above.'];
    }
    if ($runtime['size'] === null) {
        return ['The local SQLite runtime is missing. Deploy public/assets/sql-wasm.wasm with the application assets.'];
    }
    if ($snapshotEndpoint !== null && !isSuccessful($snapshotEndpoint)) {
        return ['The CLI-selected release has a valid snapshot, but the public endpoint is not successful. The web process likely uses a different FORUM_STATIC_HTML_ROOT, site profile, or deployment checkout.'];
    }
    if ($runtimeEndpoint !== null && !isSuccessful($runtimeEndpoint)) {
        return ['The public SQLite runtime is unavailable. Deploy public/assets/sql-wasm.wasm; static publishing alone cannot provide this application asset.'];
    }
    if ($snapshotEndpoint === null) {
        return ['The CLI-selected release and SQLite runtime are valid. Public endpoints were not checked.'];
    }

    return ['The active release and anonymous public snapshot endpoint are ready.'];
}

/**
 * @param array{release:?string,path:?string,size:?int,sqlite:bool} $snapshot
 * @param array{path:string,size:?int} $runtime
 * @param array{url:string,status:?int}|null $snapshotEndpoint
 * @param array{url:string,status:?int}|null $runtimeEndpoint
 */
function hasFailure(bool $approvedMembersOnly, array $snapshot, array $runtime, ?array $snapshotEndpoint, ?array $runtimeEndpoint): bool
{
    return $approvedMembersOnly || $snapshot['path'] === null || !$snapshot['sqlite'] || $runtime['size'] === null
        || ($snapshotEndpoint !== null && !isSuccessful($snapshotEndpoint))
        || ($runtimeEndpoint !== null && !isSuccessful($runtimeEndpoint));
}

/** @param array{url:string,status:?int} $response */
function isSuccessful(array $response): bool
{
    return $response['status'] !== null && $response['status'] >= 200 && $response['status'] < 300;
}

function buildCommand(string $siteId, string $staticHtmlRoot): string
{
    return 'FORUM_SITE_ID=' . escapeshellarg($siteId)
        . ' FORUM_STATIC_HTML_ROOT=' . escapeshellarg($staticHtmlRoot)
        . ' ./v3 build-static';
}

function printUsage($stream): void
{
    fwrite($stream, <<<'TEXT'
Usage:
  ./v3 offline diagnose [--url=https://public.example] [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--static-html-root=/path/static_html]

This command is read-only. It checks the CLI-effective site profile, static
release, SQLite runtime, local offline snapshot, and optionally the anonymous
public endpoints.

TEXT);
}
