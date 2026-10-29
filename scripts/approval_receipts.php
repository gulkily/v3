<?php

declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Support\ApprovalReceiptMaintenance;

try {
    $args = array_slice($argv, 1);
    if ($args === [] || in_array('--help', $args, true) || in_array('-h', $args, true)) {
        echo "Usage: ./v3 approval receipts inspect|convert|readiness [--dry-run] [--json]\n"
            . "       [--repository-root=/path/repository] [--database-path=/path/cache.sqlite3]\n"
            . "Readiness is read-only: exit 2 means legacy files remain; exit 1 means blockers; 0 means live tree ready, archives undecided.\n"
            . "Inspect is read-only. Convert requires a clean Git repository and commits text replacements.\n"
            . "Preview with convert --dry-run. Retry the same convert command after interruption.\n"
            . "Original evidence is retained in the private Git recovery journal until refresh succeeds.\n";
        exit(0);
    }
    $action = array_shift($args); $options = [];
    if (!in_array($action, ['inspect', 'convert', 'readiness'], true)) throw new RuntimeException('Unknown receipt action. Use --help.');
    foreach ($args as $arg) {
        if (in_array($arg, ['--dry-run', '--json'], true)) { $options[substr($arg, 2)] = true; continue; }
        if (preg_match('/^--(repository-root|database-path)=(.+)$/D', $arg, $m)) { $options[$m[1]] = $m[2]; continue; }
        throw new RuntimeException('Unknown receipt option: ' . $arg);
    }
    if ($action !== 'convert' && isset($options['dry-run'])) throw new RuntimeException('--dry-run applies to convert only.');
    $project = dirname(__DIR__);
    $root = realpath($options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: $project . '/state/local_repository'));
    if ($root === false || !is_dir($root . '/records')) throw new RuntimeException('Repository must already exist; use --repository-root.');
    $db = $options['database-path'] ?? (getenv('FORUM_DATABASE_PATH') ?: $project . '/state/cache/post_index.sqlite3');
    $service = new ApprovalReceiptMaintenance($root, $db);
    $result = match ($action) { 'convert' => $service->convert(isset($options['dry-run'])), 'readiness' => $service->readiness(), default => $service->inspect() };
    if (isset($options['json'])) echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    else {
        echo 'Status: ' . ($result['status'] ?? 'inspected') . "\nLegacy-Files: " . $result['legacy_files'] . "\nArchive-Coverage: " . $result['archive_coverage'] . "\n";
        if (isset($result['live_repository_ready'])) echo 'Live-Repository-Ready: ' . ($result['live_repository_ready'] ? 'yes' : 'no') . "\nRetirement-Decision: " . $result['retirement_decision'] . "\n";
        if (isset($result['commit'])) echo 'Commit: ' . $result['commit'] . "\n";
        foreach ($result['receipts'] as $row) {
            echo "\nPost-Id: " . $row['post_id'] . "\nState: " . $row['state'] . "\n";
            foreach ($row['paths'] as $path) echo 'Receipt: ' . $path . "\n";
        }
        foreach ($result['blockers'] as $error) echo 'Blocker: ' . str_replace(["\r", "\n"], ' ', $error) . "\n";
    }
    exit($result['blockers'] !== [] ? 1 : (($result['live_repository_ready'] ?? true) ? 0 : 2));
} catch (Throwable $e) { fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n"); exit(1); }
