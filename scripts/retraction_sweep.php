<?php

declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Retraction\RetractionSweep;
use ForumRewrite\Llm\LlmExchangeDatabaseConfig;
use ForumRewrite\Support\PrivateConfig;

$root = dirname(__DIR__);
try {
    $command = $argv[1] ?? 'preview';
    $options = [];
    foreach (array_slice($argv, 2) as $arg) {
        if (!preg_match('/^--(repository-root|exchange-database-path|state-path|static-html-root|database-path|limit)=(.+)$/D', $arg, $m)) throw new InvalidArgumentException('Unknown option.');
        $options[$m[1]] = $m[2];
    }
    if (!in_array($command, ['preview', 'status', 'run'], true)) throw new InvalidArgumentException('Use preview, status or run.');
    $sweep = new RetractionSweep(
        $options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: $root . '/state/local_repository'),
        $options['exchange-database-path'] ?? LlmExchangeDatabaseConfig::path($root, PrivateConfig::load($root)),
        $options['state-path'] ?? $root . '/state/private/retraction_sweep.json',
        [$options['static-html-root'] ?? (getenv('FORUM_STATIC_HTML_ROOT') ?: $root . '/state/static_html'), getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: $root . '/public'],
    );
    $limit = $options['limit'] ?? '250';
    if (!ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 10000) throw new InvalidArgumentException('Limit must be 1–10000.');
    echo json_encode(match ($command) { 'run' => $sweep->run((int) $limit), 'status' => $sweep->state(), default => $sweep->preview((int) $limit) }, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, "Retraction sweep failed: " . $error->getMessage() . "\n");
    exit(1);
}
