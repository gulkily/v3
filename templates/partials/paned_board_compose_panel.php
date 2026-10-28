<div class="paned-compose-panel" data-paned-compose-panel data-compose-root hidden>
  <div class="paned-compose-head">Reply</div>
  <p class="paned-compose-status" data-role="compose-identity-status" hidden></p>
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
