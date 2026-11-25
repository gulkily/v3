<?php
/**
 * Keys of this account waiting for private access, for a device that can confirm them
 * (docs/concepts/private_key_access.md). Confirm signs a confirm record; Not me opens the
 * key's User Access page, where Retire is available. private_access_confirm.js repeats the
 * two headings below as rows are confirmed; change both together.
 *
 * @var list<array<string, mixed>> $waitingKeys
 * @var string $accountName
 */
$waitingKeys = $waitingKeys ?? [];
if ($waitingKeys === []) return;
$count = count($waitingKeys);
$visible = array_slice($waitingKeys, 0, 5);
$more = array_slice($waitingKeys, 5);
$row = static function (array $key) use ($e): string {
    $id = (string) $key['identity_id'];
    return '<li class="private-access-row" data-private-access-row data-target-identity-id="' . $e($id) . '">'
        . '<strong class="private-access-code">' . $e(\ForumRewrite\Security\PrivateKeyAccess::shortCode($id)) . '</strong> '
        . '<button type="button" data-action="confirm-private-access">Confirm</button> '
        . '<a href="/tools/user-access/profiles/' . $e(rawurlencode((string) $key['profile_slug'])) . '/" data-role="not-me">Not me</a>'
        . '<span class="meta" data-role="private-access-feedback" role="status" hidden></span></li>';
};
?>
<article class="card private-access-waiting" data-private-access-waiting data-account-name="<?= $e($accountName) ?>" aria-labelledby="private-access-waiting-heading">
  <h2 id="private-access-waiting-heading" data-role="private-access-heading"><?= $count === 1
      ? 'A new key for ' . $e($accountName) . ' wants to read your messages'
      : $e((string) $count) . ' keys for ' . $e($accountName) . ' want to read your messages' ?></h2>
  <p data-role="private-access-hint">Confirm only if the code matches a device of yours.</p>
  <p class="meta" data-role="private-access-card-feedback" role="status" hidden></p>
  <ul class="private-access-list">
<?php foreach ($visible as $key): ?>
    <?= $row($key) ?>

<?php endforeach; ?>
  </ul>
<?php if ($more !== []): ?>
  <details>
    <summary><?= $e((string) count($more)) ?> more</summary>
    <ul class="private-access-list">
<?php foreach ($more as $key): ?>
      <?= $row($key) ?>

<?php endforeach; ?>
    </ul>
  </details>
<?php endif; ?>
</article>
