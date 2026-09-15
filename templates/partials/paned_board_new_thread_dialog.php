<dialog class="paned-new-thread-dialog" data-paned-new-thread-dialog>
  <div class="paned-dialog-titlebar">
    <span>New Thread</span>
    <button type="button" class="paned-dialog-close" data-paned-new-thread-cancel aria-label="Cancel">&times;</button>
  </div>
<?= $indent($partial('partials/thread_compose_form.php', [
    'action' => '/compose/thread',
    'boardTags' => 'general',
    'subject' => '',
    'body' => '',
    'compact' => false,
    'formClass' => 'paned-compose-form',
]), 1) ?>
</dialog>
