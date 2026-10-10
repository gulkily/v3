<?php $item = $message ?? []; ?>
<article class="private-message" data-private-message-id="<?= $e($item['message_id'] ?? '') ?>" data-sender="<?= $e($item['sender_username_token'] ?? '') ?>" data-created-at="<?= $e($item['created_at'] ?? '') ?>">
  <header class="private-message-meta">
    <span data-role="message-sender"><?= $e($item['sender_username_token'] ?? '') ?></span>
    <time datetime="<?= $e($item['created_at'] ?? '') ?>"><?= $e($item['created_at'] ?? '') ?></time>
    <span data-role="private-message-verification" title="Signature verified" aria-label="Signature verified" hidden>✓</span>
  </header>
  <p class="meta" data-role="private-message-reader-error">Decrypting and verifying message...</p>
  <pre data-role="private-message-plaintext" hidden></pre>
  <details data-role="private-message-unavailable-details" hidden>
    <summary>Message unavailable · Details</summary>
    <p class="meta" data-role="private-message-unavailable-explanation"></p>
  </details>
  <button type="button" data-role="private-message-read-retry" hidden>Retry reading message</button>
</article>
