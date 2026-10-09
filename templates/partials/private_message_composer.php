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
  <h2>Message <?= $e($recipientLabel) ?></h2>
  <p class="meta">Encrypted to every approved key associated with this username.</p>
  <form class="stack" data-private-message-form>
    <label>
      Message
      <textarea name="plaintext" rows="6" maxlength="65536" required></textarea>
    </label>
    <button type="submit">Send private message</button>
  </form>
  <p class="meta" data-role="private-message-feedback" hidden></p>
</article>
