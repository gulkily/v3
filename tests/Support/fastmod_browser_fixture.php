<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/autoload.php';
if (PHP_SAPI !== 'cli') exit(1);
$root = $argv[1];
$project = dirname(__DIR__, 2);
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
foreach (['templates', 'public', 'prompts'] as $directory) symlink($project . '/' . $directory, $root . '/' . $directory);
mkdir($root . '/sessions', 0700, true);
\ForumRewrite\Support\LocalRepositoryBootstrap::initializeLocalRepository($project, $root . '/repository');
$repository = new \ForumRewrite\Canonical\CanonicalRecordRepository($root . '/repository');
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repository))->rebuild();
$writer = new \ForumRewrite\Write\LocalWriteService($root . '/repository', $root . '/read.sqlite3', $root . '/static', $repository);
$identity = $writer->linkIdentity(['public_key' => $input['publicKey']])['identity_id'];
$path = $root . '/repository/records/approval-seeds/openpgp-' . substr($identity, 8) . '.txt';
file_put_contents($path, "Approved-Identity-ID: {$identity}\nSeed-Reason: browser test\n\nTest operator.\n");
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repository))->rebuild();
echo json_encode(['identity' => $identity]);
