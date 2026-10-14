<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/autoload.php';
$root = $argv[1];
file_put_contents($root . '/unused.php', '<?php return [];');
$data = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
foreach (['public', 'templates', 'docs', 'prompts'] as $name) { symlink(dirname(__DIR__, 2) . '/' . $name, $root . '/' . $name); }
mkdir($root . '/sessions', 0700);
\ForumRewrite\Support\LocalRepositoryBootstrap::initializeLocalRepository(dirname(__DIR__, 2), $root . '/repository');
$repo = new \ForumRewrite\Canonical\CanonicalRecordRepository($root . '/repository');
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repo))->rebuild();
$writer = new \ForumRewrite\Write\LocalWriteService($root . '/repository', $root . '/read.sqlite3', $root . '/static', $repo);
$identity = $writer->linkIdentity(['public_key' => $data['publicKey']]);
$id = $identity['identity_id'];
$seed = $root . '/repository/records/approval-seeds/openpgp-' . substr($id, 8) . '.txt';
file_put_contents($seed, 'Approved-Identity-ID: ' . $id . "\nSeed-Reason: image browser test\n\nTest.\n");
exec('git -C ' . escapeshellarg($root . '/repository') . ' add records && git -C ' . escapeshellarg($root . '/repository') . ' commit -qm ' . escapeshellarg('Approve image test identity'), $output, $code);
if ($code !== 0) { throw new RuntimeException('Unable to commit test approval.'); }
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repo))->rebuild();
echo json_encode($identity, JSON_THROW_ON_ERROR);
