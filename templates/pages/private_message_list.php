<section class="stack" data-conversation-list data-page-cursor="<?= $e($page['page_cursor']) ?>" data-next-cursor="<?= $e($page['next_cursor'] ?? '') ?>" data-viewer="<?= $e($viewerProfile['username_token']) ?>">
  <article class="card"><h1>Messages</h1></article>
  <p data-role="empty"<?= $page['conversations'] !== [] ? ' hidden' : '' ?>>No messages yet</p>
  <div class="stack" data-role="rows">
<?php foreach ($page['conversations'] as $conversation): ?>
<?= $indent($partial('partials/private_message_conversation_row.php', ['conversation' => $conversation]), 4) ?>
<?php endforeach; ?>
  </div>
  <p class="meta" data-role="list-status" role="status" aria-live="polite"></p>
  <div>
    <button type="button" data-action="load-more"<?= $page['next_cursor'] === null ? ' hidden' : '' ?>>Load more</button>
    <button type="button" data-action="retry-list" hidden>Retry loading</button>
    <a href="/messages" data-action="restart-list" hidden>Reload Messages</a>
  </div>
  <noscript><p>Enable JavaScript to load more conversations and decrypt previews.</p></noscript>
  <template data-role="row-template">
<?= $indent($partial('partials/private_message_conversation_row.php'), 4) ?>
  </template>
</section>
