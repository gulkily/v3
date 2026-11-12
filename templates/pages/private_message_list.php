<section class="stack" data-conversation-list data-private-message-cache="<?= ($privateMessageCacheEnabled ?? false) ? '1' : '0' ?>" data-approved-identity="<?= $e((string) ($viewerProfile['identity_id'] ?? '')) ?>" data-page-cursor="<?= $e($page['page_cursor']) ?>" data-next-cursor="<?= $e($page['next_cursor'] ?? '') ?>" data-viewer="<?= $e($viewerProfile['username_token']) ?>">
  <h1 class="visually-hidden">Messages</h1>
  <div class="board-empty" data-role="empty"<?= $page['conversations'] !== [] ? ' hidden' : '' ?>>
    <h2>No messages yet</h2>
  </div>
  <p class="message-list-alert" data-role="unread-retry" hidden>Couldn’t check for new messages. <button type="button" aria-label="Retry unread status">Retry</button></p>
  <div class="thread-rows message-list-rows" data-role="rows">
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
