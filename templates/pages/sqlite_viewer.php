<section class="stack">
  <article class="card">
    <div class="nav board-controls-nav">
<?php foreach ($toolNavOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </div>
  </article>
  <article class="card">
    <h1>SQLite Viewer</h1>
    <p>Inspect the published read-model database in your browser. This viewer is read-only: queries run locally against a copy loaded into this page, and are not sent to the server.</p>
    <p><strong>Source:</strong> <a href="/downloads/read_model.sqlite3">/downloads/read_model.sqlite3</a></p>
    <p><button type="button" disabled>Load Database</button></p>
    <p class="meta">The interactive viewer is not available until its browser database runtime is installed.</p>
  </article>
</section>
