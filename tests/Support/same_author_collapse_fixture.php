<?php

declare(strict_types=1);

// Reuse the existing isolated operator/key fixture and its JSON identity output.
require __DIR__ . '/fastmod_browser_fixture.php';
foreach ([['root', '10:00', 'guest'], ['r1', '10:05', 'guest'], ['r2', '10:30', 'guest'], ['r3', '10:35', 'guest'], ['r4-agent', '10:36', 'reply-agent'], ['r5-other', '10:37', 'other']] as [$id, $time, $author]) {
    $headers = "Post-ID: collapse-{$id}\nCreated-At: 2026-05-01T{$time}:00Z\nBoard-Tags: general\n";
    $headers .= $id === 'root' ? "Subject: Same-author browser check\n" : "Thread-ID: collapse-root\nParent-ID: collapse-root\n";
    file_put_contents($root . '/repository/records/posts/collapse-' . $id . '.txt', $headers . "\nPost {$id}. " . str_repeat('Long content with spaces ', 20) . "\n");
}
file_put_contents($root . '/repository/records/posts/collapse-empty.txt', "Post-ID: collapse-empty\nCreated-At: 2026-05-02T10:00:00Z\nBoard-Tags: general\nSubject: No replies\n\nEmpty thread.\n");
(new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', $repository))->rebuild();
// Synthetic labels isolate the existing author/agent presentation boundaries.
$db = new PDO('sqlite:' . $root . '/read.sqlite3');
$db->exec("UPDATE posts SET author_label = 'reply-agent' WHERE post_id = 'collapse-r4-agent'; UPDATE posts SET author_label = 'other' WHERE post_id = 'collapse-r5-other'");
