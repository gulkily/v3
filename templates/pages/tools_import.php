<?php
$s = $importState;
$workerLabels = ['fresh' => 'running normally', 'running' => 'running now', 'stale' => 'has not run recently', 'not_observed' => 'never seen', 'failed' => 'last run failed', 'unavailable' => 'unavailable'];
$outcomeLabels = ['complete' => 'complete', 'partial' => 'needs review', 'canceled' => 'canceled'];
$report = $s['last']['report'] ?? [];
$counts = is_array($report['counts'] ?? null) ? $report['counts'] : [];
// The Review row groups causes by state and the reason before any per-record detail
// ("Missing dependency: post …" counts as "Missing dependency"); paths are under Advanced.
$causes = [];
$exclusions = is_array($report['exclusion_reasons'] ?? null) ? $report['exclusion_reasons'] : [];
foreach (is_array($report['review_causes'] ?? null) ? $report['review_causes'] : [] as $cause) {
    // Exclusions are counted in full by exclusion_reasons; their root causes would repeat them.
    if (($cause['state'] ?? '') === 'excluded' && isset($exclusions[explode(': ', (string) ($cause['reason'] ?? ''), 2)[0]])) continue;
    $label = (string) ($cause['state'] ?? '') . ': ' . explode(': ', (string) ($cause['reason'] ?? ''), 2)[0];
    $causes[$label] = ($causes[$label] ?? 0) + max(1, (int) ($cause['affected_count'] ?? 1));
}
arsort($causes);
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
            <br><?= trim($partial('partials/import_run.php', ['part' => 'counts', 'counts' => $counts])) ?>
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
<?= $indent($partial('partials/import_run.php', ['s' => $s, 'part' => 'run', 'action' => '/tools/import/']), 4) ?>
    <form method="post" action="/tools/import/" class="deploy-actions import-actions" data-import-form<?= $s['busy'] ? ' hidden' : '' ?>>
<?php if ($s['pending'] === null): ?>
<?php
// Remembered sources first, then known public instances not already remembered.
$choices = array_column($s['sources'], 'source');
foreach ($s['known'] as $known) {
    if (!in_array($known['url'], $choices, true)) $choices[] = $known['url'];
}
?>
      <label>Source <input type="text" name="source" required inputmode="url" autocomplete="off" spellcheck="false" placeholder="forum.example" list="import-sources"<?= $choices !== [] ? ' value="' . $e($choices[0]) . '"' : '' ?><?= $s['busy'] ? ' disabled' : '' ?>></label>
<?php if ($choices !== []): ?>
      <datalist id="import-sources">
<?php foreach ($choices as $choice): ?>
        <option value="<?= $e($choice) ?>"></option>
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
<?= $indent($partial('partials/import_run.php', ['s' => $s, 'part' => 'not_started', 'action' => '/tools/import/']), 4) ?>
<?php $statusText = (string) ($importNotice ?? ''); ?>
    <p class="meta" role="status" aria-live="polite" data-import-status<?= $statusText === '' ? ' hidden' : '' ?>><?= $e($statusText) ?></p>
<?php endif; ?>
<?php if (!$canImport && ($importNotice ?? '') !== ''): ?>
    <p class="meta" role="status"><?= $e($importNotice) ?></p>
<?php endif; ?>
  </article>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>
