<?php $conversation = $conversation ?? []; ?>
<article class="card message-list-row" data-conversation-row data-counterpart="<?= $e($conversation['counterpart'] ?? '') ?>" data-message-id="<?= $e($conversation['message_id'] ?? '') ?>">
  <a class="message-list-link" href="/messages/conversation/<?= $e(rawurlencode($conversation['counterpart'] ?? '')) ?>">
    <strong><span data-role="counterpart"><?= $e($conversation['counterpart'] ?? '') ?></span> <span class="meta" data-role="unread-indicator">…</span></strong>
    <span class="meta" data-role="verification" title="Signature verified" aria-label="Signature verified" hidden>✓</span>
    <time class="meta" data-role="time" datetime="<?= $e($conversation['created_at'] ?? '') ?>"><?= $e($conversation['created_at'] ?? '') ?></time>
    <span data-role="preview">Encrypted message</span>
  </a>
  <button type="button" data-action="retry-preview" hidden>Retry preview</button>
</article>
