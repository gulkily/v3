<?php

declare(strict_types=1);

// Run with ./v3 bake-timestamps; all scripts: scripts/README.md.

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Retraction\LegacyTimestampBake;
use ForumRewrite\Support\DeploymentSettings;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$usage = "Usage: ./v3 bake-timestamps preview|run [--repository-root=/path/repository] [--json]\n\n"
    . "Writes records/post-timestamps/ for committed legacy posts that lack Created-At,\n"
    . "using the date rebuild already shows, and commits them. Clears the purge-history\n"
    . "blocker \"legacy posts lack a Created-At header\". preview changes nothing.\n";

try {
    $command = $argv[1] ?? 'preview';
    if (in_array($command, ['--help', '-h', 'help'], true)) {
        echo $usage;
        exit(0);
    }
    if (!in_array($command, ['preview', 'run'], true)) {
        throw new InvalidArgumentException('Use preview or run.');
    }
    $options = [];
    foreach (array_slice($argv, 2) as $arg) {
        if ($arg === '--json') { $options['json'] = true; continue; }
        if (preg_match('/^--(repository-root)=(.+)$/D', $arg, $m) !== 1) {
            throw new InvalidArgumentException('Unknown option: ' . $arg);
        }
        $options[$m[1]] = $m[2];
    }
    $root = rtrim($options['repository-root'] ?? (DeploymentSettings::value('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot)), '/');
    $result = $command === 'run' ? LegacyTimestampBake::run($root) : LegacyTimestampBake::plan($root);

    if ($options['json'] ?? false) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    } else {
        echo ($command === 'run' ? 'Legacy timestamp bake: complete' : 'Legacy timestamp bake: preview') . "\n";
        echo 'Repository: ' . $root . "\n";
        echo ($command === 'run' ? 'Timestamps written: ' : 'Timestamps to write: ') . count($result['baked']) . "\n";
        foreach ($result['baked'] as $entry) {
            echo '  ' . $entry['created_at'] . '  ' . $entry['path'] . "\n";
        }
        if (($result['commit'] ?? null) !== null) {
            echo 'Commit: ' . $result['commit'] . "\n";
        }
        if ($result['undated'] !== []) {
            echo 'Posts Git cannot date (left unbaked): ' . count($result['undated']) . "\n";
            foreach ($result['undated'] as $path) {
                echo '  ' . $path . "\n";
            }
        }
        if ($command === 'preview' && $result['baked'] !== []) {
            echo "Run bake-timestamps run to write and commit them.\n";
        }
    }
    exit($result['undated'] === [] ? 0 : 1);
} catch (InvalidArgumentException $error) {
    fwrite(STDERR, 'Error: ' . $error->getMessage() . "\n" . $usage);
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, 'Legacy timestamp bake failed: ' . $error->getMessage() . "\n");
    exit(1);
}
