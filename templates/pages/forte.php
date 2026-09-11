<div class="paned-window">
  <div class="paned-menubar">
    <span>File</span><span>Edit</span><span>View</span><span>Thread</span><span>Navigate</span><span>Help</span>
  </div>
  <div class="paned-toolbar">
    <button type="button" class="paned-toolbar-btn" disabled>
      <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="2" y="2" width="12" height="12" fill="none" stroke="currentColor"/><line x1="8" y1="5" x2="8" y2="11" stroke="currentColor"/><line x1="5" y1="8" x2="11" y2="8" stroke="currentColor"/></svg>
      <span>New</span>
    </button>
    <button type="button" class="paned-toolbar-btn" disabled>
      <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M9 3 L3 8 L9 13" fill="none" stroke="currentColor"/><path d="M3 8 H13" fill="none" stroke="currentColor"/></svg>
      <span>Reply</span>
    </button>
    <span class="paned-toolbar-sep"></span>
    <button type="button" class="paned-toolbar-btn" data-paned-prev>
      <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M11 3 L5 8 L11 13" fill="none" stroke="currentColor"/></svg>
      <span>Prev</span>
    </button>
    <button type="button" class="paned-toolbar-btn" data-paned-next>
      <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M5 3 L11 8 L5 13" fill="none" stroke="currentColor"/></svg>
      <span>Next</span>
    </button>
    <span class="paned-toolbar-sep"></span>
    <button type="button" class="paned-toolbar-btn" disabled>
      <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8 a5 5 0 1 1 1.6 3.6" fill="none" stroke="currentColor"/><path d="M3 11 v-3 h3" fill="none" stroke="currentColor"/></svg>
      <span>Refresh</span>
    </button>
  </div>
  <div class="paned-panes-stack">
<?= $indent($partial('partials/paned_list_pane.php', ['replyTree' => $replyTree, 'rootPostId' => (string) $thread['root_post_id']]), 2) ?>
<?= $indent($partial('partials/paned_content_pane.php', ['posts' => $posts, 'thread' => $thread]), 2) ?>
  </div>
  <div class="paned-statusbar">
    <span><?= count($posts) ?> post<?= count($posts) === 1 ? '' : 's' ?> in thread</span>
    <span>Forte reader</span>
  </div>
</div>
