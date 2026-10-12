<section class="card stack" data-operator-role>
  <p><strong>Instance role:</strong> <?= $e($operatorView['status']) ?></p>
<?php if ($operatorView['status'] !== 'member' && $operatorView['status'] !== 'unavailable'): ?>
  <p class="meta">This role applies to all approved keys for this username. Pending keys have no operator access.</p>
<?php endif; ?>
<?php foreach ($operatorView['grants'] as $id => $grant): ?>
  <p class="meta">Grant from <?= $e($grant['from']) ?>: <?= $grant['revoked'] ? 'revoked' : 'unrevoked' ?>.
    <a href="/posts/<?= $e($id) ?>">Signed grant</a>
  </p>
<?php endforeach; ?>
<?php if ($operatorView['canManage'] && $operatorView['target'] !== null && $operatorView['status'] !== 'root operator'): ?>
  <form data-operator-action-form>
    <input type="hidden" name="action" value="grant">
    <input type="hidden" name="target_identity_id" value="<?= $e($operatorView['target']['identity_id']) ?>">
    <button type="submit"><?= $operatorView['status'] === 'operator' ? 'Add operator grant' : 'Make operator' ?></button>
    <p class="meta" data-operator-feedback role="status"></p>
  </form>
<?php endif; ?>
</section>
