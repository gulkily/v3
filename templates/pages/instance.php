<section class="stack">
<?php if (isset($toolNavOptions)): ?>
<?php if ($imageBackupAvailable ?? false): ?>
  <article class="card">
    <h2>Image backup</h2>
    <p>Download the content repository below and <a href="<?= $e($imageBackupHref) ?>">the complete image collection (.tar)</a>. Keep matching repository revisions together.</p>
  </article>
<?php endif; ?>
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
  </article>
<?php endif; ?>
  <article class="card">
    <h1>Backup</h1>
    <p><strong>Name:</strong> <?= $e($siteName) ?></p>
    <p><strong>Admin:</strong>
<?php if ($admins === []): ?>
      none
<?php else: ?>
<?php foreach ($admins as $index => $admin): ?>
<?php if ($index > 0): ?>, <?php endif; ?><a href="/user/<?= $e($admin['username_token']) ?>"><?= $e($admin['username']) ?></a>
<?php endforeach; ?>
<?php endif; ?>
    </p>
  </article>
  <article class="card">
    <h2>Snapshot freshness</h2>
<?php if (($backupSnapshot['generated_at'] ?? '') === ''): ?>
    <p class="meta">Freshness information is not available yet.</p>
<?php else: ?>
    <p><strong>Generated at:</strong> <?= $timestamp($backupSnapshot['generated_at']) ?></p>
<?php endif; ?>
<?php if (($backupSnapshot['repository_head'] ?? '') !== ''): ?>
    <p class="meta"><strong>Repository snapshot:</strong> <?= $e(substr((string) $backupSnapshot['repository_head'], 0, 12)) ?></p>
<?php endif; ?>
    <h3>Recent included items</h3>
<?php if (($backupSnapshot['items'] ?? []) === []): ?>
    <p>No recent content items are available in this snapshot.</p>
<?php else: ?>
    <ul class="backup-preview">
<?php foreach ($backupSnapshot['items'] as $item): ?>
<?php if (($item['kind'] ?? '') === 'thread_label_add'): ?>
<?php $href = '/threads/' . ($item['thread_id'] ?? ''); ?>
<?php $linkLabel = $item['thread_id'] ?? ''; ?>
<?php else: ?>
<?php $href = '/posts/' . ($item['post_id'] ?? ''); ?>
<?php $linkLabel = $item['post_id'] ?? ''; ?>
<?php endif; ?>
      <li><a href="<?= $e($href) ?>"><?= $e($linkLabel) ?></a> - <?= $e($item['label'] ?? '') ?> <span class="meta"><?= $e($item['kind'] ?? '') ?></span></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
    <p><a href="/activity/">See all recent activity</a></p>
  </article>
  <article class="card">
    <h2>Downloads</h2>
    <ul>
<?php foreach ($downloads as $download): ?>
      <li><a href="<?= $e($download['href']) ?>"><?= $e($download['label']) ?></a> - <?= $e($download['description']) ?></li>
<?php endforeach; ?>
    </ul>
    <p>Public content and history only, not a complete instance backup. Private messages, browser keys and drafts, operational data, software, configuration, secrets, and externally hosted media need separate backups.</p>
    <p class="meta">The SQLite index may lag behind the repository and include supplemental workflow records.</p>
  </article>
</section>
