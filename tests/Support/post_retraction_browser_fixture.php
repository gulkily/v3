<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/autoload.php';
if (PHP_SAPI !== 'cli') exit(1);
$root = $argv[1];
$project = dirname(__DIR__, 2);
$keys = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
symlink($project . '/templates', $root . '/templates');
symlink($project . '/public', $root . '/public');
mkdir($root . '/sessions', 0700, true);
\ForumRewrite\Support\LocalRepositoryBootstrap::initializeLocalRepository($project, $root . '/repository');
$repository = new \ForumRewrite\Canonical\CanonicalRecordRepository($root . '/repository');
$writer = new \ForumRewrite\Write\LocalWriteService($root . '/repository', $root . '/read.sqlite3', $root . '/static', $repository);
$identities = [];
foreach ($keys as $role => $key) $identities[$role] = $writer->linkIdentity(['public_key' => $key['publicKey']])['identity_id'];
$actor = $identities['operator'];
$path = \ForumRewrite\Canonical\CanonicalPathResolver::approvalSeed(substr($actor, 8));
file_put_contents($root . '/repository/' . $path, "Approved-Identity-ID: {$actor}\nSeed-Reason: browser fixture\n\n");
exec('git -C ' . escapeshellarg($root . '/repository') . ' add ' . escapeshellarg($path), $out, $code);
if ($code !== 0) throw new RuntimeException('Cannot stage fixture seed');
exec('git -C ' . escapeshellarg($root . '/repository') . ' commit -qm "Approve browser operator"', $out, $code);
if ($code !== 0) throw new RuntimeException('Cannot commit fixture seed');
$posts = [];
foreach (['author', 'operator', 'partial'] as $scenario) {
    $posts[$scenario] = $writer->prepareThread(['board_tags' => 'general', 'subject' => 'Retraction browser ' . $scenario,
        'body' => 'Browser prose ' . $scenario, 'author_identity_id' => $identities['author']]);
}
echo json_encode(['identities' => $identities, 'posts' => $posts]);
