<?php

declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';

try {
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            echo "Usage: ./v3 agent-reply format-readiness [--repository-root=/path/repository] [--json]\n"
                . "Read-only live canonical post inspection; no initialization or rebuild.\n"
                . "Exit 0: live tree ready; 2: legacy records remain; 1: incomplete or invalid scan.\n"
                . "Stop writers for a reliable snapshot. Git history and external archives remain undecided.\n"
                . "Keep the legacy reader while old signed records remain; do not rewrite them.\n";
            exit(0);
        }
        if ($arg === '--json') { $options['json'] = true; continue; }
        if (str_starts_with($arg, '--repository-root=')) { $options['root'] = substr($arg, 18); continue; }
        throw new RuntimeException('Unknown option: ' . $arg . '. Use --help.');
    }
    $root = realpath($options['root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: dirname(__DIR__) . '/state/local_repository'));
    if ($root === false || !is_dir($root)) throw new RuntimeException('Repository must already exist; use --repository-root.');
    $result = (new ForumRewrite\Support\AgentResponseFormatReadiness($root))->inspect();
    if (isset($options['json'])) echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    else {
        $line = static function (string $key, mixed $value): void {
            echo $key . ': ' . str_replace(["\r", "\n"], ' ', (string) $value) . "\n";
        };
        $line('Status', $result['status']);
        $line('Scan-Complete', $result['scan_complete'] ? 'yes' : 'no');
        $line('Live-Repository-Ready', $result['live_repository_ready'] ? 'yes' : 'no');
        $line('Scanned-Posts', $result['scanned_posts']);
        $line('Legacy-Records', count($result['legacy_records']));
        $line('Archive-Coverage', $result['archive_coverage']);
        $line('Retirement-Decision', $result['retirement_decision']);
        foreach ($result['legacy_records'] as $row) {
            echo "\n"; $line('Legacy-Post-ID', $row['post_id'] ?? 'unknown'); $line('Path', $row['path']);
        }
        foreach ($result['dependent_expansions'] as $row) {
            echo "\n"; $line('Dependent-Expansion', $row['post_id']); $line('Path', $row['path']);
            foreach ($row['legacy_dependencies'] as $id) $line('Legacy-Dependency', $id);
        }
        foreach ($result['blockers'] as $error) $line('Blocker', $error);
    }
    exit($result['blockers'] !== [] ? 1 : ($result['live_repository_ready'] ? 0 : 2));
} catch (Throwable $error) { fwrite(STDERR, 'Error: ' . $error->getMessage() . "\n"); exit(1); }
