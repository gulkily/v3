<article id="account-keys" class="card account-key-history" aria-label="Account key history">
  <h2>Account key history</h2>
  <p class="meta account-key-history-note"><?= (int) $approvalHistory['total'] ?> approved keys for <?= $e($approvalHistory['username']) ?>.</p>
  <ul class="account-key-history-list">
<?php foreach ($approvalHistory['rows'] as $grant): ?>
  <li class="account-key-history-entry">
    <h3 class="account-key-history-heading"><a href="/profiles/<?= $e($grant['profile_slug']) ?>"><?= $e($grant['profile_slug']) ?></a></h3>
    <p class="account-key-history-metadata">Approved by <strong><?= $e($grant['approver_label']) ?></strong> · Accepted: <?= $e($grant['accepted_at'] ?? 'unknown') ?><?php if ($grant['source_path'] !== null): ?>
<?php $evidenceUrl = $grant['kind'] === 'root_seed'
    ? '/approvals/root/' . rawurlencode(pathinfo($grant['source_path'], PATHINFO_FILENAME))
    : '/posts/' . rawurlencode(pathinfo($grant['source_path'], PATHINFO_FILENAME)); ?>
      · <a href="<?= $e($evidenceUrl) ?>">Approval evidence</a><?php if ($grant['receipt_path'] !== null): ?> · <a href="/source/current/<?= $e($grant['receipt_path']) ?>">Acceptance receipt</a><?php endif; ?>
<?php endif; ?></p>
    <p class="meta"><?= $e(match ($grant['classification']) {
        'new_account' => 'First-key sponsorship',
        'own_account_key' => 'Additional key authorized by the same account',
        'cross_account_key' => 'Additional key authorized by another account',
        default => $grant['kind'] === 'root_seed' ? 'Root-approved operator seed; acceptance classification unknown' : 'Historical classification unknown',
    }) ?><?php if ($grant['actor_is_operator'] === true): ?> — root-approved operator authority<?= $grant['classification'] === 'cross_account_key' ? ' (operator override)' : '' ?><?php endif; ?>.</p>
<?php if ($grant['target_username_at_acceptance'] !== null && $grant['target_username_at_acceptance'] !== $approvalHistory['username']): ?>
    <p class="meta">Account username at acceptance: <?= $e($grant['target_username_at_acceptance']) ?>.</p>
<?php endif; ?>
<?php if ($grant['existing_key_count'] !== null): ?>
    <p class="meta">Approved keys before acceptance: <?= (int) $grant['existing_key_count'] ?>. Cross-account approvals at acceptance: <?= $grant['cross_account_allowed'] ? 'enabled' : 'restricted' ?>.</p>
<?php endif; ?>
  </li>
<?php endforeach; ?>
  </ul>
<?php if ($approvalHistory['previous_offset'] !== null): ?>
  <a href="/user/<?= $e($approvalHistory['username']) ?>?key_offset=<?= (int) $approvalHistory['previous_offset'] ?>#account-keys">Previous keys</a>
<?php endif; ?>
<?php if ($approvalHistory['next_offset'] !== null): ?>
  <a href="/user/<?= $e($approvalHistory['username']) ?>?key_offset=<?= (int) $approvalHistory['next_offset'] ?>#account-keys">More keys</a>
<?php endif; ?>
</article>
