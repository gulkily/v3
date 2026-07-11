<section class="stack">
  <article class="card">
    <div class="nav board-controls-nav">
<?php foreach ($toolNavOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </div>
  </article>
  <article class="card tool-launcher">
    <h1>Bookmarklets</h1>
    <p>Bookmarklets let you jump into Compose Thread from another page with the current page URL or selected text already filled in. Drag a bookmarklet link to your bookmarks bar, then activate it while viewing another page.</p>
<?php
$bookmarkletSections = [
    [
        'heading' => 'Same window',
        'mode' => 'same-window',
        'note' => 'These replace the current page with Compose Thread.',
    ],
    [
        'heading' => 'New window',
        'mode' => 'new-window',
        'note' => 'These open Compose Thread in a new window and may be affected by popup blockers.',
    ],
];
?>
<?php foreach ($bookmarkletSections as $section): ?>
    <h2><?= $e($section['heading']) ?></h2>
    <p class="meta"><?= $e($section['note']) ?></p>
    <ul class="tool-launcher-list">
<?php foreach ($bookmarklets as $bookmarklet): ?>
<?php if ($bookmarklet['mode'] !== $section['mode']) { continue; } ?>
      <li class="tool-launcher-item">
        <a
          class="tool-launcher-button"
          href="#"
          data-bookmarklet="pending"
          data-bookmarklet-kind="<?= $e($bookmarklet['bookmarklet_kind']) ?>"
          data-bookmarklet-mode="<?= $e($bookmarklet['mode']) ?>"
        ><?= $e($bookmarklet['label']) ?></a>
        <p class="tool-launcher-description"><?= $e($bookmarklet['description']) ?></p>
      </li>
<?php endforeach; ?>
    </ul>
<?php endforeach; ?>
  </article>
</section>
