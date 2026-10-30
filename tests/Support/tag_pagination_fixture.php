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
for ($i = 0; $i < 65; $i++) {
    $id = sprintf('paging-%03d', $i);
    $body = 'Thread ' . $i . ' ' . str_repeat('LongUnbrokenValue', 15);
    file_put_contents($root . '/repository/records/posts/' . $id . '.txt',
        "Post-ID: $id\nCreated-At: 2026-10-30T12:00:00Z\nBoard-Tags: paging news\nSubject: Paging $i\n\n$body\n");
}
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', new \ForumRewrite\Canonical\CanonicalRecordRepository($root . '/repository')))->rebuild();
$publisher = new \ForumRewrite\Host\StaticArtifactReleasePublisher($root, $root . '/repository', $root . '/static');
$publisher->activate($publisher->build($root . '/read.sqlite3'));
echo json_encode(['themes' => array_values(array_unique(array_merge(
    array_filter(\ForumRewrite\SiteProfileRegistry::active()['permittedThemes'], static fn ($v) => $v !== 'auto'),
    ['qdb', 'chouse', 'mitrapclub']
)))]);
