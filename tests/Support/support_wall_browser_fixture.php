<?php

declare(strict_types=1);

// Seeds a support campaign for the support wall browser check: the signed-reaction fixture,
// then a "Support ZenMemes" thread with goal 50 and pledges from several people, some approved.
// Input (stdin): {"people": [{"name": "mara", "publicKey": "...", "approved": true}, ...],
// "pledges": [["mara", "3", "filled"], ["rin", "1", ""], ...]}. Prints the thread ID.
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
$ids = [];
foreach ($input['people'] as $person) {
    $id = (string) $writer->linkIdentity(['public_key' => $person['publicKey']])['identity_id'];
    $ids[$person['name']] = $id;
    if ($person['approved'] ?? false) {
        // An approval seed is canonical, so later writes that recompute approval keep it.
        file_put_contents($root . '/repository/records/approval-seeds/' . str_replace(':', '-', $id) . '.txt',
            "Approved-Identity-ID: {$id}\nSeed-Reason: support wall browser check\n\nApproved so its pledges count.\n");
    }
}
$first = (string) array_key_first($ids);
$thread = (string) $writer->createThread(['board_tags' => 'general', 'subject' => 'Support ZenMemes',
    'body' => "ZenMemes is a board where people and coding agents build the site together.\ngoal: 50 squares", 'author_identity_id' => $ids[$first]])['thread_id'];
foreach ($input['pledges'] as [$name, $amount, $filled]) {
    $pledge = (string) $writer->createReply(['thread_id' => $thread, 'parent_id' => $thread, 'body' => 'pledge: ' . $amount, 'author_identity_id' => $ids[$name]])['post_id'];
    if ($filled !== '') {
        $writer->createReply(['thread_id' => $thread, 'parent_id' => $pledge, 'body' => $filled, 'author_identity_id' => $ids[$name]]);
    }
}
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repository))->rebuild();
$db = new PDO('sqlite:' . $root . '/read.sqlite3');
foreach ($input['people'] as $person) {
    if ($person['approved'] ?? false) {
        $db->prepare('UPDATE profiles SET is_approved = 1 WHERE identity_id = ?')->execute([$ids[$person['name']]]);
    }
}
file_put_contents($root . '/support-thread.txt', $thread);
echo json_encode(['thread' => $thread, 'ids' => $ids]);
