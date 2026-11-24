<?php
/**
 * The page a public-only device's QR opens on another device of the account
 * (docs/concepts/private_key_access.md). The server has decided the state for this viewer.
 *
 * @var string $state confirm|self|has_access|not_allowed
 * @var array<string, mixed> $target
 * @var string $accountName
 */
?>
<section class="stack private-access-confirm">
<?php if ($state === 'confirm'): ?>
<?= $partial('partials/private_access_waiting.php', ['waitingKeys' => [$target], 'accountName' => $accountName]) ?>
<?php else: ?>
  <article class="card">
    <h1>Confirm a device</h1>
<?php if ($state === 'self'): ?>
    <p>Open this link on another of your devices, one that can read your messages.</p>
<?php elseif ($state === 'has_access'): ?>
    <p>This key can already read its account's messages.</p>
<?php else: ?>
    <p>Only another device of <?= $e($accountName) ?> that can read its messages, or an operator, can confirm this key.</p>
<?php endif; ?>
    <p class="private-access-code">Code <strong><?= $e(\ForumRewrite\Security\PrivateKeyAccess::shortCode((string) $target['identity_id'])) ?></strong></p>
  </article>
<?php endif; ?>
</section>
