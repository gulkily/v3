<section class="stack" data-private-message-mailbox data-mailbox="conversation" data-counterpart-username-token="<?= $e($counterpartUsernameToken) ?>">
  <article class="card">
    <h1>Conversation with <a href="/user/<?= $e(rawurlencode($counterpartUsernameToken)) ?>"><?= $e($counterpartUsernameToken) ?></a></h1>
    <p><a href="/messages">Back to Messages</a></p>
  </article>
<?php if ($messages === []): ?>
  <article class="card"><p>No private messages with this user yet.</p></article>
<?php else: ?>
<?php foreach ($messages as $message): ?>
<?php $isOutgoing = (string) $message['sender_username_token'] === (string) $viewerProfile['username_token']; ?>
  <article class="card" data-private-message-id="<?= $e($message['message_id']) ?>">
    <p><strong><?= $isOutgoing ? 'To' : 'From' ?>:</strong> <?= $e($counterpartUsernameToken) ?> <span class="meta" data-role="private-message-verification" title="Signature verified" aria-label="Signature verified" hidden>✓</span></p>
    <p><strong>Sent:</strong> <?= $e($message['created_at']) ?></p>
    <p class="feedback" data-role="private-message-reader-error" hidden></p>
    <button type="button" data-role="private-message-read-retry" hidden>Retry reading message</button>
    <pre data-role="private-message-plaintext" hidden></pre>
  </article>
<?php endforeach; ?>
<?php endif; ?>
<?= $indent($partial('partials/private_message_composer.php', [
    'recipientUsernameToken' => $counterpartUsernameToken,
    'senderUsernameToken' => (string) $viewerProfile['username_token'],
    'recipientLabel' => $counterpartUsernameToken,
    'successUrl' => '/messages/conversation/' . rawurlencode($counterpartUsernameToken),
]), 2) ?>
</section>
