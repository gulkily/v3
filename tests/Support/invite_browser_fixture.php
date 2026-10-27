<?php

declare(strict_types=1);

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
$issuer = null;
foreach (array_merge([$input['publicKey']], $input['additionalPublicKeys'] ?? []) as $publicKey) {
    $identity = $writer->linkIdentity(['public_key' => $publicKey]);
    $issuer ??= $identity;
    $path = $root . '/repository/records/approval-seeds/' . str_replace(':', '-', $identity['identity_id']) . '.txt';
    file_put_contents($path, 'Approved-Identity-ID: ' . $identity['identity_id'] . "\nSeed-Reason: isolated invitation browser fixture\n\n");
}
foreach ($input['unapprovedPublicKeys'] ?? [] as $publicKey) {
    $writer->linkIdentity(['public_key' => $publicKey]);
}
exec('git -C ' . escapeshellarg($root . '/repository') . ' add .');
exec('git -C ' . escapeshellarg($root . '/repository') . ' commit -qm "Approve isolated browser inviter"');
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repository))->rebuild();
echo json_encode($issuer, JSON_THROW_ON_ERROR);
