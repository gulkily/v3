<?php

declare(strict_types=1);

// Seeds a #meta thread with two replies and an approved viewer for tests/browser/tag_unlocks.mjs.
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
$writer->seedApprovedIdentity($identity, 'tag unlocks browser viewer');
$thread = $writer->createThread(['subject' => 'Agent accessibility', 'board_tags' => 'meta', 'body' => 'Report trouble here.'])['thread_id'];
$first = $writer->createReply(['thread_id' => $thread, 'parent_id' => $thread, 'body' => "Image attach fails from the API.\nIt returns a 500 when the file is a PNG.", 'author_identity_id' => $identity])['post_id'];
// No author: an anonymous reply, so the two replies do not merge into one same-author run.
$second = $writer->createReply(['thread_id' => $thread, 'parent_id' => $thread, 'body' => 'Thread pages have no text version. Appending .txt to the URL returns 404 and ?format=txt returns the HTML page.'])['post_id'];
$writer->applyPostTag(['post_id' => $second, 'tag' => 'closed', 'author_identity_id' => $identity]);
$third = $writer->createReply(['thread_id' => $thread, 'parent_id' => $thread, 'body' => 'Deploy from the panel times out on large rebuilds.'])['post_id'];
$writer->applyPostTag(['post_id' => $third, 'tag' => 'claimed', 'author_identity_id' => $identity]);
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repository))->rebuild();
echo json_encode(['identity' => $identity, 'thread' => $thread, 'first' => $first, 'second' => $second, 'third' => $third]);
