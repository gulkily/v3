<section class="stack">
<?php if (isset($toolNavOptions)): ?>
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
  </article>
<?php endif; ?>
  <article class="card">
    <h1>Backup</h1>
    <ul>
<?php foreach ($downloads as $download): ?>
      <li><a href="<?= $e($download['href']) ?>"><?= $e($download['label']) ?></a></li>
<?php endforeach; ?>
<?php if ($imageBackupAvailable ?? false): ?>
      <li><a href="<?= $e($imageBackupHref) ?>">Images (.tar)</a></li>
<?php endif; ?>
    </ul>
<?php if ($imageBackupAvailable ?? false): ?>
<?php if ($textBackupEnabled ?? true): ?>
    <p class="meta">Keep content and image downloads with matching revisions.</p>
<?php else: ?>
    <p class="meta">Restoring images requires a matching content backup. Text downloads are disabled here.</p>
<?php endif; ?>
<?php endif; ?>
<?php if ($textBackupEnabled ?? true): ?>
    <p class="meta">The SQLite index may be out of date.</p>
<?php endif; ?>
    <p>Public content only. Back up Git history, private data, browser keys and drafts, software, settings and external media separately.</p>
  </article>
</section>
