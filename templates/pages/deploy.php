<?php
$s = $deployState;
$short = static fn (?string $sha): string => $sha === null ? 'unavailable' : substr($sha, 0, 12);
$latest = $s['latest'];
$task = $s['task'];
$workerQuiet = in_array($s['worker'], ['stale', 'not_observed', 'failed', 'unavailable'], true);
$rebuild = $s['rebuild'] ?? null;
$rebuilding = in_array($rebuild['status'] ?? null, ['queued', 'running'], true);
$pending = $s['busy'] || $rebuilding || in_array($task['status'] ?? null, ['queued', 'running'], true);
// Show the read model only while a rebuild is pending or after the last deploy queued one.
$showRebuild = $rebuilding || (($latest['Read-Model'] ?? '') === 'queued' && $rebuild !== null);
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
$versions = $deployVersions ?? [];
$preview = $rollbackPreview ?? null;
$returning = $returnPreview ?? null;
$commitCode = static function (string $sha) use ($e, $commitLink, $commitLinkBase): string {
    $code = '<code>' . $e(substr($sha, 0, 8)) . '</code>';

    return $commitLinkBase === null ? $code : '<a href="' . $e($commitLink($sha)) . '">' . $code . '</a>';
};
$workerLabels = ['fresh' => 'running normally', 'running' => 'running now', 'stale' => 'has not run recently', 'not_observed' => 'never seen', 'failed' => 'last run failed', 'unavailable' => 'unavailable'];
?>
<section class="stack">
  <article class="card" data-deploy-root data-deploy-pending="<?= $pending ? '1' : '0' ?>"<?= $queuedAt !== '' ? ' data-deploy-queued-at="' . $e($queuedAt) . '"' : '' ?>>
    <h1>Deploy</h1>
<?php if (!$s['is_checkout']): ?>
    <p>This application directory is not a Git checkout.</p>
<?php else: ?>
<?php if ($returning !== null && $canDeploy): ?>
<?php $stopping = count($returning['commits']) + (int) $returning['more']; ?>
    <form method="post" action="/tools/deploy/" class="deploy-rollback">
      <h2>Return to main from <code><?= $e($returning['branch']) ?></code>?</h2>
<?php if ($stopping > 0): ?>
      <p><strong>These commits stop running.</strong> They stay on the server in <code><?= $e($returning['branch']) ?></code>.</p>
      <details class="deploy-incoming"<?= $stopping <= 5 ? ' open' : '' ?>>
        <summary><?= $e($stopping . ' commit' . ($stopping === 1 ? '' : 's') . ' not on origin/main') ?></summary>
        <ol>
<?php foreach ($returning['commits'] as $c): ?>
          <li><?= $commitCode($c['commit']) ?> <?= $e($c['subject']) ?> <span class="meta"><?= $e($c['author']) ?>, <?= $e($c['date']) ?></span></li>
<?php endforeach; ?>
        </ol>
<?php if ($returning['more'] > 0): ?>
        <p class="meta">and <?= $e((string) $returning['more']) ?> more</p>
<?php endif; ?>
      </details>
<?php endif; ?>
<?php if ($returning['backup']): ?>
      <p>The server's <code>main</code> has commits not on origin/main; they are kept in a <code><?= $e(\ForumRewrite\Deploy\AppUpdate::MAIN_BACKUP_PREFIX) ?>…</code> branch.</p>
<?php endif; ?>
      <div class="deploy-actions">
        <button type="submit" name="action" value="return_to_main" class="deploy-actions__primary">Return to main</button>
        <a href="/tools/deploy/">Cancel</a>
      </div>
    </form>
<?php endif; ?>
<?php if ($preview !== null && $canDeploy): ?>
<?php $undone = count($preview['commits']) + (int) $preview['more']; ?>
    <form method="post" action="/tools/deploy/" class="deploy-rollback">
      <h2>Roll back to <?= $commitCode($preview['commit']) ?>?</h2>
<?php foreach ($preview['warnings'] as $warning): ?>
      <p><strong><?= $e($warning) ?></strong></p>
<?php endforeach; ?>
      <details class="deploy-incoming"<?= $undone <= 5 ? ' open' : '' ?>>
        <summary>Undoes <?= $e($undone . ' commit' . ($undone === 1 ? '' : 's')) ?></summary>
        <ol>
<?php foreach ($preview['commits'] as $c): ?>
          <li><?= $commitCode($c['commit']) ?> <?= $e($c['subject']) ?> <span class="meta"><?= $e($c['author']) ?>, <?= $e($c['date']) ?></span></li>
<?php endforeach; ?>
        </ol>
<?php if ($preview['more'] > 0): ?>
        <p class="meta">and <?= $e((string) $preview['more']) ?> more</p>
<?php endif; ?>
      </details>
      <input type="hidden" name="commit" value="<?= $e($preview['commit']) ?>">
      <div class="deploy-actions">
        <button type="submit" name="action" value="rollback" class="deploy-actions__primary">Roll back to <?= $e(substr($preview['commit'], 0, 8)) ?></button>
        <a href="/tools/deploy/">Cancel</a>
      </div>
    </form>
