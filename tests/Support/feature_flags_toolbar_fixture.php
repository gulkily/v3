<?php

declare(strict_types=1);

// Render the production template with isolated states, without changing the registry.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/autoload.php';

use ForumRewrite\Support\FeatureFlags\FeatureFlagDefinition;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagState;

$registry = new FeatureFlagRegistry();
$canManageFeatureFlags = ($argv[1] ?? '') !== 'readonly';
$flags = [];
foreach ([
    ['TEST_ALPHA', 'Alpha switch', 'Unique apricot description', false, true, null, 'First', '2026-11-16'],
    ['TEST_BETA', 'Beta switch', 'Shared description', true, true, null, 'First', '2026-11-17'],
    ['TEST_GAMMA', 'Gamma switch', 'Shared description', false, false, false, 'Second', '2026-11-10'],
    ['TEST_DELTA', 'Delta switch', 'Shared description', true, true, true, 'Second', null],
] as [$key, $label, $description, $default, $enabled, $environment, $group, $releasedOn]) {
    $definition = new FeatureFlagDefinition($key, $label, $description, $default, $key, siteMutable: true, group: $group, releasedOn: $releasedOn);
    $flags[] = new FeatureFlagState($definition, $enabled, $environment !== null ? 'environment' : 'site', $environment);
}
if (($argv[1] ?? '') === 'canonical') {
    $flags = array_reverse((new \ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator())->all());
}
$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$indent = static fn (string $html, int $level = 1): string => $html;
$toolNavOptions = [['href' => '/tools/feature-flags/', 'label' => 'Feature Flags', 'is_active' => true]];
// Nested partials (the Tools card renders the Tools nav) need the same helpers.
$partial = static function (string $path) use ($e, $indent, $toolNavOptions, &$partial): string {
    ob_start();
    require dirname(__DIR__, 2) . '/templates/' . $path;
    return (string) ob_get_clean();
};
?>
<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Feature flags toolbar fixture</title>
<link rel="stylesheet" href="/assets/site.css">
<link rel="stylesheet" href="/assets/tool-details.css">
<link rel="stylesheet" href="/assets/theme-dark.css">
<link rel="stylesheet" href="/assets/theme-console.css">
<div class="app-version-banner" hidden><div class="app-version-banner__inner">New version available</div></div>
<main class="shell">
<?php require dirname(__DIR__, 2) . '/templates/pages/feature_flags.php'; ?>
<label>Unrelated text <input id="other-input"></label>
<div id="editor" contenteditable="true" tabindex="0">Editable text</div>
<div style="height:1000px"></div>
</main>
<script src="/assets/feature_flags.js"></script>
</html>
