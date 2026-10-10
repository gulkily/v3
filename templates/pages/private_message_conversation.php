<section class="stack private-conversation" data-private-message-mailbox data-mailbox="conversation" data-counterpart-username-token="<?= $e($counterpartUsernameToken) ?>" data-viewer-username-token="<?= $e($viewerProfile['username_token']) ?>">
  <article class="card">
    <h1>Conversation with <a href="/user/<?= $e(rawurlencode($counterpartUsernameToken)) ?>"><?= $e($counterpartUsernameToken) ?></a></h1>
    <p><a href="/messages">Back to Messages</a></p>
  </article>
  <div data-role="private-message-transcript" aria-label="Conversation messages">
  <p data-role="private-message-empty"<?= $messages !== [] ? ' hidden' : '' ?>>No private messages with this user yet.</p>
<?php foreach ($messages as $message): ?>
<?= $indent($partial('partials/private_message_item.php', ['message' => $message]), 2) ?>
<?php endforeach; ?>
  </div>
  <template data-role="private-message-template"><?= $partial('partials/private_message_item.php', ['message' => []]) ?></template>
<?= $indent($partial('partials/private_message_composer.php', [
    'recipientUsernameToken' => $counterpartUsernameToken,
    'senderUsernameToken' => (string) $viewerProfile['username_token'],
    'recipientLabel' => $counterpartUsernameToken,
    'successUrl' => '/messages/conversation/' . rawurlencode($counterpartUsernameToken),
]), 2) ?>
</section>
