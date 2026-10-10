<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Import\ContentImportRunner;
use ForumRewrite\Import\ImportedContentPublisher;
use ForumRewrite\Import\InstanceArchiveDownloader;
use ForumRewrite\Import\RepositoryArchive;
use ForumRewrite\Import\InstanceSourceResolver;
use ForumRewrite\PresentationPathResolver;
use ForumRewrite\SiteProfileRegistry;

$projectRoot = dirname(__DIR__);
$workspace = null;
$exitCode = 1;
try {
    $options = importInstanceOptions(array_slice($argv, 1));
    if (isset($options['help'])) {
        fwrite(STDOUT, importInstanceUsage());
        exit(0);
    }
    $source = $options['source'] ?? null;
    $resume = isset($options['resume']);
    if (($source === null) === !$resume || ($resume && isset($options['dry-run']))) {
        throw new RuntimeException('Supply one source URL, or --resume without a source/preview.');
    }
    if ($source !== null) {
        $source = (new InstanceSourceResolver())->resolve($source, $options['sources'] ?? null);
    }
    $repository = realpath($options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: $projectRoot . '/state/local_repository'));
    if ($repository === false || !is_dir($repository . '/records') || !is_dir($repository . '/.git')) {
        throw new RuntimeException('Destination repository does not exist; initialize it or supply --repository-root.');
    }
    $database = importAbsolutePath($options['database-path'] ?? (getenv('FORUM_DATABASE_PATH') ?: $projectRoot . '/state/cache/post_index.sqlite3'));
    $static = importAbsolutePath($options['static-html-root'] ?? (getenv('FORUM_STATIC_HTML_ROOT') ?: PresentationPathResolver::staticHtmlRoot($projectRoot, SiteProfileRegistry::active())));
    $progress = static function (string $message): void { fwrite(STDOUT, importTerminalText($message) . "\n"); };
    $progress('Source: ' . ($source ?? 'saved interrupted import'));
    $progress('Destination: ' . $repository);
    $progress('Database: ' . $database);
    $progress('Static/offline root: ' . $static);
    $root = null;
    $excluded = [];
    if ($source !== null) {
        $workspace = sys_get_temp_dir() . '/forum-instance-import-' . bin2hex(random_bytes(12));
        if (!mkdir($workspace, 0700)) {
            throw new RuntimeException('Unable to create private download workspace.');
        }
        $archive = $workspace . '/repository.tar.gz';
        $progress('Downloading repository archive...');
        $bytes = (new InstanceArchiveDownloader(progress: $progress))->download($source, $archive);
        $progress('Downloaded ' . $bytes . ' bytes; validating and extracting archive...');
        $extracted = (new RepositoryArchive())->extract($archive, $workspace . '/source');
        $root = $extracted['root'];
        $excluded = $extracted['excluded_archive_categories'];
    }
    $publisher = new ImportedContentPublisher($projectRoot, $repository, $database, $static, $progress);
    $progress(isset($options['dry-run']) ? 'Previewing content merge...' : 'Importing content and publishing local views...');
    $result = (new ContentImportRunner($repository, $database, progress: $progress, publicationContext: ['static_root' => $static, 'site_profile' => SiteProfileRegistry::active()['name']]))->run($root, $publisher->publishWhileLocked(...), isset($options['dry-run']), $source ?? '', $excluded);
    $progress(($result['preview'] ? 'Preview' : 'Import') . ' result: ' . $result['status']);
    foreach ($result['counts'] as $category => $count) {
        $progress($category . ': ' . $count);
    }
    foreach ($result['excluded_archive_categories'] as $category => $count) {
        $progress('Excluded archive category ' . $category . ': ' . $count);
    }
    foreach ($result['entries'] as $path => $entry) {
        if (in_array($entry['state'], ['conflict', 'invalid', 'unsupported', 'excluded'], true)) {
            $progress($entry['state'] . ': ' . $path . ' — ' . $entry['reason']);
        }
    }
    if (isset($result['review_path'])) {
        $progress('Review: ' . $result['review_path'] . '/report.json');
    }
    $exitCode = $result['status'] === 'complete' ? 0 : 2;
} catch (Throwable $error) {
    fwrite(STDERR, 'Import failed: ' . importTerminalText($error->getMessage()) . "\n");
    fwrite(STDERR, "If a merge was interrupted, use --resume with the same destination options.\n");
} finally {
    if ($workspace !== null && is_dir($workspace)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workspace, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) { rmdir($item->getPathname()); } else { unlink($item->getPathname()); }
        }
        rmdir($workspace);
    }
}
exit($exitCode);

function importInstanceOptions(array $arguments): array
{
    $options = [];
    foreach ($arguments as $argument) {
        if (in_array($argument, ['--help', '-h', '--dry-run', '--resume'], true)) {
            $options[$argument === '-h' ? 'help' : substr($argument, 2)] = true;
        } elseif (preg_match('/^--(repository-root|database-path|static-html-root|sources)=(.+)$/D', $argument, $match)) {
            if (isset($options[$match[1]])) { throw new RuntimeException('Duplicate option: --' . $match[1]); }
            $options[$match[1]] = $match[2];
        } elseif (!str_starts_with($argument, '-') && !isset($options['source'])) {
            $options['source'] = $argument;
        } else {
            throw new RuntimeException('Unknown or duplicate argument: ' . $argument);
        }
    }
    return $options;
}

function importAbsolutePath(string $path): string
{
    $absolute = str_starts_with($path, '/') ? $path : getcwd() . '/' . $path;
    $parts = [];
    foreach (explode('/', $absolute) as $part) {
        if ($part === '..') { array_pop($parts); } elseif ($part !== '' && $part !== '.') { $parts[] = $part; }
    }
    return '/' . implode('/', $parts);
}

function importInstanceUsage(): string
{
    return <<<'TEXT'
Usage:
  ./v3 import-instance <name|hostname|url> [--sources=/private/instances.json] [--dry-run]
  ./v3 import-instance --resume
  Options: --repository-root=/path/repository --database-path=/path/index.sqlite3
           --static-html-root=/path/static_html

Download and merge public forum content, preserving local settings and authority.
Preview does not change destination content. Conflicts retain the local version.
Names require an explicit JSON mapping: {"community": "https://forum.example/base"}
Hostnames default to HTTPS; use an explicit http:// URL when needed.
Limits: 256 MiB download, 1 GiB expanded, 100,000 entries, 120 seconds, 5 redirects.
Excludes private data, authority, instance settings, source history, and derived databases.
Legacy posts without explicit timestamps and unsupported records are reported, not rewritten.
Resume requires the original repository/database/static-root/site-profile configuration.
Exit codes: 0 complete supported-content merge (or preview), 2 partial/review required, 1 failed.

TEXT;
}

function importTerminalText(string $value): string
{
    return preg_replace('/[\x00-\x1f\x7f]/', '?', $value) ?? '';
}
