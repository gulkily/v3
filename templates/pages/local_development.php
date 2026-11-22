<?php
$s = $importState;
$pending = $s['pending'] !== null;
$failed = !$s['busy'] && ($s['task']['status'] ?? null) === 'failed';
$finished = !$s['busy'] && !$pending && ($s['task']['status'] ?? null) === 'completed';
// The result comes from the import's own output ($result), so it always matches this request.
// A finished import still needs attention when records were held back or images are missing.
$review = $finished && (($result['outcome'] ?? 'complete') !== 'complete' || ($result['images'] ?? 'complete') !== 'complete');
$counts = $finished && $result['counts'] !== [] ? $result['counts'] : null;
$failure = $result['failure'] !== null ? $result['failure'] . ' Retry or resume below.' : 'Import did not finish. Check import details, then retry or resume below.';
// A partial import that still brought records in keeps the link to them.
$imported = $finished && (!$review || ($result['counts']['import'] ?? 0) > 0);
$alert = $notice ?? ($failed ? $failure : ($review ? 'Some content needs review or images could not be copied. Check import details.' . ($imported ? '' : ' Local content is available below.') : null));
?>
<section class="stack">
  <article class="card stack">
    <h1>Local development</h1>
    <p>Explore your local copy, or bring in public content from another instance.</p>
<?= $indent($partial('partials/getting_started_links.php', ['part' => 'browse']), 2) ?>
  </article>
  <article class="card stack" data-import-root data-import-busy="<?= $s['busy'] ? '1' : '0' ?>">
    <h2>Import public content</h2>
<?php if ($alert !== null): ?>
    <p role="alert" data-import-result><?= $e($alert) ?></p>
<?php endif; ?>
<?php if ($counts !== null && $notice === null): ?>
    <p data-import-result><?= trim($partial('partials/import_run.php', ['part' => 'counts', 'counts' => $counts])) ?></p>
<?php endif; ?>
<?php if ($imported && $notice === null): ?>
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
        <button type="submit" name="action" value="<?= $pending ? 'resume' : 'import' ?>"><?= $pending ? 'Resume import' : ($failed ? 'Retry import' : ($finished ? 'Import again' : 'Import')) ?></button>
<?php if ($failed || ($review && !$imported)): ?>
        <a href="/threads/?view=all">Browse local content</a>
<?php endif; ?>
      </div>
    </form>
<?= $indent($partial('partials/import_run.php', ['s' => $s, 'part' => 'not_started', 'action' => '/local/', 'token' => $token]), 4) ?>
    <p class="meta" role="status" aria-live="polite" data-import-status hidden></p>
<?php if ($log !== null && !$s['busy']): ?>
    <details data-import-result>
      <summary>Import details</summary>
      <pre><?= $e($log) ?></pre>
    </details>
<?php endif; ?>
  </article>
  <article class="card stack">
    <h2>Try the forum</h2>
<?= $indent($partial('partials/getting_started_links.php', ['part' => 'try']), 2) ?>
  </article>
</section>
