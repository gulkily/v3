<section class="stack">
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
  </article>
  <article class="card tool-launcher">
    <h1>Bookmarklets</h1>
    <p>Bookmarklets let you jump into Compose Thread from another page with the current page URL or selected text already filled in. Drag a bookmarklet link to your bookmarks bar, then activate it while viewing another page.</p>
<?php
$bookmarkletSections = [
    [
        'heading' => 'Same window (for iPhone)',
        'mode' => 'same-window',
        'note' => 'These replace the current page with Compose Thread. iPhone-compatible.',
    ],
    [
        'heading' => 'New window (for anyone)',
        'mode' => 'new-window',
        'note' => 'These open Compose Thread in a new window and may be affected by popup blockers. iPhone does not support this.',
    ],
];
?>
<?php foreach ($bookmarkletSections as $section): ?>
    <h2><?= $e($section['heading']) ?></h2>
    <p class="meta"><?= $e($section['note']) ?></p>
    <ul class="tool-launcher-list">
<?php foreach ($bookmarklets as $bookmarklet): ?>
<?php if ($bookmarklet['mode'] !== $section['mode']) { continue; } ?>
      <li class="tool-launcher-item bookmarklet-item">
        <div class="bookmarklet-actions">
        <a
          class="tool-launcher-button"
          href="#"
          data-bookmarklet="pending"
          data-bookmarklet-kind="<?= $e($bookmarklet['bookmarklet_kind']) ?>"
          data-bookmarklet-mode="<?= $e($bookmarklet['mode']) ?>"
        ><?= $e($bookmarklet['label']) ?></a>
        <button type="button" class="bookmarklet-copy" data-bookmarklet-copy disabled
          aria-label="<?= $e('Copy ' . $bookmarklet['label'] . ' bookmarklet (' . $bookmarklet['mode'] . ')') ?>"
          title="Copy one-line bookmarklet">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h4"/></svg>
        </button>
        <span class="bookmarklet-feedback meta" data-bookmarklet-feedback role="status" aria-live="polite" aria-atomic="true"></span>
        </div>
        <p class="tool-launcher-description"><?= $e($bookmarklet['description']) ?></p>
      </li>
<?php endforeach; ?>
    </ul>
<?php endforeach; ?>
  </article>
</section>
