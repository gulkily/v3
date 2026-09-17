<?php
/**
 * @var list<array{status:string,path:string,previous_path:string,role:string,href:string,signature_signer_identity:string,signature_public_key_path:string,signature_public_key_href:string,signature_key_status:string}> $files
 */
$files ??= [];
?>
<?php if ($files !== []): ?>
<section class="activity-commit-manifest">
  <h3>Commit files (<?= $e((string) count($files)) ?>)</h3>
  <ul>
<?php foreach ($files as $file): ?>
    <li>
      <span class="meta"><?= $e($file['status']) ?> · <?= $e($file['role']) ?></span>
      <span class="activity-commit-manifest__path">
<?php if ($file['previous_path'] !== ''): ?>
      <span><?= $e($file['previous_path']) ?> → </span>
<?php endif; ?>
<?php if ($file['href'] !== ''): ?>
      <a href="<?= $e($file['href']) ?>"><?= $e($file['path']) ?></a>
<?php else: ?>
      <span><?= $e($file['path']) ?></span>
<?php endif; ?>
      </span>
<?php if ($file['role'] === 'detached signature'): ?>
      <span class="activity-commit-manifest__signer meta">Signer:
<?php if ($file['signature_signer_identity'] !== ''): ?>
        <?= $e($file['signature_signer_identity']) ?>
<?php else: ?>
        unavailable
<?php endif; ?>
      </span>
      <span class="activity-commit-manifest__key meta">Public key:
<?php if ($file['signature_public_key_href'] !== ''): ?>
        <a href="<?= $e($file['signature_public_key_href']) ?>"><?= $e($file['signature_public_key_path']) ?></a>
<?php else: ?>
        <?= $e($file['signature_key_status'] !== '' ? $file['signature_key_status'] : 'unavailable') ?>
<?php endif; ?>
      </span>
<?php endif; ?>
    </li>
<?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
