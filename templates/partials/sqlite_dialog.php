<dialog class="sqlite-dialog" data-query-dialog aria-labelledby="query-dialog-title">
  <header class="sqlite-dialog-header">
    <h2 id="query-dialog-title">Query viewer</h2>
    <span class="sqlite-dialog-zoom" role="group" aria-label="Text size">
      <button type="button" data-query-zoom="-1" aria-label="Smaller" title="Smaller"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor"/><line x1="10.2" y1="10.2" x2="14" y2="14" stroke="currentColor"/><line x1="5" y1="7" x2="9" y2="7" stroke="currentColor"/></svg></button>
      <button type="button" data-query-zoom="1" aria-label="Larger" title="Larger"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor"/><line x1="10.2" y1="10.2" x2="14" y2="14" stroke="currentColor"/><line x1="5" y1="7" x2="9" y2="7" stroke="currentColor"/><line x1="7" y1="5" x2="7" y2="9" stroke="currentColor"/></svg></button>
    </span>
    <button type="button" data-close-query autofocus>Close</button>
  </header>
<?= $partial('pages/sqlite_viewer.php', ['embedded' => true]) ?>
</dialog>
