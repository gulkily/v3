<?php

declare(strict_types=1);

// Run with ./v3 feedback; all scripts: scripts/README.md.

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Feedback\BacklogPrompt;
use ForumRewrite\Feedback\BacklogText;
use ForumRewrite\Feedback\FeedbackBacklog;
use ForumRewrite\Feedback\FeedbackIndex;
use ForumRewrite\Feedback\FeedbackItemCheck;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\Support\SourceRepository;

$projectRoot = dirname(__DIR__);
$args = array_slice($argv, 1);
$command = array_shift($args) ?? '';
$directory = $projectRoot . '/docs/feedback/items';
$options = [];
$ids = [];
foreach ($args as $arg) {
    if (str_starts_with($arg, '--dir=')) {
        $directory = substr($arg, 6);
    } elseif ($command === 'list' && preg_match('/^--(sort|order|show|kind)=(.*)$/', $arg, $m) === 1) {
        $options[$m[1]] = $m[2];
    } elseif ($command === 'start' && str_starts_with($arg, '--mode=')) {
        $options['mode'] = substr($arg, 7);
    } elseif ($command === 'start' && !str_starts_with($arg, '--')) {
        $ids[] = $arg;
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
    $contents = FeedbackIndex::render($check->items, is_file(dirname($indexPath) . '/HISTORY.md'));
    $unchanged = is_file($indexPath) && file_get_contents($indexPath) === $contents;
    if (!$unchanged) {
        FeedbackIndex::write($indexPath, $contents);
    }
    fwrite(STDOUT, ($unchanged ? 'Unchanged: ' : 'Wrote ') . $indexPath . sprintf(" (%d item(s)).\n", count($check->items)));
    exit(0);
}

if ($command === 'list' || $command === 'start') {
    $check = FeedbackItemCheck::run($directory);
    foreach ($check->problems as $problem) {
        fwrite(STDERR, 'Problem (item left out): ' . $problem . "\n");
    }
}

if ($command === 'list') {
    $words = FeedbackBacklog::words($options);
    foreach ($words['unknown'] as $unknown) {
        fwrite(STDERR, 'Unknown word, default used: ' . $unknown . "\n");
    }
    $items = FeedbackBacklog::sort(FeedbackBacklog::select($check->items, $words['show'], $words['kind']), $words['sort'], $words['order']);
    fwrite(STDOUT, BacklogText::render($items, $words, ReadModelMetadata::repositoryHead($projectRoot)));
    exit($words['unknown'] === [] ? 0 : 2);
}

if ($command === 'start') {
    if (isset($options['mode']) && !in_array($options['mode'], BacklogPrompt::MODES, true)) {
        fwrite(STDERR, 'Unknown mode: ' . $options['mode'] . ' (use ' . implode(', ', BacklogPrompt::MODES) . ")\n");
        exit(2);
    }
    $picked = BacklogPrompt::pick($check->items, $ids);
    foreach ($picked['unknown'] as $id) {
        fwrite(STDERR, 'Left out, no such item: ' . $id . "\n");
    }
    foreach ($picked['closed'] as $id) {
        fwrite(STDERR, 'Left out, already closed: ' . $id . "\n");
    }
    if ($picked['items'] === []) {
        fwrite(STDERR, "Nothing to start. Name at least one open item: ./v3 feedback start PF-YYYYMMDD-F01\n");
        exit(1);
    }
    fwrite(STDOUT, BacklogPrompt::build($picked['items'], $options['mode'] ?? BacklogPrompt::MODES[0], SourceRepository::fromProjectRoot($projectRoot)));
    exit(0);
}

fwrite($command === '--help' || $command === 'help' ? STDOUT : STDERR, "Usage:\n  ./v3 feedback check [--dir=docs/feedback/items]\n  ./v3 feedback index [--dir=docs/feedback/items]   (writes README.md beside the items folder)\n  ./v3 feedback list [--sort=salient|entered|touched|effort|impact] [--order=newest|oldest]\n                     [--show=can-start|waiting|open|<workflow>] [--kind=all|bug|feature|other]\n  ./v3 feedback start <ID>... [--mode=auto|one-shot|staged]   (prints the prompt that starts an agent; auto lets it choose)\n\nBacklog: docs/agents/backlog_to_pr.md\nFeedback items: docs/specs/feedback_item_record_v1.md\n");
exit($command === '--help' || $command === 'help' ? 0 : 2);
