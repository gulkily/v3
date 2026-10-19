<section class="stack" data-sqlite-viewer data-runtime-url="<?= htmlspecialchars((string) ($runtimeUrl ?? '/assets/sql-wasm.wasm'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
<?php if (empty($embedded)): ?>
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
  </article>
<?php endif; ?>
<?php if (!empty($embedded)): ?>
  <p class="meta" data-role="sqlite-status" aria-live="polite" hidden></p>
<?php else: ?>
  <article class="card">
    <h1>SQLite Viewer</h1>
    <p>Read-only. Queries run locally in your browser.</p>
    <p><a href="/downloads/read_model.sqlite3">Download database</a></p>
    <p class="meta" data-role="sqlite-source-details" hidden></p>
    <p><a href="/downloads/sqlite_query_catalog.sql">Download query pack</a></p>
    <p><button type="button" data-action="load-sqlite">Load Database</button></p>
    <p class="meta" data-role="sqlite-status" aria-live="polite" hidden></p>
  </article>
<?php endif; ?>
  <article class="card" data-role="sqlite-query-panel" hidden>
    <div class="sqlite-query-layout" data-query-layout>
      <div class="sqlite-query-editor">
<?php if (empty($embedded)): ?>
        <label>Preset query
          <select data-role="sqlite-query-select">
            <option value="">Choose a query</option>
          </select>
        </label>
        <label>SQL
          <textarea data-role="sqlite-query-input" cols="80" rows="24" spellcheck="false" placeholder="SELECT ..."></textarea>
        </label>
<?php else: ?>
        <select data-role="sqlite-query-select" aria-label="Preset query">
          <option value="">Choose a query</option>
        </select>
        <textarea data-role="sqlite-query-input" aria-label="SQL query" cols="80" rows="24" spellcheck="false" placeholder="SELECT ..."></textarea>
<?php endif; ?>
        <p><button type="button" data-action="run-sqlite-query">Run Query</button></p>
        <p class="meta" data-role="sqlite-query-status" aria-live="polite" role="alert"></p>
      </div>
      <div class="sqlite-query-output">
        <div class="sqlite-result-scroll" data-role="sqlite-query-results"></div>
        <div class="sqlite-query-bottom">
          <div data-role="sqlite-query-pagination"></div>
          <details data-role="sqlite-effective-query" hidden>
            <summary>View effective query</summary>
            <h3>Count query</h3>
            <pre data-role="sqlite-effective-count"></pre>
            <h3>Data query</h3>
            <pre data-role="sqlite-effective-data"></pre>
          </details>
        </div>
      </div>
    </div>
  </article>
<?php if (empty($embedded)): ?>
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
<?php endif; ?>
<?php if (!empty($embedded)): ?>
  <footer><a href="/tools/sqlite/">Open full viewer</a></footer>
<?php endif; ?>
</section>
