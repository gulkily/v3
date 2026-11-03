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
$rebuild = static fn (): mixed => (new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repository))->rebuild();
$rebuild();
$writer = new \ForumRewrite\Write\LocalWriteService($root . '/repository', $root . '/read.sqlite3', $root . '/static', $repository);
$author = $writer->linkIdentity(['public_key' => $input['authorPublicKey']]);
$member = $writer->linkIdentity(['public_key' => $input['memberPublicKey']]);
$operator = $writer->linkIdentity(['public_key' => $input['operatorPublicKey']]);
// The operator is a committed approval seed; the member is approved by the operator and is not an operator.
$writer->seedApprovedIdentity($operator['identity_id'], 'title directive browser operator');
$rebuild();
$writer->approveUser([
    'approver_identity_id' => $operator['identity_id'],
    'target_identity_id' => $member['identity_id'],
    'target_profile_slug' => $member['profile_slug'],
    'thread_id' => $member['bootstrap_thread_id'],
    'parent_id' => $member['bootstrap_post_id'],
]);
$thread = $writer->createThread(['board_tags' => 'general', 'subject' => 'Replay original title', 'body' => 'The maintainer\'s own thread.', 'author_identity_id' => $author['identity_id']]);
$rebuild();
echo json_encode(['author' => $author['identity_id'], 'member' => $member['identity_id'], 'operator' => $operator['identity_id'], 'thread_id' => $thread['post_id']]);
