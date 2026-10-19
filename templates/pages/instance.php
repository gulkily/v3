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
      <p class="meta">Download this together with the content repository above, and keep files with matching repository revisions together. The SQLite index alone cannot restore images.</p>
      <p class="feature-flags-notice meta">If the repository changes, refresh this page and download both again. Images remain available for backup when image display is disabled. Restore instructions are in the image backup runbook.</p>
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
      <p class="meta">Showing the five most recent content items when available; this is a preview, not a complete archive listing.</p>
<?php endif; ?>
      <p><a href="/activity/">See all recent activity</a></p>
    </section>
  </article>
  <article class="card">
    <h2 class="feature-flag-group-heading">About these downloads</h2>
    <h3>Why this matters</h3>
    <p>These downloads preserve the forum's canonical public content and its history, together with a searchable index. With the application software and a new deployment, they support independent restoration, migration, or forking of the public board if the original service changes or disappears.</p>
    <p>They are not a complete instance backup. Private messages and separately stored operational data, browser-local keys and drafts, application software, deployment configuration and secrets, and externally hosted media are not included.</p>
    <h3>Explain it like I'm five</h3>
    <p>Think of these downloads as a copy of the public forum's records, not a copy of everything on the server or in your browser. Someone can use those records to rebuild the public board elsewhere, but private conversations and other excluded data need separate backups. A saved link to a video does not save the video itself.</p>
    <h3>For technical users</h3>
    <p>The repository archive preserves the canonical content and its Git history, including public identity and governance records. The canonical index can be rebuilt from those records. The SQLite download serves the current database file, which may also contain supplemental workflow records; it is not a curated export of selected tables. The application software and deployment setup must be obtained separately.</p>
    <p>The repository and index are downloaded separately and are not guaranteed to represent the same instant; the index may lag behind the repository. These files reduce dependence on the original operator for preserving and verifying the public record, but they do not preserve the instance's full private and operational state.</p>
  </article>
</section>
