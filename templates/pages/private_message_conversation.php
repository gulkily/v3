<section class="stack" data-private-message-mailbox data-mailbox="conversation" data-counterpart-username-token="<?= $e($counterpartUsernameToken) ?>">
  <article class="card">
    <h1>Conversation with <?= $e($counterpartUsernameToken) ?></h1>
    <p><a href="/messages/inbox">Inbox</a> · <a href="/messages/sent">Sent</a></p>
  </article>
<?php if ($messages === []): ?>
  <article class="card"><p>No private messages with this user yet.</p></article>
<?php else: ?>
<?php foreach ($messages as $message): ?>
<?php $isOutgoing = (string) $message['sender_username_token'] === (string) $viewerProfile['username_token']; ?>
  <article class="card" data-private-message-id="<?= $e($message['message_id']) ?>">
    <p><strong><?= $isOutgoing ? 'To' : 'From' ?>:</strong> <?= $e($counterpartUsernameToken) ?> <span class="meta" data-role="private-message-verification" title="Siganture verified" aria-label="Siganture verified" hidden>✓</span></p>
    <p><strong>Sent:</strong> <?= $e($message['created_at']) ?></p>
    <p class="feedback" data-role="private-message-reader-error" hidden></p>
    <pre data-role="private-message-plaintext" hidden></pre>
  </article>
<?php endforeach; ?>
<?php endif; ?>
</section>
