<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Docs\DocsReleaseLocator;
use ForumRewrite\Docs\DocsReleasePublisher;
use ForumRewrite\PresentationPathResolver;
use ForumRewrite\SiteProfileRegistry;

$json = in_array('--json', $argv, true);
try {
    $args = array_slice($argv, 1);
    if (in_array('--help', $args, true) || in_array('-h', $args, true) || $args === []) {
        echo "Usage: ./v3 docs publish [--json] [--repository-root=/path/records] [--static-html-root=/path/static]\n\n";
        echo "Publish or retry the complete documentation library independently of forum imports.\n";
        echo "Uses the active FORUM_SITE_ID profile. Docs live in the associated static root's -docs sibling.\n";
        echo "No forum release, read-model database or offline snapshot is replaced.\n";
        echo "For initial static docs setup or changed docs/application inputs, run this command separately.\n";
        exit(0);
    }
    if (array_shift($args) !== 'publish') {
        throw new RuntimeException('Unknown docs operation. Use ./v3 docs publish --help.');
    }
    $options = [];
    foreach ($args as $arg) {
        if ($arg === '--json') {
            continue;
        }
        if (!preg_match('/^--(repository-root|static-html-root)=(.+)$/D', $arg, $match)) {
            throw new RuntimeException('Unknown or incomplete option: ' . $arg . '. Use ./v3 docs publish --help.');
        }
        if (isset($options[$match[1]])) {
            throw new RuntimeException('Duplicate option: --' . $match[1]);
        }
        $options[$match[1]] = $match[2];
    }
    $project = dirname(__DIR__);
    $absolute = static fn (string $path): string => $path === '/' ? '/' : rtrim(str_starts_with($path, '/') ? $path : getcwd() . '/' . $path, '/');
    $repository = $absolute($options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: $project . '/state/local_repository'));
    $static = $absolute($options['static-html-root'] ?? (getenv('FORUM_STATIC_HTML_ROOT') ?: PresentationPathResolver::staticHtmlRoot($project, SiteProfileRegistry::active())));
    $locator = new DocsReleaseLocator($static);
    if (!$json) {
        echo "Publishing documentation\nDocs root: " . $locator->root() . "\n";
    }
    $progress = $json ? null : static function (string $line): void { echo $line, "\n"; };
    $release = (new DocsReleasePublisher($project, $repository, $static))->publish($progress);
    $result = ['status' => 'complete', 'docs_root' => $locator->root(), 'release' => $release,
        'version' => $locator->active()['version'], 'documents' => $locator->active()['documents']];
    echo $json ? json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n" : "Documentation published: " . $release . "\n";
} catch (Throwable $error) {
    $next = 'Correct the reported cause and retry ./v3 docs publish with the same destination/profile.';
    if ($json) {
        echo json_encode(['status' => 'error', 'error' => $error->getMessage(), 'next_action' => $next], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES), "\n";
    } else {
        fwrite(STDERR, 'Docs publication failed: ' . $error->getMessage() . "\n" . $next . "\n");
    }
    exit(1);
}
