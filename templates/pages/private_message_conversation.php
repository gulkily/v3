<?php
// Theme hook (inert): a stable hue per person, from their name.
$personHue = hexdec(substr(hash('crc32b', $counterpartUsernameToken), 0, 4)) % 360;
?>
<section class="stack private-conversation" data-person="<?= $e($counterpartUsernameToken) ?>" style="--person: <?= (int) $personHue ?>" data-private-message-mailbox data-mailbox="conversation" data-counterpart-username-token="<?= $e($counterpartUsernameToken) ?>" data-viewer-username-token="<?= $e($viewerProfile['username_token']) ?>" data-history-page-cursor="<?= $e($historyPage['page_cursor'] ?? '') ?>" data-history-next-cursor="<?= $e($historyPage['next_cursor'] ?? '') ?>">
  <input type="hidden" data-role="private-message-read-token" value="<?= $e($historyPage['read_token'] ?? '') ?>">
  <header class="card conversation-head">
    <a class="conversation-head__back" href="/messages" aria-label="Back to Messages"><span aria-hidden="true">‹</span></a>
    <h1><a href="/user/<?= $e(rawurlencode($counterpartUsernameToken)) ?>"><?= $e($counterpartUsernameToken) ?></a></h1>
    <p class="meta conversation-head__privacy" data-role="private-message-encryption-note">Only you and <?= $e($counterpartUsernameToken) ?> can read these, on your approved devices.</p>
  </header>
  <div class="private-message-history-controls" data-role="history-controls">
    <button type="button" data-role="history-load"<?= empty($historyPage['next_cursor']) ? ' hidden' : '' ?>>Load older</button>
    <button type="button" data-role="history-restart" hidden>Restart history</button>
    <p class="meta" data-role="history-status" role="status" aria-live="polite"><?= empty($historyPage['next_cursor']) ? 'All history loaded.' : '' ?></p>
  </div>
  <div data-role="private-message-transcript" aria-label="Conversation messages">
  <div class="private-message-empty" data-role="private-message-empty"<?= $messages !== [] ? ' hidden' : '' ?>>
    <div class="private-message-empty__starfield" data-starfield-slot aria-hidden="true"></div>
    <p>No messages with <?= $e($counterpartUsernameToken) ?> yet.</p>
  </div>
<?php foreach ($messages as $message): ?>
<?= $indent($partial('partials/private_message_item.php', ['message' => $message]), 2) ?>
<?php endforeach; ?>
  </div>
  <template data-role="private-message-template"><?= $partial('partials/private_message_item.php', ['message' => []]) ?></template>
<?= $indent($partial('partials/private_message_composer.php', [
    'recipientUsernameToken' => $counterpartUsernameToken,
    'senderUsernameToken' => (string) $viewerProfile['username_token'],
    'recipientLabel' => $counterpartUsernameToken,
    'compact' => true,
    'successUrl' => '/messages/conversation/' . rawurlencode($counterpartUsernameToken),
]), 2) ?>
</section>
