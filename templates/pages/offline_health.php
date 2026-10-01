<section
  class="stack"
  data-offline-health
  data-reader-url="/offline/reader/"
  data-snapshot-url="/offline/snapshot.sqlite3"
  data-runtime-url="<?= htmlspecialchars((string) ($runtimeUrl ?? '/assets/sql-wasm.wasm'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
>
  <article class="card codebase-status-card" data-role="offline-health-status-card">
    <h1>Offline Reading</h1>
    <p class="codebase-status offline-health-status" data-role="offline-health-status" data-status="checking" aria-live="polite">CHECKING</p>
    <p class="meta" data-role="offline-health-summary" aria-live="polite">Checking offline reading status…</p>
  </article>
  <article class="card offline-health-card">
    <h2>Device</h2>
    <table class="codebase-facts">
      <tbody data-role="offline-health-checks" aria-live="polite"></tbody>
    </table>
  </article>
  <article class="card offline-health-card">
    <h2>Service worker</h2>
    <table class="codebase-facts">
      <tbody data-role="offline-worker-checks" aria-live="polite"></tbody>
    </table>
  </article>
  <article class="card offline-health-card">
    <h2>Saved archive</h2>
    <table class="codebase-facts">
      <tbody data-role="offline-archive-checks" aria-live="polite"></tbody>
    </table>
  </article>
  <article class="card offline-health-card">
    <h2>Published archive</h2>
    <table class="codebase-facts">
      <tbody data-role="offline-publication-checks" aria-live="polite"></tbody>
    </table>
  </article>
  <article class="card offline-health-card">
    <h2>Actions</h2>
    <p class="meta" data-role="offline-health-guidance" hidden></p>
    <div class="button-row button-row-natural">
      <button class="offline-health-action" type="button" data-action="recheck-offline-health">Check again</button>
      <button class="offline-health-action" type="button" data-action="refresh-offline-reader">Refresh saved reader</button>
      <a class="offline-health-action offline-health-open-archive" data-role="open-saved-archive" href="/offline/reader/" hidden>Open saved archive</a>
    </div>
    <noscript><p class="meta">Offline health checks require JavaScript.</p></noscript>
  </article>
</section>
