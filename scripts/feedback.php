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
    } elseif (in_array($command, ['claim', 'release'], true) && preg_match('/^--(site|note|post)=(.*)$/s', $arg, $m) === 1) {
        $options[$m[1]] = $m[2];
    } elseif ($command === 'claim' && $arg === '--anyway') {
        $options['anyway'] = true;
    } elseif (in_array($command, ['start', 'claim', 'release'], true) && !str_starts_with($arg, '--')) {
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

if ($command === 'claim' || $command === 'release') {
    // Claims live on a site, in its claims thread; a checkout does not know them. This reads the
    // site's /backlog/claims.txt and posts the claim or release line as a signed reply with Quill.
    $site = rtrim((string) ($options['site'] ?? ''), '/');
    $ids = BacklogPrompt::ids($ids);
    if (preg_match('#^https?://[^\s/]+(/[^\s?\#]*)?$#', $site) !== 1 || $ids === []) {
        fwrite(STDERR, "Name the site and at least one item: ./v3 feedback {$command} --site=https://example.org PF-YYYYMMDD-F01\n");
        exit(2);
    }
    $context = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true, 'header' => "Accept: text/plain\r\n"]]);
    $answer = @file_get_contents($site . '/backlog/claims.txt?items=' . implode(',', $ids), false, $context);
    $status = preg_match('#^HTTP/\S+ (\d{3})#', (string) ($http_response_header[0] ?? ''), $m) === 1 ? (int) $m[1] : 0;
    if ($answer === false || $status !== 200) {
        fwrite(STDERR, $status === 404
            ? "{$site} keeps no backlog claims. Look for a branch or pull request that names the item instead.\n"
            : "Could not read claims from {$site}" . ($status === 0 ? '' : " (HTTP {$status})") . ". Nothing was posted.\n");
        exit(1);
    }
    fwrite(STDOUT, $answer);
    $blocks = [];
    foreach (preg_split('/\n\n+/', trim($answer)) ?: [] as $text) {
        $block = [];
        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^([A-Za-z-]+): (.*)$/', $line, $m) === 1) {
                $block[$m[1]] = $m[2];
            }
        }
        $blocks[] = $block;
    }
    $thread = (string) ($blocks[0]['Claims-Thread'] ?? '');
    $byId = [];
    foreach (array_slice($blocks, 1) as $block) {
        if (isset($block['ID'])) {
            $byId[$block['ID']] = $block;
        }
    }
    if (preg_match('/^[A-Za-z0-9._-]+$/', $thread) !== 1 || $byId === []) {
        fwrite(STDERR, "No open item among " . implode(', ', $ids) . " on {$site}. Nothing was posted.\n");
        exit(1);
    }
    // V3_QUILL names another client, such as "php public/quill.php" or "perl public/quill.pl".
    $quill = getenv('V3_QUILL') ?: 'python3 ' . escapeshellarg($projectRoot . '/public/quill.py');
    $post = static function (string $parent, string $body) use ($quill, $site, $thread): int {
        $file = tempnam(sys_get_temp_dir(), 'v3-claim-');
        file_put_contents($file, $body . "\n");
        passthru($quill . ' --site ' . escapeshellarg($site) . ' reply --thread ' . escapeshellarg($thread) . ' --parent ' . escapeshellarg($parent) . ' --body-file ' . escapeshellarg($file), $code);
        unlink($file);
        return $code;
    };

    if ($command === 'claim') {
        $held = array_filter($byId, static fn (array $block): bool => ($block['Claim-State'] ?? '') === 'held');
        foreach ($held as $id => $block) {
            fwrite(STDERR, "Held: {$id} by " . ($block['Claimed-By'] ?? '?') . ' since ' . ($block['Claimed-Since'] ?? '?') . "\n");
        }
        if ($held !== [] && !isset($options['anyway'])) {
            fwrite(STDERR, "Nothing was posted. Stop and report who holds it, or claim the free items only. --anyway claims regardless.\n");
            exit(1);
        }
        $note = trim((string) ($options['note'] ?? ''));
        exit($post($thread, \ForumRewrite\Support\ClaimDirective::body(array_keys($byId)) . ($note === '' ? '' : "\n\n" . $note)) === 0 ? 0 : 1);
    }

    // A release answers the claim it ends: --post names it; otherwise the holder's claim on each item.
    $byPost = [];
    foreach ($byId as $id => $block) {
        $claim = (string) ($options['post'] ?? $block['Claim-Post'] ?? '');
        if ($claim === '') {
            fwrite(STDERR, "Nothing to release for {$id}: no counted claim. Name the claim post with --post=<post ID>.\n");
            continue;
        }
        $byPost[$claim][] = $id;
    }
    $failed = $byPost === [];
    foreach ($byPost as $claim => $released) {
        $failed = $post((string) $claim, \ForumRewrite\Support\ReleaseDirective::body($released)) !== 0 || $failed;
    }
    exit($failed ? 1 : 0);
}

fwrite($command === '--help' || $command === 'help' ? STDOUT : STDERR, "Usage:\n  ./v3 feedback check [--dir=docs/feedback/items]\n  ./v3 feedback index [--dir=docs/feedback/items]   (writes README.md beside the items folder)\n  ./v3 feedback list [--sort=salient|entered|touched|effort|impact] [--order=newest|oldest]\n                     [--show=can-start|waiting|open|<workflow>] [--kind=all|bug|feature|other]\n  ./v3 feedback start <ID>... [--mode=auto|one-shot|staged]   (prints the prompt that starts an agent; auto lets it choose)\n  ./v3 feedback claim --site=<address> <ID>... [--note=<text>] [--anyway]   (says who holds the items; posts a claim if they are free)\n  ./v3 feedback release --site=<address> <ID>... [--post=<claim post ID>]   (ends your claim on the items)\n\nBacklog: docs/agents/backlog_to_pr.md\nFeedback items: docs/specs/feedback_item_record_v1.md\n");
exit($command === '--help' || $command === 'help' ? 0 : 2);
