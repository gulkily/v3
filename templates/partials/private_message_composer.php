<article
  class="card"
  data-private-message-composer
  data-recipient-username-token="<?= $e($recipientUsernameToken) ?>"
  data-sender-username-token="<?= $e($senderUsernameToken) ?>"
  data-recipient-label="<?= $e($recipientLabel) ?>"
<?php if (($successUrl ?? '') !== ''): ?>
  data-private-message-success-url="<?= $e($successUrl) ?>"
<?php endif; ?>
>
<?php if (!($compact ?? false)): ?>
  <h2>Message <?= $e($recipientLabel) ?></h2>
<?php endif; ?>
  <form class="stack" data-private-message-form>
    <label>
      <?= ($compact ?? false) ? '' : 'Message' ?>
      <textarea name="plaintext" rows="3" maxlength="65536" aria-label="Message to <?= $e($recipientLabel) ?>" placeholder="Write a message…" required></textarea>
    </label>
    <div class="private-message-actions">
      <button type="submit">Send private message</button>
<?php if ($compact ?? false): ?>
      <button type="button" data-role="private-message-latest">Latest message</button>
<?php endif; ?>
    </div>
  </form>
  <p class="meta" data-role="private-message-encryption-note" title="Encrypted to every approved key associated with this username.">Encrypted to all approved keys.</p>
  <p class="meta" data-role="private-message-feedback" role="status" aria-live="polite" hidden></p>
  <button type="button" data-role="private-message-send-retry" hidden>Check previous send</button>
<?php if ($compact ?? false): ?>
  <div data-role="read-feedback" hidden>
    <p class="meta" data-role="read-status" role="status" aria-live="polite"></p>
    <button type="button" data-role="read-retry">Retry seen status</button>
    <a data-role="read-reopen" href="<?= $e($successUrl ?? '/messages') ?>" hidden>Reopen conversation</a>
  </div>
<?php endif; ?>
</article>
