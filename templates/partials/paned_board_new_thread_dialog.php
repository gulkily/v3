<?php $selectedTag = (string) ($selectedTag ?? ''); ?>
<dialog class="paned-new-thread-dialog" data-paned-new-thread-dialog data-compose-root>
  <div class="paned-dialog-titlebar">
    <span>New Thread</span>
    <button type="button" class="paned-dialog-close" data-paned-new-thread-cancel aria-label="Cancel">
      <svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M1 1 L9 9 M9 1 L1 9" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
    </button>
  </div>
<?= $indent($partial('partials/thread_compose_form.php', [
    'action' => '/compose/thread',
    'boardTags' => 'general',
    'subject' => '',
    'body' => '',
    'compact' => false,
    'formClass' => 'paned-compose-form',
    'returnTo' => '/forte' . ($selectedTag !== '' ? ('?tag=' . rawurlencode($selectedTag)) : ''),
]), 1) ?>
</dialog>
