<?php
// Prints the real Unread page from canned rows, for a browser check that serves it
// from memory (no read model, no server):
// php tests/Support/unread_page_fixture.php <replies,...|-> <new,...|-> [failed-section]
declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use ForumRewrite\Http\RecentPageController;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\View\TemplateRenderer;

putenv('FORUM_SITE_ID=zenmemes');
$flags = new FeatureFlagEvaluator(siteValues: [
    FeatureFlagRegistry::PRIVATE_MESSAGES_ENABLED => false,
    FeatureFlagRegistry::INBOX_ENABLED => true,
]);
$row = static fn (string $id): array => [
    'post_id' => $id, 'thread_id' => 'thread-' . $id, 'context_id' => 'thread-' . $id,
    'context_subject' => 'Thread ' . $id, 'context_body' => '', 'subject' => '', 'body' => 'Post ' . $id,
    'created_at' => '2026-11-07T12:00:00Z', 'author_label' => 'alice', 'author_identity_id' => 'openpgp:alice',
];
$ids = static fn (string $arg): array => $arg === '-' ? [] : explode(',', $arg);
$failed = $argv[3] ?? null;
$sections = [];
foreach (['replies' => $argv[1] ?? '-', 'new' => $argv[2] ?? '-'] as $name => $arg) {
    $sections[$name] = ['items' => RecentPageController::present(array_map($row, $ids($arg))), 'failed' => false, 'catch_up_failed' => $failed === $name];
}
$renderer = new TemplateRenderer(dirname(__DIR__, 2) . '/templates', featureFlags: $flags);
echo $renderer->renderPageTemplate('inbox.php', [
    'viewerProfile' => ['identity_id' => 'openpgp:ilyag', 'username_token' => 'ilyag', 'is_approved' => 1],
    'messages' => ['enabled' => false, 'failed' => false, 'conversations' => []],
    'sections' => $sections,
], 'Unread', 'inbox', ['/assets/inbox_catch_up.js']);
