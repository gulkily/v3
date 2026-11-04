<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Feedback\FeedbackIndex;
use ForumRewrite\Feedback\FeedbackItemCheck;

$projectRoot = dirname(__DIR__);
$args = array_slice($argv, 1);
$command = array_shift($args) ?? '';
$directory = $projectRoot . '/docs/feedback/items';
foreach ($args as $arg) {
    if (str_starts_with($arg, '--dir=')) {
        $directory = substr($arg, 6);
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        exit(2);
    }
}

if ($command === 'check') {
    $check = FeedbackItemCheck::run($directory);
    foreach ($check->problems as $problem) {
        fwrite(STDOUT, 'Problem: ' . $problem . "\n");
    }
    foreach ($check->warnings as $warning) {
        fwrite(STDOUT, 'Review: ' . $warning . "\n");
    }
    fwrite(STDOUT, sprintf("%d item(s), %d problem(s), %d to review.\n", count($check->items), count($check->problems), count($check->warnings)));
    exit($check->problems === [] ? 0 : 1);
}

if ($command === 'index') {
    $check = FeedbackItemCheck::run($directory);
    if ($check->problems !== []) {
        foreach ($check->problems as $problem) {
            fwrite(STDOUT, 'Problem: ' . $problem . "\n");
        }
        fwrite(STDOUT, "Index not written; fix the problems above (./v3 feedback check).\n");
        exit(1);
    }
    $indexPath = dirname(rtrim($directory, '/')) . '/README.md';
    $contents = FeedbackIndex::render($check->items);
    $unchanged = is_file($indexPath) && file_get_contents($indexPath) === $contents;
    if (!$unchanged) {
        FeedbackIndex::write($indexPath, $contents);
    }
    fwrite(STDOUT, ($unchanged ? 'Unchanged: ' : 'Wrote ') . $indexPath . sprintf(" (%d item(s)).\n", count($check->items)));
    exit(0);
}

fwrite($command === '--help' || $command === 'help' ? STDOUT : STDERR, "Usage:\n  ./v3 feedback check [--dir=docs/feedback/items]\n  ./v3 feedback index [--dir=docs/feedback/items]   (writes README.md beside the items folder)\n\nFeedback items: docs/specs/feedback_item_record_v1.md\n");
exit($command === '--help' || $command === 'help' ? 0 : 2);
