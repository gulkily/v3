<section class="stack">
<?php if (isset($toolNavOptions)): ?>
  <article class="card">
    <div class="nav board-controls-nav">
<?php foreach ($toolNavOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </div>
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
    <p class="meta">Showing the five most recent content items when available; this is a preview, not a complete archive listing.</p>
<?php endif; ?>
  </article>
  <article class="card">
    <h2>Downloads</h2>
    <ul>
<?php foreach ($downloads as $download): ?>
      <li><a href="<?= $e($download['href']) ?>"><?= $e($download['label']) ?></a> - <?= $e($download['description']) ?></li>
<?php endforeach; ?>
    </ul>
    <h3>Why this matters</h3>
    <p>These downloads are complete snapshots of the forum data, not partial exports. They preserve the full board as it exists at that moment, which makes them an insurance policy of sorts: if the board is changed in ways the community did not choose or consent to, the data needed to restore, migrate, or independently preserve it still exists.</p>
    <h3>Explain it like I'm five</h3>
    <p>Think of these downloads like a backup copy of the whole forum. They do not just save a few pieces, they save everything needed to keep the board alive somewhere else. If someone changes the board in a way the community did not agree to, this gives people a way to put it back, move it, or preserve it.</p>
    <h3>For technical users</h3>
    <p>The downloadable artifacts are sufficient to reconstruct the board in a self-hosted or independently archived form. The repository archive preserves the canonical content and its history, while the SQLite read-model provides a ready-made local index for query and inspection. Together, they make the forum portable, auditable, and resilient against unilateral platform or administrator actions.</p>
    <p>In other words, these files reduce trust requirements. Users do not have to rely on a live server remaining benevolent, stable, or even available in order to retain access to the board's full state. If governance fails or the service is modified without community consent, the data needed to verify, migrate, and continue the forum remains available outside that control surface.</p>
  </article>
</section>
