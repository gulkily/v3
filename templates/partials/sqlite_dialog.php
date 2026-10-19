<dialog class="sqlite-dialog" data-query-dialog aria-labelledby="query-dialog-title">
  <header class="sqlite-dialog-header">
    <h2 id="query-dialog-title">Database query</h2>
    <button type="button" data-close-query autofocus>Close</button>
  </header>
<?= $partial('pages/sqlite_viewer.php', ['embedded' => true]) ?>
</dialog>
