<?php
$s = $deployState;
$short = static fn (?string $sha): string => $sha === null ? 'unavailable' : substr($sha, 0, 12);
$latest = $s['latest'];
$task = $s['task'];
$workerQuiet = in_array($s['worker'], ['stale', 'not_observed', 'failed', 'unavailable'], true);
$pending = $s['busy'] || in_array($task['status'] ?? null, ['queued', 'running'], true);
if ($s['behind'] === null) {
    $behind = 'unknown';
} elseif ($s['behind'] === 0) {
    $behind = 'up to date';
} else {
    $behind = $s['behind'] . ' commit' . ($s['behind'] === 1 ? '' : 's');
}
$commitLink = static fn (string $sha): string => $commitLinkBase === null ? '' : $commitLinkBase . '/commit/' . rawurlencode($sha);
$incoming = $s['incoming_commits'] ?? [];
$stat = $s['incoming_stat'] ?? null;
$queuedAt = ($task['status'] ?? null) === 'queued' ? (string) ($task['requested_at'] ?? '') : '';
$workerLabels = ['fresh' => 'running normally', 'running' => 'running now', 'stale' => 'has not run recently', 'not_observed' => 'never seen', 'failed' => 'last run failed', 'unavailable' => 'unavailable'];
?>
<section class="stack">
  <article class="card" data-deploy-root data-deploy-pending="<?= $pending ? '1' : '0' ?>"<?= $queuedAt !== '' ? ' data-deploy-queued-at="' . $e($queuedAt) . '"' : '' ?>>
    <h1>Deploy</h1>
<?php if (!$s['is_checkout']): ?>
    <p>This application directory is not a Git checkout.</p>
<?php else: ?>
    <table class="codebase-facts">
      <tbody>
        <tr>
          <th scope="row">Commit</th>
          <td><code data-deploy-field="commit"><?= $e($short($s['commit'])) ?></code> <?= $e($s['subject'] ?? '') ?></td>
        </tr>
        <tr>
          <th scope="row">Branch</th>
          <td><code><?= $e($s['branch'] ?? 'detached') ?></code></td>
        </tr>
<?php if (($s['local_changes'] ?? 0) > 0): ?>
        <tr>
          <th scope="row">Local changes</th>
          <td><?php foreach ($s['changed_files'] as $i => $file): ?><?= $i > 0 ? ', ' : '' ?><code><?= $e($file) ?></code><?php endforeach; ?><br><span class="meta">Kept across deploys.</span></td>
        </tr>
<?php endif; ?>
        <tr>
          <th scope="row">Behind origin/main</th>
          <td><span data-deploy-field="behind"><?= $e($behind) ?></span> <span class="meta" data-deploy-field="checked"><?= $s['last_checked_at'] === null ? '(never checked)' : '(checked ' . $e($s['last_checked_at']) . ')' ?></span></td>
        </tr>
<?php if ($incoming !== [] || $stat !== null): ?>
        <tr>
          <th scope="row">Incoming</th>
          <td data-deploy-field="incoming">
<?php
$commitCount = count($incoming) + (int) ($s['incoming_more'] ?? 0);
$parts = $commitCount > 0 ? [$commitCount . ' commit' . ($commitCount === 1 ? '' : 's')] : [];
if ($stat !== null) {
    $parts[] = $stat['files'] . ' file' . ($stat['files'] === 1 ? '' : 's') . ' changed, +' . $stat['insertions'] . ' −' . $stat['deletions'];
}
$summary = implode(' · ', $parts);
?>
            <details class="deploy-incoming"<?= $commitCount <= 5 ? ' open' : '' ?>>
              <summary><?= $e($summary) ?></summary>
              <ol>
<?php foreach ($incoming as $c): ?>
                <li><?php if ($commitLinkBase !== null): ?><a href="<?= $e($commitLink($c['commit'])) ?>"><code><?= $e(substr($c['commit'], 0, 8)) ?></code></a><?php else: ?><code><?= $e(substr($c['commit'], 0, 8)) ?></code><?php endif; ?> <?= $e($c['subject']) ?> <span class="meta"><?= $e($c['author']) ?>, <?= $e($c['date']) ?></span></li>
<?php endforeach; ?>
              </ol>
<?php if (($s['incoming_more'] ?? 0) > 0): ?>
<?php $moreText = 'and ' . $s['incoming_more'] . ' more'; ?>
              <p class="meta"><?php if ($commitLinkBase !== null && $s['commit'] !== null && $s['remote_commit'] !== null): ?><a href="<?= $e($commitLinkBase . '/compare/' . $s['commit'] . '...' . $s['remote_commit']) ?>"><?= $e($moreText) ?></a><?php else: ?><?= $e($moreText) ?><?php endif; ?></p>
<?php endif; ?>
            </details>
<?php if (($s['incoming_local_overlap'] ?? []) !== []): ?>
            <p><strong>Also changes locally edited</strong> <?php foreach ($s['incoming_local_overlap'] as $i => $file): ?><?= $i > 0 ? ', ' : '' ?><code><?= $e($file) ?></code><?php endforeach; ?>. If the same lines changed, the deploy stops and keeps the old version.</p>
<?php endif; ?>
          </td>
        </tr>
<?php endif; ?>
        <tr>
          <th scope="row">Task worker</th>
          <td><?= $e($workerLabels[$s['worker']] ?? $s['worker']) ?></td>
        </tr>
        <tr>
          <th scope="row">Last deploy</th>
          <td data-deploy-field="latest">
<?php if ($latest === null): ?>
            none
<?php else: ?>
            <strong><?= $e(str_replace('_', ' ', $latest['Outcome'] ?? '')) ?></strong> <?= $e($latest['Finished-At'] ?? $latest['Started-At'] ?? '') ?> (<?= $e($latest['Mode'] ?? '') ?>)<br>
            <span class="meta"><?= $e($latest['Message'] ?? '') ?></span>
<?php endif; ?>
          </td>
        </tr>
      </tbody>
    </table>
<?php if ($workerQuiet && $canDeploy): ?>
<?php $workerSentences = ['stale' => 'has not run recently', 'not_observed' => 'has not run on this server yet', 'failed' => 'failed on its last run', 'unavailable' => 'cannot be checked']; ?>
    <p class="meta">Queued deploys wait for the task worker, which <?= $e($workerSentences[$s['worker']] ?? 'cannot be checked') ?>. Deploy now runs immediately.</p>
<?php endif; ?>
<?php if ($canDeploy): ?>
    <form method="post" action="/tools/deploy/" class="deploy-actions" data-deploy-form>
      <button type="submit" name="action" value="queue">Queue deploy</button>
      <button type="submit" name="action" value="now">Deploy now</button>
      <button type="submit" name="action" value="check">Check for updates</button>
    </form>
<?php endif; ?>
<?php endif; ?>
<?php if ($canDeploy): ?>
<?php $statusText = ($deployNotice ?? '') !== '' ? $deployNotice : ($queuedAt !== '' ? 'Queued; waiting for the task worker.' : ($pending ? 'A deploy is running.' : '')); ?>
    <p class="meta" role="status" aria-live="polite" data-deploy-status<?= $statusText === '' ? ' hidden' : '' ?>><?= $e($statusText) ?></p>
<?php elseif (($deployNotice ?? '') !== ''): ?>
    <p class="meta" role="status"><?= $e($deployNotice) ?></p>
<?php endif; ?>
  </article>
  <article class="card tools-nav-card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
  </article>
</section>
