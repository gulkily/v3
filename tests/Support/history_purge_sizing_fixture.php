<?php

declare(strict_types=1);

/**
 * Builds a synthetic repository shaped like a large quote archive for the
 * history purge sizing gate: many dated posts with detached signatures spread
 * across many commits, then a handful of operator deletions with accepted
 * receipts. Uses `git fast-import` so tens of thousands of commits take
 * seconds. Synthetic, not derived from any production instance.
 *
 *   php tests/Support/history_purge_sizing_fixture.php <empty-dir> [--posts=80000] [--commits=4000] [--deletions=10]
 */

require dirname(__DIR__, 2) . '/autoload.php';

$target = $argv[1] ?? '';
$options = ['posts' => 80000, 'commits' => 4000, 'deletions' => 10];
foreach (array_slice($argv, 2) as $argument) {
    if (preg_match('/^--(posts|commits|deletions)=(\d+)$/', $argument, $m) !== 1) { fwrite(STDERR, "Unknown option: $argument\n"); exit(1); }
    $options[$m[1]] = (int) $m[2];
}
if ($target === '' || (file_exists($target) && count(scandir($target)) > 2)) { fwrite(STDERR, "Usage: <empty-dir> [--posts=N] [--commits=N] [--deletions=N]\n"); exit(1); }
if ($options['posts'] < 1 || $options['commits'] < 1 || $options['deletions'] > $options['posts']) { fwrite(STDERR, "Invalid sizes.\n"); exit(1); }

@mkdir($target, 0775, true);
$git = static function (array $args) use ($target): string { return \ForumRewrite\Canonical\LabelRemoval::git($target, $args); };
$git(['init', '-q', '-b', 'main']);
$git(['config', 'user.name', 'Fixture']);
$git(['config', 'user.email', 'fixture@example.invalid']);

$author = 'openpgp:' . str_repeat('f', 40);
$perCommit = (int) ceil($options['posts'] / $options['commits']);
$start = new DateTimeImmutable('2006-01-01T00:00:00Z');
$ids = [];
$sources = [];
$stream = proc_open(['git', '-C', $target, 'fast-import', '--quiet'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($stream)) { fwrite(STDERR, "Cannot start fast-import.\n"); exit(1); }
$write = static function (string $text) use ($pipes): void { fwrite($pipes[0], $text); };
$mark = 0;
$postIndex = 0;
$deleteIds = [];
for ($commit = 1; $commit <= $options['commits'] && $postIndex < $options['posts']; $commit++) {
    $when = $start->modify('+' . ($commit * 3600) . ' seconds');
    $mark++;
    $write("commit refs/heads/main\nmark :$mark\ncommitter Fixture <fixture@example.invalid> " . $when->getTimestamp() . " +0000\ndata " . strlen("batch $commit") . "\nbatch $commit\n");
    if ($mark > 1) { $write('from :' . ($mark - 1) . "\n"); }
    for ($i = 0; $i < $perCommit && $postIndex < $options['posts']; $i++, $postIndex++) {
        $id = sprintf('quote-%s-%08x', $when->format('YmdHis'), $postIndex);
        $createdAt = $when->format('Y-m-d\TH:i:s\Z');
        $path = \ForumRewrite\Canonical\CanonicalPathResolver::datedPost($id, $createdAt);
        $body = "Synthetic quote number $postIndex for the sizing gate.\n";
        $source = "Post-ID: $id\nCreated-At: $createdAt\nAuthor-Identity-ID: $author\nBoard-Tags: quotes\nSubject: Quote $postIndex\n\n$body";
        $write("M 100644 inline $path\ndata " . strlen($source) . "\n$source\n");
        $signature = "-----BEGIN PGP SIGNATURE-----\nsynthetic $id\n-----END PGP SIGNATURE-----\n";
        $write("M 100644 inline $path.asc\ndata " . strlen($signature) . "\n$signature\n");
        if (count($deleteIds) < $options['deletions'] && $postIndex % max(1, intdiv($options['posts'], max(1, $options['deletions']))) === 0) {
            $deleteIds[$id] = [$path, $source, $createdAt];
        }
    }
    $write("\n");
}
// Deletions: one commit each, removing the post and signature and accepting a receipt.
$n = 0;
foreach ($deleteIds as $id => [$path, $source, $createdAt]) {
    $n++;
    $when = $start->modify('+' . (($options['commits'] + $n) * 3600) . ' seconds');
    $receipt = \ForumRewrite\Canonical\RemovalReceipt::encode(['Format' => 'removal-acceptance-v1', 'Post-ID' => $id,
        'Operation' => 'delete', 'Authority' => 'local-operator', 'Accepted-At' => $when->format('Y-m-d\TH:i:s\Z'),
        'Actor' => 'local-operator', 'Source-SHA256' => hash('sha256', $source), 'Thread-ID' => $id, 'Parent-ID' => '',
        'Post-Created-At' => $createdAt, 'Author-Identity-ID' => $author, 'Thread-Type' => '', 'Retraction-Class' => '',
        'Retraction-Attribution' => '', 'Request-SHA256' => '', 'Signature-SHA256' => '']);
    $mark++;
    $write("commit refs/heads/main\nmark :$mark\ncommitter Fixture <fixture@example.invalid> " . $when->getTimestamp() . " +0000\ndata " . strlen("Delete $id") . "\nDelete $id\nfrom :" . ($mark - 1) . "\n");
    $write("D $path\nD $path.asc\n");
    $write("M 100644 inline records/post-removals/$id.txt\ndata " . strlen($receipt) . "\n$receipt\n\n");
}
$write("done\n");
fclose($pipes[0]);
$out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
fclose($pipes[1]); fclose($pipes[2]);
if (proc_close($stream) !== 0) { fwrite(STDERR, "fast-import failed: $err\n"); exit(1); }
$git(['checkout', '-q', 'main']);
fwrite(STDOUT, sprintf("Synthetic repository at %s: %d posts, %d commits, %d deletions with receipts.\n",
    $target, $postIndex, (int) trim($git(['rev-list', '--count', 'HEAD'])), count($deleteIds)));
