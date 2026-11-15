<?php

declare(strict_types=1);

// Run with ./v3 tour; all scripts: scripts/README.md.
require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Tour\TourSnapshots;

$usage = "Usage: ./v3 tour refresh|status [--json]\n"
    . "refresh renders the pages in resources/tour.txt as logged-out snapshots and rebuilds /assets/tour/.\n"
    . "status shows the last refresh and whether a new one is due.\n";
$arguments = array_slice($argv, 1);
$json = in_array('--json', $arguments, true);
$arguments = array_values(array_filter($arguments, static fn (string $argument): bool => $argument !== '--json'));
$command = $arguments[0] ?? 'status';
if (in_array($command, ['help', '--help', '-h'], true)) { echo $usage; exit(0); }
if (!in_array($command, ['refresh', 'status'], true) || count($arguments) > 1) {
    fwrite(STDERR, ($arguments === [] || in_array($command, ['refresh', 'status'], true) ? 'Unexpected argument.' : 'Unknown tour command: ' . $command) . "\n" . $usage);
    exit(1);
}

$project = dirname(__DIR__);
$startedAt = microtime(true);
$progress = $command === 'refresh'
    ? static function (string $message) use ($startedAt): void { fwrite(STDERR, sprintf('[%6.2fs] %s' . "\n", microtime(true) - $startedAt, $message)); }
    : null;
$snapshots = TourSnapshots::forProject($project, $progress);

try {
    if ($command === 'refresh') {
        $result = $snapshots->refresh();
        if ($json) { echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"; exit($result['outcome'] === 'already_running' ? 2 : 0); }
        if ($result['outcome'] === 'already_running') { echo "A tour refresh is already running.\n"; exit(2); }
        echo 'Outcome: ' . str_replace('_', ' ', $result['outcome']) . "\n" . 'Shown: ' . $result['shown'] . "\n";
        foreach ($result['routes'] as $name => $routeResult) echo '  ' . $name . ': ' . $routeResult . "\n";
        exit(0);
    }
    $status = $snapshots->status() + ['Due' => $snapshots->needsRefresh() ? 'yes' : 'no'];
    if ($json) { echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"; exit(0); }
    if (!isset($status['State'])) echo "Never refreshed.\n";
    foreach ($status as $key => $value) if ($key !== 'Schema') echo $key . ': ' . $value . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, sprintf('Tour %s failed after %.2f seconds: %s' . "\n", $command, microtime(true) - $startedAt, $error->getMessage()));
    exit(1);
}
