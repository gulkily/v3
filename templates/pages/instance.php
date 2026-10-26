<section class="stack">
<?php if (isset($toolNavOptions)): ?>
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
  </article>
<?php endif; ?>
  <article class="card">
    <h1>Backup</h1>
    <p><strong>Instance:</strong> <?= $e($siteName) ?></p>
    <p><strong>Admins:</strong>
<?php if ($admins === []): ?>
      None
<?php else: ?>
<?php foreach ($admins as $index => $admin): ?>
<?php if ($index > 0): ?>, <?php endif; ?><a href="/user/<?= $e($admin['username_token']) ?>"><?= $e($admin['username']) ?></a>
<?php endforeach; ?>
<?php endif; ?>
    </p>
    <p><strong>Index generated:</strong> <?= ($backupSnapshot['generated_at'] ?? '') !== '' ? $timestamp($backupSnapshot['generated_at']) : 'Unavailable' ?></p>
    <p><strong>Snapshot:</strong> <?= ($backupSnapshot['repository_head'] ?? '') !== '' ? $e(substr((string) $backupSnapshot['repository_head'], 0, 12)) : 'Unavailable' ?></p>
  </article>
  <article class="card">
    <h2>Downloads</h2>
    <ul>
<?php foreach ($downloads as $download): ?>
      <li><a href="<?= $e($download['href']) ?>"><?= $e($download['label']) ?></a><?php if ($download['href'] === '/downloads/read_model.sqlite3'): ?> <span class="meta">(may be out of date)</span><?php endif; ?></li>
<?php endforeach; ?>
<?php if ($imageBackupAvailable ?? false): ?>
      <li><a href="<?= $e($imageBackupHref) ?>">Images (.tar)</a> <span class="meta">Match the content revision.</span></li>
<?php endif; ?>
    </ul>
    <p class="meta">Public content only; not a full site backup.</p>
  </article>
  <article class="card">
    <h2>Recent included items</h2>
<?php if (($backupSnapshot['items'] ?? []) === []): ?>
    <p>No recent items.</p>
<?php else: ?>
    <ul class="backup-preview">
<?php foreach ($backupSnapshot['items'] as $item): ?>
      <li><a href="<?= $e($item['link']['href']) ?>"><?= $e($item['link']['label']) ?></a> - <?= $e($item['label'] ?? '') ?> <span class="meta"><?= $e($item['kind'] ?? '') ?></span></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
    <p><a href="/activity/">All activity</a></p>
  </article>
</section>
