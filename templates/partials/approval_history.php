<?php
$historyAccountBase = $historyAccountBase ?? '/user/';
$historyProfileBase = $historyProfileBase ?? '/profiles/';
$keyOnly = (bool) ($approvalHistory['profile_scope'] ?? false); ?>
<article id="account-keys" class="card account-key-history<?= $keyOnly ? ' account-key-history-single' : '' ?>" aria-label="<?= $keyOnly ? 'Key approval' : 'Account key history' ?>">
  <h2><?= $keyOnly ? 'Key approval' : 'Account key history' ?></h2>
<?php if (!$keyOnly): ?>
  <p class="meta account-key-history-note"><?= (int) $approvalHistory['total'] ?> approved keys.</p>
<?php elseif ($approvalHistory['rows'] === []): ?>
  <p>This key is not approved.</p>
<?php endif; ?>
  <ul class="account-key-history-list">
<?php foreach ($approvalHistory['rows'] as $grant): ?>
<?php
$when = $grant['accepted_at'] ?? $grant['recorded_at'] ?? null;
$whenLabel = $grant['accepted_at'] !== null ? 'Accepted' : (($grant['recorded_at_kind'] ?? null) === 'commit' ? 'Committed' : 'Recorded');
$classification = match ($grant['classification']) {
    'new_account' => 'New account',
    'own_account_key' => 'Additional key',
    'cross_account_key' => 'Cross-account key',
    default => $grant['kind'] === 'root_seed' ? 'Root seed' : '',
};
?>
  <li class="account-key-history-entry">
<?php if (!$keyOnly): ?>
    <h3 class="account-key-history-heading"><a href="<?= $e($historyProfileBase) ?><?= $e($grant['profile_slug']) ?>" title="<?= $e($grant['profile_slug']) ?>" aria-label="<?= $e($grant['profile_slug']) ?>"><?= $e($shortenProfileSlug($grant['profile_slug'])) ?></a></h3>
<?php endif; ?>
    <p class="account-key-history-metadata">Approved by <strong><?= $e($grant['approver_label']) ?></strong><?php if ($when !== null): ?> · <?= $e($whenLabel) ?>: <?= $timestamp($when) ?><?php else: ?> · Date not recorded<?php endif; ?><?php if ($grant['source_path'] !== null): ?>
<?php $evidenceUrl = $grant['kind'] === 'root_seed'
    ? '/approvals/root/' . rawurlencode(pathinfo($grant['source_path'], PATHINFO_FILENAME))
    : '/posts/' . rawurlencode(pathinfo($grant['source_path'], PATHINFO_FILENAME)); ?>
      · <a href="<?= $e($evidenceUrl) ?>">Approval evidence</a>
<?php endif; ?></p>
<?php if ($classification !== ''): ?>
    <p class="meta"><?= $e($classification) ?><?= $grant['actor_is_operator'] === true && $grant['classification'] === 'cross_account_key' ? ' · operator override' : '' ?></p>
<?php endif; ?>
<?php if ($grant['existing_key_count'] !== null || $grant['receipt_path'] !== null || ($grant['target_username_at_acceptance'] !== null && $grant['target_username_at_acceptance'] !== $approvalHistory['username'])): ?>
    <details>
      <summary class="meta">Details</summary>
<?php if ($grant['target_username_at_acceptance'] !== null && $grant['target_username_at_acceptance'] !== $approvalHistory['username']): ?>
      <p class="meta">Account at acceptance: <?= $e($grant['target_username_at_acceptance']) ?>.</p>
<?php endif; ?>
<?php if ($grant['existing_key_count'] !== null): ?>
      <p class="meta"><?= (int) $grant['existing_key_count'] ?> prior keys · Cross-account policy: <?= $grant['cross_account_allowed'] ? 'enabled' : 'restricted' ?>.</p>
<?php endif; ?>
<?php if ($grant['actor_is_operator'] !== null): ?>
      <p class="meta">Authority: <?= $grant['actor_is_operator'] ? 'root-approved operator' : 'member' ?>.</p>
<?php endif; ?>
<?php if ($grant['receipt_path'] !== null): ?>
      <p><a href="/source/current/<?= $e($grant['receipt_path']) ?>">Acceptance receipt</a></p>
<?php endif; ?>
    </details>
<?php endif; ?>
  </li>
<?php endforeach; ?>
  </ul>
<?php if ($keyOnly): ?>
  <p class="meta account-key-history-footer"><a href="<?= $e($historyAccountBase) ?><?= $e($approvalHistory['username']) ?>#account-keys">All account keys</a></p>
<?php endif; ?>
<?php if ($approvalHistory['previous_offset'] !== null): ?>
  <a href="<?= $e($historyAccountBase) ?><?= $e($approvalHistory['username']) ?>?key_offset=<?= (int) $approvalHistory['previous_offset'] ?>#account-keys">Previous keys</a>
<?php endif; ?>
<?php if ($approvalHistory['next_offset'] !== null): ?>
  <a href="<?= $e($historyAccountBase) ?><?= $e($approvalHistory['username']) ?>?key_offset=<?= (int) $approvalHistory['next_offset'] ?>#account-keys">More keys</a>
<?php endif; ?>
</article>
