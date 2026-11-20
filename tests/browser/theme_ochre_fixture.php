<?php

declare(strict_types=1);

// Fixture for theme_ochre.mjs: a ported thread nested nine deep, plus a reply
// from the fixture's operator that sets `theme: ochre` on it. Prints the
// thread ID. Usage: php theme_ochre_fixture.php <repo> <database> <public>
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Port\PortFile;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Write\LocalWriteService;

require __DIR__ . '/../../autoload.php';

[, $repository, $database, $public] = $argv;
// [item, parent, author, text]: 1001 to 1009 are a chain nine deep.
$comments = [
    [1001, 1000, 'mkessler', 'A canonical record that is a text file.'],
    [1002, 1001, 'tidewater', 'How are deletes handled?'],
    [1003, 1002, 'mkessler', 'The post goes and a retraction record stays.'],
    [1004, 1003, 'tidewater', 'A reasonable split.'],
    [1005, 1004, 'ondrej_v', 'Rewriting history breaks clones.'],
    [1006, 1005, 'plinth', 'Only if the rewrite is pushed.'],
    [1007, 1006, 'saralynn', 'Announced how?'],
    [1008, 1007, 'plinth', 'A dated note in the repository.'],
    [1009, 1008, 'saralynn', 'Nine levels down.'],
    [1010, 1002, 'aokafor', 'Most forums only pretend to delete.'],
    [1011, 1001, 'plinth', 'No JavaScript needed to read.'],
    [1012, 1000, 'saralynn', 'Agents are welcome here?'],
    [1013, 1012, 'ondrej_v', 'Each agent post is labelled.'],
    [1014, 1013, 'saralynn', 'Fold their branch away.'],
    [1015, 1000, 'aokafor', 'Thin indents suit phones.'],
];
$text = "Port-Format: hn-v1\nThread-Title: Every post is a Git commit\nThread-Link: https://news.ycombinator.com/item?id=1000\n"
    . "%%\nItem: 1000\nAuthor: op\nCreated-At: 2026-11-19T10:00:00Z\nTitle: Every post is a Git commit\nLink: https://example.com/story\n\nOpening text.\n";
foreach ($comments as $index => [$item, $parent, $author, $body]) {
    $text .= "%%\nItem: {$item}\nParent: {$parent}\nAuthor: {$author}\nCreated-At: 2026-11-19T10:" . sprintf('%02d', $index + 1) . ":00Z\n\n{$body}\n";
}
$service = new LocalWriteService($repository, $database, $public, new CanonicalRecordRepository($repository), featureFlags: FeatureFlagEvaluator::forRepository($repository));
$threadId = (string) $service->createPortedThread(PortFile::parse($text), 'openpgp:abababababababababababababababababababab')['thread_id'];
$service->createReply(['thread_id' => $threadId, 'parent_id' => $threadId, 'body' => 'theme: ochre', 'author_identity_id' => 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954']);
echo $threadId, "\n";
