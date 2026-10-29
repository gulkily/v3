<?php $conversation = $conversation ?? []; ?>
<article class="card message-list-row" data-conversation-row data-counterpart="<?= $e($conversation['counterpart'] ?? '') ?>" data-message-id="<?= $e($conversation['message_id'] ?? '') ?>">
  <a class="message-list-link" href="/messages/conversation/<?= $e(rawurlencode($conversation['counterpart'] ?? '')) ?>">
    <strong class="message-list-name"><span data-role="counterpart"><?= $e($conversation['counterpart'] ?? '') ?></span> <span class="visually-hidden" data-role="unread-indicator">…</span></strong>
    <span class="meta" data-role="verification" title="Signature verified" aria-label="Signature verified" hidden>✓</span>
    <time class="meta" data-role="time" datetime="<?= $e($conversation['created_at'] ?? '') ?>"><?= $e($conversation['created_at'] ?? '') ?></time>
    <span class="message-preview is-loading" data-role="preview">Encrypted message</span>
  </a>
  <button type="button" class="message-list-retry" data-action="retry-preview" aria-label="Retry preview" hidden>Retry</button>
</article>
