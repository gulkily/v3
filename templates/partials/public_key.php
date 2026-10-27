<details class="account-key-advanced" data-public-key-details>
  <summary>Public key</summary>
<?php if (trim((string) $publicKey) !== ''): ?>
  <div class="account-key-viewer-header">
    <span class="account-key-viewer-label">Public key</span>
    <button type="button" class="account-key-viewer-copy" data-copy-public-key hidden>Copy</button>
  </div>
  <pre data-role="public-key-viewer"><?= $e($publicKey) ?></pre>
  <p class="meta" role="status" data-key-copy-status hidden></p>
<?php else: ?>
  <p class="meta">Public key unavailable.</p>
<?php endif; ?>
</details>
