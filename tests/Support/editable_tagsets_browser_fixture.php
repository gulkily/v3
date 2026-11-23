<?php

declare(strict_types=1);

// Seeds an operator, an #issue thread with a closed reply and an #event thread for
// tests/browser/editable_tagsets.mjs.
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
$writer->seedApprovedIdentity($identity, 'editable tagsets operator');
$issue = $writer->createThread(['subject' => 'Agent accessibility', 'board_tags' => 'meta issue', 'body' => 'Report trouble here.'])['thread_id'];
$closed = $writer->createReply(['thread_id' => $issue, 'parent_id' => $issue, 'body' => 'Thread pages have no text version.'])['post_id'];
$writer->applyPostTag(['post_id' => $closed, 'tag' => 'closed', 'author_identity_id' => $identity]);
$event = $writer->createThread(['subject' => 'Rap club meetup', 'board_tags' => 'event', 'body' => 'Thursday at seven.'])['thread_id'];
$reply = $writer->createReply(['thread_id' => $event, 'parent_id' => $event, 'body' => 'Count me in.'])['post_id'];
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repository))->rebuild();
echo json_encode(['identity' => $identity, 'issue' => $issue, 'event' => $event, 'reply' => $reply]);
