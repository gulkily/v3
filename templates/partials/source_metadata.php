<?php
$sourcePath = (string) ($source_path ?? '');
$sourceCommit = (string) ($source_commit_sha ?? '');
$sourcePathHref = (string) ($source_path_href ?? '');
$sourceCommitHref = (string) ($source_commit_href ?? '');
$sourceSignaturePath = (string) ($source_signature_path ?? '');
$sourceSignatureHref = (string) ($source_signature_href ?? '');
$sourceCommitIsUnavailable = $sourceCommit === '' || $sourceCommit === 'no-git' || $sourceCommit === 'git-error';
?>
<?php if ($sourcePath !== ''): ?>
<p class="meta">Source:
<?php if ($sourcePathHref !== ''): ?>
  <a href="<?= $e($sourcePathHref) ?>"><?= $e($sourcePath) ?></a>
<?php else: ?>
  <span><?= $e($sourcePath) ?></span>
<?php endif; ?>
</p>
<p class="meta">Commit:
<?php if (!$sourceCommitIsUnavailable): ?>
<?php if ($sourceCommitHref !== ''): ?>
  <a href="<?= $e($sourceCommitHref) ?>" title="<?= $e($sourceCommit) ?>"><?= $e(substr($sourceCommit, 0, 12)) ?></a>
<?php else: ?>
  <span title="<?= $e($sourceCommit) ?>"><?= $e(substr($sourceCommit, 0, 12)) ?></span>
<?php endif; ?>
<?php else: ?>
  <span>commit unavailable</span>
<?php endif; ?>
</p>
<?php if ($sourceSignaturePath !== ''): ?>
<p class="meta">Signature:
<?php if ($sourceSignatureHref !== ''): ?>
  <a href="<?= $e($sourceSignatureHref) ?>"><?= $e($sourceSignaturePath) ?></a>
<?php else: ?>
  <span><?= $e($sourceSignaturePath) ?></span>
<?php endif; ?>
</p>
<?php endif; ?>
<?php endif; ?>
