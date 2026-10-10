<section class="stack" data-conversation-list data-page-cursor="<?= $e($page['page_cursor']) ?>" data-next-cursor="<?= $e($page['next_cursor'] ?? '') ?>" data-viewer="<?= $e($viewerProfile['username_token']) ?>">
  <article class="card"><h1>Messages</h1></article>
  <p data-role="empty"<?= $page['conversations'] !== [] ? ' hidden' : '' ?>>No messages yet</p>
  <div class="stack" data-role="rows">
<?php foreach ($page['conversations'] as $conversation): ?>
<?= $indent($partial('partials/private_message_conversation_row.php', ['conversation' => $conversation]), 4) ?>
<?php endforeach; ?>
  </div>
  <template data-role="row-template">
<?= $indent($partial('partials/private_message_conversation_row.php'), 4) ?>
  </template>
</section>
