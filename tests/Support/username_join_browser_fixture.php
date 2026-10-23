<?php

declare(strict_types=1);

// Isolated repository for tests/browser/username_join_request.mjs: an operator
// approves the holder ("ilya") and the requester ("guest") keys passed on stdin.
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__, 2) . '/autoload.php';
$root = $argv[1];
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$project = dirname(__DIR__, 2);
foreach (['templates', 'public', 'docs'] as $name) symlink($project . '/' . $name, $root . '/' . $name);
mkdir($root . '/sessions', 0700);
\ForumRewrite\Support\LocalRepositoryBootstrap::initializeLocalRepository($project, $root . '/repository');
$repository = new \ForumRewrite\Canonical\CanonicalRecordRepository($root . '/repository');
$writer = new \ForumRewrite\Write\LocalWriteService($root . '/repository', $root . '/read.sqlite3', $root . '/static', $repository);
$operator = $writer->linkIdentity(['public_key' => $input['operator']]);
file_put_contents($root . '/repository/records/approval-seeds/' . str_replace(':', '-', $operator['identity_id']) . '.txt',
    'Approved-Identity-ID: ' . $operator['identity_id'] . "\nSeed-Reason: isolated username join browser fixture\n\n");
exec('git -C ' . escapeshellarg($root . '/repository') . ' add . && git -C ' . escapeshellarg($root . '/repository') . ' commit -qm "Seed operator"');
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repository))->rebuild();
$pdo = (new \ForumRewrite\ReadModel\ReadModelConnection($root . '/read.sqlite3'))->open();
$ids = [];
foreach (['holder', 'requester'] as $role) {
    $ids[$role] = $writer->linkIdentity(['public_key' => $input[$role]])['identity_id'];
    $profile = \ForumRewrite\ReadModel\ProfileRepository::byIdentityId($pdo, $ids[$role]);
    $writer->approveUser(['approver_identity_id' => $operator['identity_id'], 'target_identity_id' => $ids[$role],
        'target_profile_slug' => $profile['profile_slug'], 'thread_id' => $profile['bootstrap_thread_id'], 'parent_id' => $profile['bootstrap_post_id']]);
}
echo json_encode($ids, JSON_THROW_ON_ERROR);
