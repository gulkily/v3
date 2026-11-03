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
$writer->seedApprovedIdentity($identity, 'post signals browser viewer');
$prior = $writer->linkIdentity(['public_key' => $input['priorPublicKey']])['identity_id'];
$writer->seedApprovedIdentity($prior, 'post signals browser prior actor');
$writer->applyThreadTag(['thread_id' => 'root-001', 'tag' => 'like', 'author_identity_id' => $prior]);
$writer->applyPostTag(['post_id' => 'reply-001', 'tag' => 'flag', 'author_identity_id' => $prior]);
// The prior actor is an operator (seeded), so its title directive retitles root-001.
$writer->createReply(['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => "title: Signals browser title\nRetitling from a reply.", 'author_identity_id' => $prior]);
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repository))->rebuild();
echo json_encode(['identity' => $identity, 'prior' => $prior]);
