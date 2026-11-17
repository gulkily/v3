<?php
$s = $importState;
$workerLabels = ['fresh' => 'running normally', 'running' => 'running now', 'stale' => 'has not run recently', 'not_observed' => 'never seen', 'failed' => 'last run failed', 'unavailable' => 'unavailable'];
$outcomeLabels = ['complete' => 'complete', 'partial' => 'needs review', 'canceled' => 'canceled'];
$report = $s['last']['report'] ?? [];
$counts = is_array($report['counts'] ?? null) ? $report['counts'] : [];
// The Review row groups causes by state and the reason before any per-record detail
// ("Missing dependency: post …" counts as "Missing dependency"); paths are under Advanced.
$causes = [];
foreach (is_array($report['review_causes'] ?? null) ? $report['review_causes'] : [] as $cause) {
    $label = (string) ($cause['state'] ?? '') . ': ' . explode(': ', (string) ($cause['reason'] ?? ''), 2)[0];
    $causes[$label] = ($causes[$label] ?? 0) + max(1, (int) ($cause['affected_count'] ?? 1));
}
arsort($causes);
$exclusions = is_array($report['exclusion_reasons'] ?? null) ? $report['exclusion_reasons'] : [];
$detailLimit = 500;
$details = [];
// Records needing attention come before excluded ones, so the cap hides exclusions first.
$detailOrder = ['conflict' => 0, 'invalid' => 1, 'unsupported' => 2, 'excluded' => 3];
foreach (is_array($report['entries'] ?? null) ? $report['entries'] : [] as $path => $entry) {
    if (!isset($detailOrder[$entry['state'] ?? ''])) continue;
    $details[] = ['path' => (string) $path, 'state' => (string) $entry['state'], 'reason' => (string) ($entry['reason'] ?? ''), 'root' => (string) ($entry['root_cause'] ?? '')];
}
usort($details, static fn (array $a, array $b): int => [$detailOrder[$a['state']], $a['path']] <=> [$detailOrder[$b['state']], $b['path']]);
$hiddenDetails = max(0, count($details) - $detailLimit);
$details = array_slice($details, 0, $detailLimit);
?>
<section class="stack">
  <article class="card" data-import-root data-import-busy="<?= ($s['busy'] ?? false) ? '1' : '0' ?>">
    <h1>Import</h1>
<?php if (!$canImport): ?>
    <p>Importing requires a signed-in root-approved operator.</p>
<?php else: ?>
    <table class="codebase-facts">
      <tbody>
<?php if ($s['pending'] !== null): ?>
        <tr>
          <th scope="row">Interrupted</th>
          <td><?= $e($s['pending']['source'] !== '' ? $s['pending']['source'] : $s['pending']['id']) ?><br><span class="meta"><?= $s['pending']['committed'] ? 'Content is committed; resume finishes publishing.' : 'Resume finishes it; cancel removes what it installed.' ?></span></td>
        </tr>
<?php endif; ?>
        <tr>
          <th scope="row">Last import</th>
          <td data-import-field="last">
<?php if ($s['last'] === null): ?>
            none
<?php else: ?>
            <strong><?= $e($outcomeLabels[$s['last']['outcome']] ?? $s['last']['outcome']) ?></strong> <?= $e($s['last']['at']) ?><?php if (($s['last']['report']['source'] ?? '') !== ''): ?> from <?= $e($s['last']['report']['source']) ?><?php endif; ?>
<?php if ($counts !== []): ?>
            <br><span class="meta"><?php $first = true; foreach ($counts as $category => $count): if ((int) $count === 0) continue; ?><?= $first ? '' : ' · ' ?><?= $e($category === 'import' ? 'imported' : $category) ?> <?= $e((string) $count) ?><?php $first = false; endforeach; ?><?php if ($first): ?>nothing new<?php endif; ?></span>
<?php endif; ?>
<?php if ($s['images'] !== null && $s['last']['outcome'] !== 'canceled'): ?>
            <br><span class="meta">images <?= $e($s['images']) ?></span>
<?php endif; ?>
<?php endif; ?>
          </td>
        </tr>
<?php if ($causes !== [] || $exclusions !== []): ?>
        <tr>
          <th scope="row">Review</th>
          <td>
            <ul class="import-causes">
<?php foreach ($causes as $label => $count): ?>
              <li><?= $e((string) $label) ?> <span class="meta">(<?= $e((string) $count) ?>)</span></li>
