<?php

declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';


$root = dirname(__DIR__);
try {
    $command = $argv[1] ?? 'preview';
    if (in_array($command, ['--help', '-h', 'help'], true)) {
        echo "Usage: ./v3 retraction-sweep preview|run|status [--limit=250] [--repository-root=PATH] [--static-html-root=PATH] [--exchange-database-path=PATH] [--state-path=PATH]\n";
        exit(0);
    }
    $options = [];
    foreach (array_slice($argv, 2) as $arg) {
        if (!preg_match('/^--(repository-root|exchange-database-path|state-path|static-html-root|limit)=(.+)$/D', $arg, $m)) throw new InvalidArgumentException('Unknown option.');
        $options[$m[1]] = $m[2];
    }
    if (!in_array($command, ['preview', 'status', 'run'], true)) throw new InvalidArgumentException('Use preview, status or run.');
    $sweep = \ForumRewrite\Retraction\RetractionSweepFactory::create(
        $root,
        $options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: $root . '/state/local_repository'),
        $options['static-html-root'] ?? (getenv('FORUM_STATIC_HTML_ROOT') ?: \ForumRewrite\PresentationPathResolver::staticHtmlRoot($root, \ForumRewrite\SiteProfileRegistry::active())),
        $options['exchange-database-path'] ?? null,
        $options['state-path'] ?? null,
    );
    $limit = $options['limit'] ?? '250';
    if (!ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 10000) throw new InvalidArgumentException('Limit must be 1–10000.');
    echo json_encode(match ($command) { 'run' => $sweep->run((int) $limit), 'status' => $sweep->state(), default => $sweep->preview((int) $limit) }, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Retraction sweep failed: " . $error->getMessage() . "\n");
    exit(1);
}
