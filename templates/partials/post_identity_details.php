<?php
$isBootstrapPost = (string) ($post['post_id'] ?? '') === (string) ($post['thread_id'] ?? '');
$boardTags = json_decode((string) ($post['board_tags_json'] ?? ''), true);
$isBootstrapPost = $isBootstrapPost
    && is_array($boardTags)
    && in_array('identity', $boardTags, true)
    && in_array('internal', $boardTags, true);
$authorPublicKey = trim((string) ($post['author_public_key'] ?? ''));
$authorPublicKeyPath = trim((string) ($post['author_public_key_path'] ?? ''));
$authorPublicKeyHref = trim((string) ($post['author_public_key_href'] ?? ''));
if (!$isBootstrapPost && $authorPublicKeyHref === '') {
    return;
}
?>
<?php if ($authorPublicKeyHref !== ''): ?>
  <p class="meta">Public key: <a href="<?= $e($authorPublicKeyHref) ?>"><?= $e($authorPublicKeyPath !== '' ? $authorPublicKeyPath : 'Open public key') ?></a></p>
<?php endif; ?>
<?php if ($isBootstrapPost): ?>
  <details class="account-key-advanced">
    <summary>Advanced / technical details</summary>
    <div class="stack">
      <div>
        <p class="account-key-label">Public key</p>
<?php if ($authorPublicKey !== ''): ?>
        <pre><?= $e($authorPublicKey) ?></pre>
<?php else: ?>
        <p class="meta">Public key unavailable.</p>
<?php endif; ?>
      </div>
    </div>
  </details>
<?php endif; ?>