<?php endforeach; ?>
<?php foreach ($exclusions as $reason => $count): ?>
              <li>excluded: <?= $e((string) $reason) ?> <span class="meta">(<?= $e((string) $count) ?>)</span></li>
<?php endforeach; ?>
            </ul>
          </td>
        </tr>
<?php endif; ?>
        <tr>
          <th scope="row">Task worker</th>
          <td><?= $e($workerLabels[$s['worker']] ?? $s['worker']) ?></td>
        </tr>
      </tbody>
    </table>
<?php if ($details !== []): ?>
    <details class="import-advanced">
      <summary>Advanced</summary>
      <ul>
<?php foreach ($details as $detail): ?>
        <li><code><?= $e($detail['path']) ?></code> <?= $e($detail['state']) ?>: <?= $e($detail['reason']) ?><?php if ($detail['root'] !== ''): ?> <span class="meta">(root cause <code><?= $e($detail['root']) ?></code>)</span><?php endif; ?></li>
<?php endforeach; ?>
      </ul>
<?php if ($hiddenDetails > 0): ?>
      <p class="meta"><?= $e((string) $hiddenDetails) ?> more in <code><?= $e((string) ($report['review_path'] ?? '')) ?>/report.json</code></p>
<?php endif; ?>
    </details>
<?php endif; ?>
<?php if ($s['task'] !== null && ($s['busy'] || $s['task']['status'] === 'failed')): ?>
    <ol class="import-progress" data-import-progress<?= $s['progress'] === [] ? ' hidden' : '' ?>>
<?php foreach ($s['progress'] as $line): ?>
      <li><?= $e($line) ?></li>
<?php endforeach; ?>
    </ol>
<?php else: ?>
    <ol class="import-progress" data-import-progress hidden></ol>
<?php endif; ?>
<?php if ($s['task'] !== null && $s['task']['status'] === 'failed' && !$s['busy']): ?>
    <p><strong>Last request failed:</strong> <?= $e($s['task']['failure']) ?></p>
<?php endif; ?>
    <form method="post" action="/tools/import/" class="deploy-actions import-actions" data-import-form>
<?php if ($s['pending'] === null): ?>
      <label>Source <input type="text" name="source" required inputmode="url" autocomplete="off" spellcheck="false" placeholder="forum.example" list="import-sources"<?= $s['sources'] !== [] ? ' value="' . $e($s['sources'][0]['source']) . '"' : '' ?><?= $s['busy'] ? ' disabled' : '' ?>></label>
<?php if ($s['sources'] !== []): ?>
      <datalist id="import-sources">
<?php foreach ($s['sources'] as $source): ?>
        <option value="<?= $e($source['source']) ?>"></option>
<?php endforeach; ?>
      </datalist>
<?php endif; ?>
      <button type="submit" name="action" value="import"<?= $s['busy'] ? ' disabled' : '' ?>>Import</button>
<?php else: ?>
      <button type="submit" name="action" value="resume"<?= $s['busy'] ? ' disabled' : '' ?>>Resume</button>
<?php if (!$s['pending']['committed']): ?>
      <button type="submit" name="action" value="cancel"<?= $s['busy'] ? ' disabled' : '' ?>>Cancel import</button>
<?php endif; ?>
<?php endif; ?>
    </form>
<?php if (in_array($s['worker'], ['stale', 'not_observed', 'failed', 'unavailable'], true)): ?>
    <p class="meta">Imports wait for the task worker, which <?= $e(['stale' => 'has not run recently', 'not_observed' => 'has not run on this server yet', 'failed' => 'failed on its last run', 'unavailable' => 'cannot be checked'][$s['worker']]) ?>.</p>
<?php endif; ?>
<?php $statusText = ($importNotice ?? '') !== '' ? $importNotice : ($s['busy'] ? (($s['task']['status'] ?? '') === 'running' ? 'Importing…' : 'Queued; waiting for the task worker.') : ''); ?>
    <p class="meta" role="status" aria-live="polite" data-import-status<?= $statusText === '' ? ' hidden' : '' ?>><?= $e($statusText) ?></p>
<?php endif; ?>
<?php if (!$canImport && ($importNotice ?? '') !== ''): ?>
    <p class="meta" role="status"><?= $e($importNotice) ?></p>
<?php endif; ?>
  </article>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>
