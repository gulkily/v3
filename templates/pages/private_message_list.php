<section class="stack" data-conversation-list data-page-cursor="<?= $e($page['page_cursor']) ?>" data-next-cursor="<?= $e($page['next_cursor'] ?? '') ?>" data-viewer="<?= $e($viewerProfile['username_token']) ?>">
  <article class="card">
    <h1>Messages</h1>
    <details data-role="new-message">
      <summary>New message</summary>
      <form class="stack" data-role="recipient-form" action="/messages" method="get">
        <label for="message-recipient">Username</label>
        <input id="message-recipient" name="username" type="text" list="message-recipients" autocomplete="off" autocapitalize="none" spellcheck="false" maxlength="64" required aria-describedby="recipient-help recipient-feedback">
        <datalist id="message-recipients">
<?php foreach ($recipientSuggestions as $suggestion): ?>
<?php if ($suggestion['username_token'] !== $viewerProfile['username_token']): ?>
          <option value="<?= $e($suggestion['username_token']) ?>"></option>
<?php endif; ?>
<?php endforeach; ?>
        </datalist>
        <p class="meta" id="recipient-help">Choose a suggestion or enter a username.</p>
        <button type="submit">Open conversation</button>
        <p class="feedback" id="recipient-feedback" data-role="recipient-feedback" role="status" hidden></p>
      </form>
    </details>
  </article>
  <div data-role="empty"<?= $page['conversations'] !== [] ? ' hidden' : '' ?>>
    <p>No messages yet</p>
    <button type="button" data-action="new-message">New message</button>
  </div>
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
