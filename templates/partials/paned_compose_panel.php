<?php
/**
 * @var array<string, mixed> $thread
 */
$rootPostId = (string) $thread['root_post_id'];
?>
<div class="paned-compose-panel" data-paned-compose-panel hidden>
  <div class="paned-compose-head">Compose Reply</div>
<?= $indent($partial('partials/reply_form.php', [
    'threadId' => $rootPostId,
    'parentId' => $rootPostId,
    'boardTags' => 'general',
    'body' => '',
    'submitLabel' => 'Post reply',
    'showBodyLabel' => false,
    'formClass' => 'paned-compose-form',
]), 1) ?>
</div>
