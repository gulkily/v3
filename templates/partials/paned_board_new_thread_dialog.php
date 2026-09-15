<dialog class="paned-new-thread-dialog" data-paned-new-thread-dialog>
  <div class="paned-compose-head">New Thread</div>
<?= $indent($partial('partials/thread_compose_form.php', [
    'action' => '/compose/thread',
    'boardTags' => 'general',
    'subject' => '',
    'body' => '',
    'compact' => false,
]), 1) ?>
</dialog>
