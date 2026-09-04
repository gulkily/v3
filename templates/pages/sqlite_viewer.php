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
    <p><strong>Local query pack:</strong> <a href="/downloads/sqlite_query_catalog.sql">sqlite_query_catalog.sql</a></p>
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
    <p class="meta" data-role="sqlite-query-status" aria-live="polite" role="alert">Queries run only after the database is loaded.</p>
    <details data-role="sqlite-effective-query" hidden>
      <summary>View effective query</summary>
      <h3>Count query</h3>
      <pre data-role="sqlite-effective-count"></pre>
      <h3>Data query</h3>
      <pre data-role="sqlite-effective-data"></pre>
    </details>
    <div class="sqlite-result-scroll" data-role="sqlite-query-results"></div>
  </article>
  <article class="card" data-role="sqlite-explorer" hidden>
    <h2>Explore tables</h2>
    <label>Table
      <select data-role="sqlite-table-select"></select>
    </label>
    <div class="sqlite-tabs" role="tablist" aria-label="Table view">
      <button type="button" class="sqlite-tab is-active" role="tab" aria-selected="true" aria-controls="sqlite-data-panel" id="sqlite-data-tab" data-sqlite-tab="data">Data</button>
      <button type="button" class="sqlite-tab" role="tab" aria-selected="false" aria-controls="sqlite-columns-panel" id="sqlite-columns-tab" data-sqlite-tab="columns">Columns</button>
    </div>
    <div class="sqlite-tab-panel sqlite-result-scroll" data-role="sqlite-table-preview" id="sqlite-data-panel" role="tabpanel" aria-labelledby="sqlite-data-tab" tabindex="0"></div>
    <div class="sqlite-tab-panel sqlite-result-scroll" data-role="sqlite-table-details" id="sqlite-columns-panel" role="tabpanel" aria-labelledby="sqlite-columns-tab" tabindex="0" hidden></div>
  </article>
</section>
