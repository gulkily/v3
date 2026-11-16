<?php

declare(strict_types=1);

// The signed-reactions fixture, then root-001 put in the prompt library: the
// browser's identity is seeded as approved and its reply's hashtag labels the thread #prompt.
require dirname(__DIR__, 2) . '/autoload.php';
if (PHP_SAPI !== 'cli') exit(1);
$root = $argv[1];
ob_start();
require __DIR__ . '/signed_reaction_browser_fixture.php';
$seeded = json_decode((string) ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
$writer->seedApprovedIdentity($seeded['identity'], 'prompt card like browser test');
$writer->createReply(['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => 'Worth keeping. #prompt', 'author_identity_id' => $seeded['identity']]);
echo json_encode($seeded);
