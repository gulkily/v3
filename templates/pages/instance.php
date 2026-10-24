<section class="stack">
<?php if (isset($toolNavOptions)): ?>
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
    <section class="feature-flag-group">
      <h2 class="feature-flag-group-heading">Text content</h2>
      <h3>Snapshot freshness</h3>
<?php if (($backupSnapshot['generated_at'] ?? '') === ''): ?>
      <p class="meta">Freshness information is not available yet.</p>
<?php else: ?>
      <p><strong>Generated at:</strong> <?= $timestamp($backupSnapshot['generated_at']) ?></p>
<?php endif; ?>
<?php if (($backupSnapshot['repository_head'] ?? '') !== ''): ?>
      <p class="meta"><strong>Repository snapshot:</strong> <?= $e(substr((string) $backupSnapshot['repository_head'], 0, 12)) ?></p>
<?php endif; ?>
      <table class="backup-downloads" data-role="text-content-downloads">
        <thead>
          <tr><th scope="col">Item</th><th scope="col">Format</th><th scope="col">Description</th><th scope="col">Link</th></tr>
        </thead>
        <tbody>
<?php foreach ($downloads as $download): ?>
          <tr>
            <th scope="row" data-label="Item"><?= $e($download['item']) ?></th>
            <td data-label="Format"><code><?= $e($download['format']) ?></code></td>
            <td data-label="Description"><?= $e($download['description']) ?></td>
            <td data-label="Link"><a href="<?= $e($download['href']) ?>" aria-label="<?= $e($download['label']) ?>">Download</a></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    </section>
<?php if ($imageBackupAvailable ?? false): ?>
    <section class="feature-flag-group">
      <h2 class="feature-flag-group-heading">Images</h2>
      <table class="backup-downloads" data-role="image-downloads">
        <thead>
          <tr><th scope="col">Item</th><th scope="col">Format</th><th scope="col">Description</th><th scope="col">Link</th></tr>
        </thead>
        <tbody>
          <tr>
            <th scope="row" data-label="Item">Image collection</th>
            <td data-label="Format"><code>.tar</code></td>
            <td data-label="Description">Every stored image with a checksum manifest tied to the repository revision.</td>
            <td data-label="Link"><a href="<?= $e($imageBackupHref) ?>" aria-label="Complete image collection (.tar)">Download</a></td>
          </tr>
        </tbody>
      </table>
      <p class="feature-flags-notice meta">Download with the content repository above. Keep matching repository revisions together.</p>
    </section>
<?php endif; ?>
    <section class="feature-flag-group">
      <h2 class="feature-flag-group-heading">Recent included items</h2>
<?php if (($backupSnapshot['items'] ?? []) === []): ?>
      <p>No recent content items are available in this snapshot.</p>
<?php else: ?>
      <ul class="backup-preview">
<?php foreach ($backupSnapshot['items'] as $item): ?>
<?php $href = $item['link']['href']; ?>
<?php $linkLabel = $item['link']['label']; ?>
        <li><a href="<?= $e($href) ?>"><?= $e($linkLabel) ?></a> - <?= $e($item['label'] ?? '') ?> <span class="meta"><?= $e($item['kind'] ?? '') ?></span></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
      <p><a href="/activity/">See all recent activity</a></p>
    </section>
  </article>
  <article class="card">
    <h2 class="feature-flag-group-heading">About these downloads</h2>
    <p>Current public content only, not a complete instance backup. Git history, private messages, browser keys and drafts, operational data, software, configuration, secrets, and externally hosted media need separate backups.</p>
    <p class="meta">The SQLite index may lag behind the repository and include supplemental workflow records.</p>
  </article>
</section>
