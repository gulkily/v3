<section class="stack thread-list" data-offline-reader data-snapshot-url="/offline/snapshot.sqlite3" data-runtime-url="<?= htmlspecialchars((string) ($runtimeUrl ?? '/assets/sql-wasm.wasm'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <div class="offline-mode-bar" data-role="offline-mode-bar" hidden>offline mode</div>
  <p class="meta" data-role="offline-reader-status" aria-live="polite">Starting saved reader…</p>
  <div class="stack" data-role="offline-reader-content" hidden></div>
</section>
