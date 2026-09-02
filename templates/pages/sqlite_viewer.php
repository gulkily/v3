<section class="stack">
  <article class="card">
    <div class="nav board-controls-nav">
<?php foreach ($toolNavOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </div>
  </article>
  <article class="card" data-sqlite-viewer>
    <h1>SQLite Viewer</h1>
    <p>Inspect the published read-model database in your browser. This viewer is read-only: queries run locally against a copy loaded into this page, and are not sent to the server.</p>
    <p><strong>Source:</strong> <a href="/downloads/read_model.sqlite3">/downloads/read_model.sqlite3</a></p>
    <p><button type="button" data-action="load-sqlite">Load Database</button></p>
    <p class="meta" data-role="sqlite-status" aria-live="polite">Database not loaded.</p>
  </article>
  <article class="card" data-role="sqlite-explorer" hidden>
    <h2>Explore tables</h2>
    <label>Table
      <select data-role="sqlite-table-select"></select>
    </label>
    <div data-role="sqlite-table-details"></div>
    <div data-role="sqlite-table-preview"></div>
  </article>
</section>
