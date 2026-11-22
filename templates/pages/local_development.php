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
    <form method="post" action="/local/" class="stack import-actions" data-local-import data-import-form<?= $s['busy'] ? ' hidden' : '' ?>>
      <p>Local posts and settings stay. Private messages and approval authority are excluded. Conflicts keep the local version.</p>
<?php if ($pending): ?>
      <p>An interrupted import needs to finish before another can start.</p>
<?php endif; ?>
      <input type="hidden" name="token" value="<?= $e($token) ?>">
      <label>Source <input type="text" name="source" value="<?= $e($source) ?>" required maxlength="2048"<?= $pending ? ' readonly' : '' ?>></label>
      <div class="button-row">
        <button type="submit" name="action" value="<?= $pending ? 'resume' : 'import' ?>"><?= $pending ? 'Resume import' : ($failed ? 'Retry import' : ($last !== null ? 'Import again' : 'Import')) ?></button>
<?php if ($failed && $last === null): ?>
        <a href="/threads/?view=all">Browse local content</a>
<?php endif; ?>
      </div>
    </form>
<?= $indent($partial('partials/import_run.php', ['s' => $s, 'part' => 'not_started', 'action' => '/local/', 'token' => $token]), 4) ?>
    <p class="meta" role="status" aria-live="polite" data-import-status hidden></p>
  </article>
  <article class="card stack">
    <h2>Try the forum</h2>
<?= $indent($partial('partials/getting_started_links.php', ['part' => 'try']), 2) ?>
  </article>
</section>
