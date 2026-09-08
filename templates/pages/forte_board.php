<div class="paned-window">
  <h1>Forte board view (stub - stage 2)</h1>
<?= $indent($partial('partials/paned_folder_tree.php', ['tagGroups' => $tagGroups, 'totalThreadCount' => count($threads)]), 1) ?>
<?= $indent($partial('partials/paned_board_thread_list.php', ['threads' => $threads]), 1) ?>
</div>
