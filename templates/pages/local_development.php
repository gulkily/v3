<?php
$s = $importState;
$pending = $s['pending'] !== null;
$failed = !$s['busy'] && ($s['task']['status'] ?? null) === 'failed';
// $last is the newest finished import (LastImportSummary), from this page or the terminal.
// The card reports on it; the newest request's own output ($result, $log) adds detail when it
// is the same run, and explains a failure when it is not.
$sameRun = $last !== null && $result['run_id'] === $last['id'];
$failure = $result['failure'] !== null ? $result['failure'] . ' Retry or resume below.' : 'Import did not finish. Check import details, then retry or resume below.';
$alert = $notice ?? ($failed ? $failure : null);
$cameIn = $last === null ? '' : ($last['imported'] > 0
    ? number_format($last['imported']) . ($last['repeat'] ? ' new' : ' imported')
    : 'nothing new');
// Held-back records are a quiet line that opens the details, not an alert on every visit.
$heldBack = [];
if ($last !== null && $last['held_back'] > 0) {
    $heldBack[] = number_format($last['held_back']) . ' held back for review';
    if ($last['conflicts'] > 0) $heldBack[] = number_format($last['conflicts']) . ($last['conflicts'] === 1 ? ' conflict' : ' conflicts');
}
if ($sameRun && ($result['images'] ?? 'complete') !== 'complete') $heldBack[] = 'some images not copied';
// Importing again: the failed request's source when retrying, otherwise the copy's own source.
$again = $failed && ($s['task']['source'] ?? '') !== '' ? $s['task']['source'] : ($last['source'] ?? '');
// What a re-import costs, from the last run's own output when this page has it.
$took = '';
if ($sameRun && $result['seconds'] !== null) {
    // A no-break space keeps the number and its unit on one line.
    $took = $result['seconds'] < 90 ? max(1, (int) round($result['seconds'])) . "\u{00A0}s" : 'about ' . (int) round($result['seconds'] / 60) . "\u{00A0}min";
}
?>
<section class="stack">
  <article class="card stack">
    <h1>Local development</h1>
    <p>Explore your local copy, or bring in public content from another instance.</p>
<?= $indent($partial('partials/getting_started_links.php', ['part' => 'browse']), 2) ?>
  </article>
  <article class="card stack" data-import-root data-import-busy="<?= $s['busy'] ? '1' : '0' ?>">
    <h2>Import public content</h2>
<?php if ($alert !== null && !$s['busy']): ?>
    <p role="alert" data-import-result><?= $e($alert) ?></p>
<?php endif; ?>
<?php if ($failed && $log !== null && !$sameRun): ?>
    <details data-import-result>
      <summary>Import details</summary>
      <pre><?= $e($log) ?></pre>
    </details>
<?php endif; ?>
<?php if ($last !== null && !$s['busy']): ?>
    <p data-import-result data-import-last>Imported from <strong><?= $e($last['host']) ?></strong> <?= $relativeTimestamp(gmdate('c', $last['at'])) ?> · <?= $e($cameIn) ?></p>
    <details data-import-result>
      <summary><?= $e($heldBack === [] ? 'Import details' : implode(' · ', $heldBack)) ?></summary>
      <p><?= trim($partial('partials/import_run.php', ['part' => 'counts', 'counts' => $last['counts']])) ?></p>
<?php if ($sameRun && $log !== null): ?>
      <pre><?= $e($log) ?></pre>
<?php endif; ?>
    </details>
    <p data-import-result><a href="/threads/?view=all">Browse imported threads</a></p>
<?php endif; ?>
<?= $indent($partial('partials/import_run.php', ['s' => $s, 'part' => 'run', 'action' => '/local/', 'failureLine' => false]), 4) ?>
<?php if ($pending || $last === null): ?>
    <form method="post" action="/local/" class="stack import-actions" data-local-import data-import-form<?= $s['busy'] ? ' hidden' : '' ?>>
      <p>Local posts and settings stay. Private messages and approval authority are excluded. Conflicts keep the local version.</p>
<?php if ($pending): ?>
      <p>An interrupted import needs to finish before another can start.</p>
<?php endif; ?>
      <input type="hidden" name="token" value="<?= $e($token) ?>">
      <label>Source <input type="text" name="source" value="<?= $e($source) ?>" required maxlength="2048"<?= $pending ? ' readonly' : '' ?>></label>
      <div class="button-row">
        <button type="submit" name="action" value="<?= $pending ? 'resume' : 'import' ?>"><?= $pending ? 'Resume import' : ($failed ? 'Retry import' : 'Import') ?></button>
<?php if ($failed): ?>
        <a href="/threads/?view=all">Browse local content</a>
<?php endif; ?>
      </div>
    </form>
<?php else: ?>
<?php // One action for a copy that has imported: the button carries its source, so no field has to be visible or valid. ?>
    <form method="post" action="/local/" class="stack import-actions" data-local-import data-import-form<?= $s['busy'] ? ' hidden' : '' ?>>
      <input type="hidden" name="token" value="<?= $e($token) ?>">
      <input type="hidden" name="source" value="<?= $e($again) ?>">
      <div class="button-row">
        <button type="submit" name="action" value="import"><?= $failed ? 'Retry import' : 'Check for new content' ?></button>
      </div>
      <p class="meta">Downloads everything again<?= $took !== '' ? '; last time took ' . $e($took) : '' ?>.</p>
    </form>
<?php if (!$s['busy']): ?>
    <details data-import-result<?= $changeOpen ? ' open' : '' ?>>
      <summary>Change source</summary>
      <form method="post" action="/local/" class="stack">
        <input type="hidden" name="token" value="<?= $e($token) ?>">
        <label>Source <input type="text" name="source" value="<?= $e($source) ?>" required maxlength="2048"></label>
        <div class="button-row">
          <button type="submit" name="action" value="import">Import</button>
        </div>
      </form>
    </details>
<?php endif; ?>
<?php endif; ?>
<?= $indent($partial('partials/import_run.php', ['s' => $s, 'part' => 'not_started', 'action' => '/local/', 'token' => $token]), 4) ?>
    <p class="meta" role="status" aria-live="polite" data-import-status hidden></p>
  </article>
  <article class="card stack" data-local-pull data-token="<?= $e($token) ?>" data-source="<?= $e($source) ?>">
    <h2>Your messages</h2>
    <p>Read your own private messages here. This browser's key joins your account on the source, then pulls your mail. Your private key stays in this browser.</p>
    <p data-local-pull-status role="status">Checking this browser's key…</p>
    <ol class="stack" data-local-pull-steps hidden>
      <li data-local-pull-step="register">
        <span data-local-pull-step-text></span>
        <div class="button-row"><button type="button" data-local-pull-register hidden>Register this key on <span data-local-pull-host></span></button></div>
      </li>
      <li data-local-pull-step="approve"><span data-local-pull-step-text></span></li>
      <li data-local-pull-step="import"><span data-local-pull-step-text></span></li>
    </ol>
  </article>
  <article class="card stack">
    <h2>Try the forum</h2>
<?= $indent($partial('partials/getting_started_links.php', ['part' => 'try']), 2) ?>
  </article>
</section>
