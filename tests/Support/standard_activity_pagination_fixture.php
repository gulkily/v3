<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/autoload.php';
if (PHP_SAPI !== 'cli') exit(1);
$root = $argv[1];
$project = dirname(__DIR__, 2);
symlink($project . '/templates', $root . '/templates');
symlink($project . '/public', $root . '/public');
mkdir($root . '/sessions', 0700, true);
\ForumRewrite\Support\LocalRepositoryBootstrap::initializeLocalRepository($project, $root . '/repository');
// Exercise the unchanged Commits append flow with more than 100 commits.
for ($i = 0; $i < 105; $i++) {
    exec('git -C ' . escapeshellarg($root . '/repository') . ' -c commit.gpgsign=false commit --allow-empty -m ' . escapeshellarg('Paging fixture ' . $i) . ' 2>&1', $output, $code);
    if ($code !== 0) throw new RuntimeException(implode("\n", $output));
    $output = [];
}
// One large import commit must not be repeated in every Activity entry.
for ($i = 0; $i < 205; $i++) {
    $id = sprintf('page-%03d-', $i) . str_repeat('long-path-', 10) . 'end';
    $subject = 'Pagination entry ' . sprintf('%03d', $i) . ' ' . str_repeat('LongUnbrokenValue', 12);
    file_put_contents($root . '/repository/records/posts/' . $id . '.txt', "Post-ID: $id\nCreated-At: 2026-10-23T12:00:00Z\nBoard-Tags: paging\nSubject: $subject\n\nPage body.\n");
}
mkdir($root . '/repository/records/notes', 0700, true);
for ($i = 0; $i < 2300; $i++) {
    file_put_contents($root . '/repository/records/notes/unrelated-' . $i . '.txt', "Unrelated import file.\n");
}
exec('git -C ' . escapeshellarg($root . '/repository') . ' add records 2>&1', $output, $code);
if ($code !== 0) throw new RuntimeException(implode("\n", $output));
exec('git -C ' . escapeshellarg($root . '/repository') . ' -c commit.gpgsign=false commit -m "Bulk import Activity fixture" 2>&1', $output, $code);
if ($code !== 0) throw new RuntimeException(implode("\n", $output));
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', new \ForumRewrite\Canonical\CanonicalRecordRepository($root . '/repository')))->rebuild();
echo json_encode(['themes' => array_values(array_filter(\ForumRewrite\SiteProfileRegistry::active()['permittedThemes'], static fn (string $theme): bool => $theme !== 'auto'))]);
