<?php
// Prints the ZenMemes app layout for a browser check that serves it from memory
// (no read model, no server): php tests/Support/app_layout_fixture.php <section> [events=1] [messages=1] [inbox=0] [deploy=0]
declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\View\TemplateRenderer;

putenv('FORUM_SITE_ID=zenmemes');
$section = $argv[1] ?? 'board';
$flags = new FeatureFlagEvaluator(siteValues: [
    FeatureFlagRegistry::EVENT_SUPPORT_ENABLED => ($argv[2] ?? '1') === '1',
    FeatureFlagRegistry::PRIVATE_MESSAGES_ENABLED => ($argv[3] ?? '1') === '1',
    FeatureFlagRegistry::INBOX_ENABLED => ($argv[4] ?? '0') === '1',
    FeatureFlagRegistry::DEPLOY_HEADER_PROMPT_ENABLED => ($argv[5] ?? '0') === '1',
]);
$renderer = new TemplateRenderer(dirname(__DIR__, 2) . '/templates', featureFlags: $flags);
echo $renderer->renderLayout(ucfirst($section), '<h1>' . htmlspecialchars(ucfirst($section)) . '</h1>', $section);
