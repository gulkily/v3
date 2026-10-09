<section class="stack thread-list" data-offline-reader data-snapshot-url="/offline/snapshot.sqlite3" data-update-url="/offline/update.sqlite3" data-runtime-url="<?= htmlspecialchars((string) ($runtimeUrl ?? '/assets/sql-wasm.wasm'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-reader-revision="<?= htmlspecialchars((string) ($readerRevision ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-snapshot-revision="<?= htmlspecialchars((string) ($snapshotRevision ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
  <div class="offline-mode-bar" data-role="offline-mode-bar" hidden>
    <span>offline mode</span>
    <a class="offline-mode-bar__outbox" href="/tools/outbox/">Outbox</a>
    <span class="offline-mode-bar__indicators" data-role="offline-mode-indicators">
      <span class="offline-mode-bar__indicator" data-role="offline-archive-indicator"></span>
      <span class="offline-mode-bar__indicator" data-role="offline-reader-indicator"></span>
    </span>
  </div>
  <p class="meta" data-role="offline-reader-status" aria-live="polite">Starting saved reader…</p>
  <div class="stack" data-role="offline-reader-content" hidden></div>
</section>