<?php endif; ?>
    <table class="codebase-facts">
      <tbody>
        <tr>
          <th scope="row">Commit</th>
          <td><code data-deploy-field="commit"><?= $e($short($s['commit'])) ?></code> <?= $e($s['subject'] ?? '') ?></td>
        </tr>
        <tr>
          <th scope="row">Branch</th>
          <td><code><?= $e($s['branch'] ?? 'detached') ?></code><?php if ($canDeploy && ($s['branch'] ?? null) !== 'main' && $returning === null): ?> <form method="post" action="/tools/deploy/" class="deploy-branch-return"><button type="submit" name="action" value="return_preview" class="deploy-actions__secondary">Return to main</button></form><?php endif; ?></td>
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
<?php if ($showRebuild): ?>
        <tr>
          <th scope="row">Site data</th>
          <td data-deploy-field="rebuild">
<?php if (($rebuild['status'] ?? '') === 'running'): ?>
            rebuilding<?php if (($rebuild['progress'] ?? null) !== null): ?> <span class="meta"><?= $e($rebuild['progress']) ?></span><?php endif; ?>
<?php elseif (($rebuild['status'] ?? '') === 'queued'): ?>
            rebuild waiting for the task worker<?php if ($workerQuiet): ?><br><span class="meta">The worker is not running; run <code>./v3 rebuild</code> over SSH.</span><?php endif; ?>
<?php elseif (($rebuild['status'] ?? '') === 'failed'): ?>
            rebuild failed<br><span class="meta">Run <code>./v3 rebuild</code> over SSH.</span>
<?php else: ?>
            rebuilt after the last deploy
<?php endif; ?>
          </td>
        </tr>
<?php endif; ?>
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
      <button type="submit" name="action" value="now" class="deploy-actions__primary">Deploy now</button>
      <button type="submit" name="action" value="queue" class="deploy-actions__secondary">Queue deploy</button>
      <button type="submit" name="action" value="check" class="deploy-actions__secondary">Check for updates</button>
    </form>
<?php endif; ?>
<?php endif; ?>
<?php if ($canDeploy): ?>
<?php $statusText = ($deployNotice ?? '') !== '' ? $deployNotice : ($queuedAt !== '' ? 'Queued; waiting for the task worker.' : ($pending ? 'A deploy is running.' : '')); ?>
    <p class="meta" role="status" aria-live="polite" data-deploy-status<?= $statusText === '' ? ' hidden' : '' ?>><?= $e($statusText) ?></p>
<?php elseif (($deployNotice ?? '') !== ''): ?>
    <p class="meta" role="status"><?= $e($deployNotice) ?></p>
<?php endif; ?>
<?php if ($versions !== [] && $s['is_checkout']): ?>
    <h2>History</h2>
<?php
// One compact row per version. Merge subjects drop their repeated opening; the full subject stays in the title.
$versionRow = static function (array $v) use ($e, $commitCode, $canDeploy): string {
    $subject = preg_replace('/^Merge pull request (#\d+) from [^\/\s]+\/(\S+)/', '$1 $2', $v['subject']) ?? $v['subject'];
    $at = preg_match('/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/', $v['deployed_at'], $m) === 1 ? $m[1] . ' ' . $m[2] : $v['deployed_at'];
    $when = $v['action'] === 'earlier' ? 'before the first recorded deploy' : ($v['action'] === 'rollback' ? 'rolled back ' : ($v['action'] === 'return' ? 'returned to main ' : '')) . $at;
    $row = '      <li><div class="deploy-history__text"><span class="deploy-history__subject" title="' . $e($v['subject']) . '">' . $commitCode($v['commit']) . ' ' . $e($subject) . '</span>'
        . '<span class="meta">' . $e($when) . ($v['running'] ? ' · <strong>running</strong>' : '') . '</span></div>';
    if ($v['can_roll_back'] && $canDeploy) {
        $row .= '<form method="post" action="/tools/deploy/"><input type="hidden" name="commit" value="' . $e($v['commit']) . '">'
            . '<button type="submit" name="action" value="rollback_preview" class="deploy-actions__secondary">Roll back</button></form>';
    }

    return $row . "</li>\n";
};
$recent = array_slice($versions, 0, 5);
$earlier = array_slice($versions, 5);
?>
    <ol class="deploy-history">
<?= implode('', array_map($versionRow, $recent)) ?>
    </ol>
<?php if ($earlier !== []): ?>
    <details class="deploy-history-more">
      <summary><?= $e((string) count($earlier)) ?> earlier</summary>
      <ol class="deploy-history">
<?= implode('', array_map($versionRow, $earlier)) ?>
      </ol>
    </details>
<?php endif; ?>
<?php endif; ?>
  </article>
<?= $indent($partial('partials/tools_nav_card.php'), 2) ?>
</section>
