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
    <p class="meta">Same-window bookmarklets replace the current page. New-window bookmarklets may be affected by popup blockers.</p>
    <ul class="tool-launcher-list">
<?php foreach ($bookmarklets as $bookmarklet): ?>
      <li class="tool-launcher-item">
        <a
          class="tool-launcher-button"
          href="#"
          data-bookmarklet="pending"
          data-bookmarklet-kind="<?= $e($bookmarklet['bookmarklet_kind']) ?>"
          data-bookmarklet-mode="<?= $e($bookmarklet['mode']) ?>"
        ><?= $e($bookmarklet['label']) ?></a>
        <p class="tool-launcher-description"><?= $e($bookmarklet['description']) ?>
          <span class="meta"><?= $e($bookmarklet['mode'] === 'new-window' ? 'Opens Compose Thread in a new window.' : 'Opens Compose Thread in this tab.') ?></span>
        </p>
      </li>
<?php endforeach; ?>
    </ul>
  </article>
</section>
