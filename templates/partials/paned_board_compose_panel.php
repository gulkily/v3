<div class="paned-compose-panel" data-paned-compose-panel hidden>
  <div class="paned-compose-head">Compose Reply</div>
<?= $indent($partial('partials/reply_form.php', [
    'threadId' => '',
    'parentId' => '',
    'boardTags' => 'general',
    'body' => '',
    'submitLabel' => 'Post reply',
    'showBodyLabel' => false,
    'formClass' => 'paned-compose-form',
    'returnTo' => '/forte',
]), 1) ?>
</div>
