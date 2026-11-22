<?php
// The running import as both import pages show it: Tools → Import and the local start page.
// $s is InstanceImportRequests::state(); $part is "run" (steps, timing, log, failure) or
// "not_started" (the worker notice, posting Try again to $action with an optional $token).
// $action is the page's own address; "run" uses it for the reload without JavaScript.
// import_control.js redraws these elements; keep its wording in step with this file.
?>
<?php if ($part === 'run'): ?>
<?php
$formatCount = static function (array $step): string {
    if ($step['done'] === null || ($step['done'] === 0 && $step['total'] === null)) return '';
    if ($step['key'] === 'download') {
        $size = static fn (int $bytes): string => $bytes < 1048576 ? max(1, (int) round($bytes / 1024)) . ' KB' : number_format($bytes / 1048576, 1) . ' MB';
        return $step['total'] !== null ? $size($step['done']) . ' of ' . $size($step['total']) : $size($step['done']);
    }
    return number_format($step['done']) . ($step['total'] !== null ? ' of ' . number_format($step['total']) : '');
};
$timing = $s['timing'] ?? null;
// Same wording as import_control.js, which keeps the numbers ticking between polls.
$timingText = '';
if ($timing !== null) {
    $clock = static fn (int $seconds): string => intdiv($seconds, 60) . ':' . str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    $left = $timing['seconds_left'] ?? $timing['step_seconds_left'];
    $scope = $timing['seconds_left'] === null && $timing['step_seconds_left'] !== null ? ' in this step' : '';
    $leftText = $left === null ? 'estimating time left' : ($left < 60 ? 'under a minute left' . $scope : 'about ' . (int) ceil($left / 60) . ' min left' . $scope);
    $timingText = 'Elapsed ' . $clock($timing['elapsed']) . ' · updated ' . $timing['since_update'] . ' s ago · ' . $leftText;
}
?>
<?php if (($s['busy'] ?? false) && ($action ?? null) !== null): ?>
<?php // Without the page script nothing polls, so the page reloads itself while an import runs. ?>
<noscript><meta http-equiv="refresh" content="5;url=<?= $e($action) ?>"></noscript>
<?php endif; ?>
<div class="import-run" data-import-run<?= ($s['steps'] ?? []) === [] ? ' hidden' : '' ?>>
  <ol class="import-steps" data-import-steps>
<?php foreach ($s['steps'] ?? [] as $step): ?>
    <li class="is-<?= $e($step['status']) ?>"><span><?= $e($step['label']) ?></span><?php $count = $formatCount($step); if ($count !== ''): ?> <span class="meta"><?= $e($count) ?></span><?php endif; ?></li>
<?php endforeach; ?>
  </ol>
  <p class="meta import-timing" data-import-timing<?= $timing === null ? ' hidden' : '' ?> data-elapsed="<?= $e((string) ($timing['elapsed'] ?? 0)) ?>" data-since-update="<?= $e((string) ($timing['since_update'] ?? 0)) ?>" data-seconds-left="<?= $e((string) ($timing['seconds_left'] ?? $timing['step_seconds_left'] ?? '')) ?>" data-left-scope="<?= $timing !== null && $timing['seconds_left'] === null && $timing['step_seconds_left'] !== null ? 'step' : 'total' ?>"><?= $e($timingText) ?></p>
</div>
<?php if ($s['task'] !== null && ($s['busy'] || $s['task']['status'] === 'failed') && $s['progress'] !== []): ?>
<details class="import-log">
  <summary>Log</summary>
  <ol class="import-progress" data-import-progress>
<?php foreach ($s['progress'] as $line): ?>
    <li><?= $e($line) ?></li>
<?php endforeach; ?>
  </ol>
</details>
<?php else: ?>
<details class="import-log" hidden>
  <summary>Log</summary>
  <ol class="import-progress" data-import-progress></ol>
</details>
<?php endif; ?>
<?php if ($s['task'] !== null && $s['task']['status'] === 'failed' && !$s['busy']): ?>
<p><strong>Last request failed:</strong> <?= $e($s['task']['failure']) ?></p>
<?php endif; ?>
<?php else: ?>
<div class="import-not-started" data-import-not-started<?= ($s['start'] ?? null) === 'not_started' ? '' : ' hidden' ?>>
  <p><strong>The task worker didn't start.</strong> Run this in the application checkout, or try again:</p>
  <p><code><?= $e($s['worker_command']) ?></code></p>
  <form method="post" action="<?= $e($action) ?>" data-import-form-retry>
<?php if (($token ?? null) !== null): ?>
    <input type="hidden" name="token" value="<?= $e($token) ?>">
<?php endif; ?>
    <button type="submit" name="action" value="start_worker">Try again</button>
  </form>
</div>
<?php endif; ?>
