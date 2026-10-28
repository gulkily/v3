<?php

declare(strict_types=1);

// Render the production Feature Flags template with the verified attribution row in isolated states.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/autoload.php';

use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagState;

$mode = $argv[1] ?? 'on';
$registry = new FeatureFlagRegistry();
$canManageFeatureFlags = $mode !== 'readonly';
$attributionCutoff = $mode === 'off' ? null : ['cutoff_at' => '2026-10-23T15:04:37Z', 'source' => 'switch-on', 'source_path' => 'records/feature-flag-changes/fixture.txt'];
$flags = [new FeatureFlagState($registry->get(FeatureFlagRegistry::VERIFIED_ATTRIBUTION), $mode !== 'off', 'site', null, $mode !== 'off')];
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
<title>Attribution cutoff fixture</title>
<link rel="stylesheet" href="/assets/site.css">
<link rel="stylesheet" href="/assets/tool-details.css">
<?php foreach (['light', 'word97', 'chicago', 'midnight'] as $theme): ?>
<link rel="stylesheet" href="/assets/theme-<?= $theme ?>.css">
<?php endforeach; ?>
<main class="shell">
<?php require dirname(__DIR__, 2) . '/templates/pages/feature_flags.php'; ?>
</main>
<script src="/assets/feature_flags.js"></script>
</html>
