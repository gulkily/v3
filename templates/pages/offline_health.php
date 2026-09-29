<section
  class="stack"
  data-offline-health
  data-reader-url="/offline/reader/"
  data-snapshot-url="/offline/snapshot.sqlite3"
  data-runtime-url="<?= htmlspecialchars((string) ($runtimeUrl ?? '/assets/sql-wasm.wasm'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"
>
  <article class="card">
    <h1>Offline Reading</h1>
    <p class="meta" data-role="offline-health-summary" aria-live="polite">Checking offline reading status…</p>
    <ul data-role="offline-health-checks" aria-live="polite"></ul>
    <p class="meta" data-role="offline-health-guidance" hidden></p>
    <button type="button" data-action="recheck-offline-health">Check again</button>
    <noscript><p class="meta">Offline health checks require JavaScript.</p></noscript>
  </article>
</section>
