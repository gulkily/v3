<section class="stack">
  <div class="paned-window">
    <div class="paned-titlebar">
      <span class="paned-titlebar-label">Forte — [<?= $e($title) ?>]</span>
      <span class="paned-titlebar-controls"><span>_</span><span>&#9633;</span><span>&times;</span></span>
    </div>
    <div class="paned-menubar">
      <span>File</span><span>Edit</span><span>View</span><span>Thread</span><span>Navigate</span><span>Help</span>
    </div>
    <div class="paned-toolbar">
      <a class="paned-toolbar-button" href="/threads/<?= $e($thread['root_post_id']) ?>">Back to standard view</a>
    </div>
<?= $indent($partial('partials/paned_list_pane.php', ['replyTree' => $replyTree]), 2) ?>
<?= $indent($partial('partials/paned_content_pane.php', ['posts' => $posts, 'thread' => $thread]), 2) ?>
  </div>
</section>
