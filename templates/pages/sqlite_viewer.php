<section class="stack" data-sqlite-viewer>
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
    <p><button type="button" data-action="load-sqlite">Load Database</button></p>
    <p class="meta" data-role="sqlite-status" aria-live="polite">Database not loaded.</p>
  </article>
  <article class="card" data-role="sqlite-query-panel" hidden>
    <h2>Run query</h2>
    <label>Preset query
      <select data-role="sqlite-query-select">
        <option value="">Choose a query</option>
      </select>
    </label>
    <label>SQL
      <textarea data-role="sqlite-query-input" rows="6" spellcheck="false" placeholder="SELECT ..."></textarea>
    </label>
    <p><button type="button" data-action="run-sqlite-query">Run Query</button></p>
    <p class="meta" data-role="sqlite-query-status" aria-live="polite">Queries run only after the database is loaded.</p>
    <div class="sqlite-result-scroll" data-role="sqlite-query-results"></div>
  </article>
  <article class="card" data-role="sqlite-explorer" hidden>
    <h2>Explore tables</h2>
    <label>Table
      <select data-role="sqlite-table-select"></select>
    </label>
    <div class="sqlite-result-scroll" data-role="sqlite-table-details"></div>
    <div class="sqlite-result-scroll" data-role="sqlite-table-preview"></div>
  </article>
</section>
