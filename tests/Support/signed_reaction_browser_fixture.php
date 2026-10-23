<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';
if (PHP_SAPI !== 'cli') exit(1);
$root = $argv[1];
$project = dirname(__DIR__, 2);
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
symlink($project . '/templates', $root . '/templates');
symlink($project . '/public', $root . '/public');
mkdir($root . '/sessions', 0700, true);
\ForumRewrite\Support\LocalRepositoryBootstrap::initializeLocalRepository($project, $root . '/repository');
$repository = new \ForumRewrite\Canonical\CanonicalRecordRepository($root . '/repository');
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repository))->rebuild();
$writer = new \ForumRewrite\Write\LocalWriteService($root . '/repository', $root . '/read.sqlite3', $root . '/static', $repository);
$identity = $writer->linkIdentity(['public_key' => $input['publicKey']])['identity_id'];
if (isset($input['priorPublicKey']) && \ForumRewrite\SiteConfig::siteName() !== 'qdb') {
    $priorIdentity = $writer->linkIdentity(['public_key' => $input['priorPublicKey']])['identity_id'];
    $writer->applyThreadTag(['thread_id' => 'root-001', 'tag' => 'like', 'author_identity_id' => $priorIdentity]);
}
if (\ForumRewrite\SiteConfig::siteName() === 'qdb') {
    for ($n = 1; $n <= 2; $n++) {
        $writer->createQuote(['body' => 'A browser test quote ' . $n, 'author_identity_id' => $identity]);
    }
}
echo json_encode(['identity' => $identity]);
