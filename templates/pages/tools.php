<section class="stack">
  <article class="card">
    <h1>Tools</h1>
    <p>Bookmarklets let you jump into Compose Thread from another page with the current page URL or selected text already filled in.</p>
  </article>
  <article class="card">
    <h2>How to use these</h2>
    <p>Drag a bookmarklet link to your bookmarks bar. Then activate it while viewing another page.</p>
    <p class="meta">Same-window bookmarklets replace the current page. New-window bookmarklets may be affected by popup blockers.</p>
  </article>
<?php foreach ($bookmarklets as $bookmarklet): ?>
  <article class="card">
    <h2><?= $e($bookmarklet['label']) ?></h2>
    <p><?= $e($bookmarklet['description']) ?></p>
    <p><a
      href="#"
      data-bookmarklet="pending"
      data-bookmarklet-kind="<?= $e($bookmarklet['bookmarklet_kind']) ?>"
      data-bookmarklet-mode="<?= $e($bookmarklet['mode']) ?>"
    ><?= $e($bookmarklet['label']) ?></a></p>
    <p class="meta"><?= $e($bookmarklet['mode'] === 'new-window' ? 'Opens Compose Thread in a new window.' : 'Opens Compose Thread in this tab.') ?></p>
  </article>
<?php endforeach; ?>
</section>
