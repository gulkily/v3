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
    <p class="meta">Keep content and image downloads with matching revisions.</p>
<?php endif; ?>
<?php if ($textBackupEnabled ?? true): ?>
    <p class="meta">The SQLite index may be out of date.</p>
<?php endif; ?>
    <p>Public content only. Back up Git history, private data, browser keys and drafts, software, settings and external media separately.</p>
  </article>
</section>
