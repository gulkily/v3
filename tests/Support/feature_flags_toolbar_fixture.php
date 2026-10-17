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
    ['TEST_ALPHA', 'Alpha switch', 'Unique apricot description', false, true, null, 'First'],
    ['TEST_BETA', 'Beta switch', 'Shared description', true, true, null, 'First'],
    ['TEST_GAMMA', 'Gamma switch', 'Shared description', false, false, false, 'Second'],
    ['TEST_DELTA', 'Delta switch', 'Shared description', true, true, true, 'Second'],
] as [$key, $label, $description, $default, $enabled, $environment, $group]) {
    $definition = new FeatureFlagDefinition($key, $label, $description, $default, $key, siteMutable: true, group: $group);
    $flags[] = new FeatureFlagState($definition, $enabled, $environment !== null ? 'environment' : 'site', $environment);
}
$e = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$indent = static fn (string $html, int $level): string => $html;
$toolNavOptions = [['href' => '/tools/feature-flags/', 'label' => 'Feature Flags', 'is_active' => true]];
$partial = static function (string $path) use ($e, $toolNavOptions): string {
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
