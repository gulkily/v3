<?php
$isInbox = $mailbox === 'inbox';
$counterpartLabel = $isInbox ? 'From' : 'To';
?>
<section class="stack" data-private-message-mailbox data-mailbox="<?= $e($mailbox) ?>">
  <article class="card">
    <h1><?= $isInbox ? 'Inbox' : 'Sent Messages' ?></h1>
    <p><a href="/messages/inbox"<?= $isInbox ? ' aria-current="page"' : '' ?>>Inbox</a> · <a href="/messages/sent"<?= !$isInbox ? ' aria-current="page"' : '' ?>>Sent</a></p>
  </article>

<?php if ($messages === []): ?>
  <article class="card">
    <p><?= $isInbox ? 'No private messages received.' : 'No private messages sent.' ?></p>
  </article>
<?php else: ?>
<?php foreach ($messages as $message): ?>
<?php $counterpart = $isInbox ? (string) $message['sender_username_token'] : (string) $message['recipient_username_token']; ?>
  <article class="card" data-private-message-id="<?= $e($message['message_id']) ?>">
    <p><strong><?= $counterpartLabel ?>:</strong> <a href="/messages/conversation/<?= $e($counterpart) ?>"><?= $e($counterpart) ?></a> <span class="meta" data-role="private-message-verification" title="Signature verified" aria-label="Signature verified" hidden>✓</span></p>
    <p><strong>Sent:</strong> <?= $e($message['created_at']) ?></p>
    <p class="feedback" data-role="private-message-reader-error" hidden></p>
    <button type="button" data-role="private-message-read-retry" hidden>Retry reading message</button>
    <pre data-role="private-message-plaintext" hidden></pre>
  </article>
<?php endforeach; ?>
<?php endif; ?>
</section>
