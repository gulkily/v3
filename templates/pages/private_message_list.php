<section class="stack" data-conversation-list data-page-cursor="<?= $e($page['page_cursor']) ?>" data-next-cursor="<?= $e($page['next_cursor'] ?? '') ?>" data-viewer="<?= $e($viewerProfile['username_token']) ?>">
  <article class="card">
    <h1>Messages</h1>
    <p data-role="empty"<?= $page['conversations'] !== [] ? ' hidden' : '' ?>>No messages yet</p>
    <p class="message-list-alert" data-role="unread-retry" hidden>Couldn’t check for new messages. <button type="button" aria-label="Retry unread status">Retry</button></p>
  </article>
  <div class="stack" data-role="rows">
<?php foreach ($page['conversations'] as $conversation): ?>
<?= $indent($partial('partials/private_message_conversation_row.php', ['conversation' => $conversation]), 4) ?>
<?php endforeach; ?>
  </div>
  <div class="message-list-footer">
    <p class="meta visually-hidden" data-role="list-status" role="status" aria-live="polite"></p>
    <button type="button" data-action="load-more"<?= $page['next_cursor'] === null ? ' hidden' : '' ?>>Load more</button>
    <button type="button" class="message-list-retry" data-action="retry-list" aria-label="Retry loading" hidden>Retry</button>
    <a class="message-list-retry" href="/messages" data-action="restart-list" aria-label="Reload Messages" hidden>Retry</a>
  </div>
  <noscript><p>Enable JavaScript to load more conversations and decrypt previews.</p></noscript>
  <template data-role="row-template">
<?= $indent($partial('partials/private_message_conversation_row.php'), 4) ?>
  </template>
</section>
