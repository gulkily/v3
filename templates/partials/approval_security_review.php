<div class="account-key-history">
  <p>Record author: <?= $e($item['author_label'] ?? 'unknown') ?>. Event time: <?= $e($item['created_at']) ?>.</p>
<?php if (isset($item['security_account'])): ?>
  <p><a href="/user/<?= $e($item['security_account']) ?>#account-keys">Affected account: <?= $e($item['security_account']) ?></a></p>
<?php endif; ?>
<?php if (isset($item['security_revocation'])): ?>
<?php $revocation = $item['security_revocation']; ?>
  <p>Key revoked: <code><?= $e($revocation['identity_id']) ?></code>.</p>
  <p>Revocation cutoff: <?= $e($revocation['revocation_cutoff']) ?>. Accepted: <?= $e($revocation['accepted_at']) ?>.</p>
  <p><a href="/source/current/<?= $e($revocation['receipt_path']) ?>">Revocation acceptance receipt</a></p>
<?php elseif (($item['security_review'] ?? null) !== null): ?>
<?php $receipt = $item['security_review']; $context = $receipt['context']; ?>
  <p>Accepted <?= $e($receipt['accepted_at']) ?>: <?= $e($context['classification']) ?>. Approved keys before acceptance: <?= (int) $context['existing_key_count'] ?>. Cross-account policy: <?= $context['cross_account_allowed'] ? 'enabled' : 'restricted' ?>. <?= $context['actor_is_operator'] ? 'Root-approved operator authority' : 'Member authority' ?>.</p>
  <p><code><?= $e($context['target_identity_id']) ?></code> authorized by <?= $e($context['actor_username']) ?>.</p>
  <p><a href="/source/current/<?= $e(\ForumRewrite\Security\ApprovalAcceptance::path($receipt['post_id'])) ?>">Acceptance receipt</a></p>
<?php elseif ($item['kind'] !== 'site_feature_flag'): ?>
  <p>Historical acceptance classification, count, authority, and policy are unknown. This record is not evidence of a policy violation.</p>
<?php endif; ?>
</div>
