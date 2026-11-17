<?php
$sourcePath = (string) ($source_path ?? '');
$sourceCommit = (string) ($source_commit_sha ?? '');
$sourcePathHref = (string) ($source_path_href ?? '');
$sourceCommitHref = (string) ($source_commit_href ?? '');
$sourceSignaturePath = (string) ($source_signature_path ?? '');
$sourceSignatureHref = (string) ($source_signature_href ?? '');
$sourceSignatureStatus = (string) ($source_signature_status ?? '');
$sourceCommitIsUnavailable = $sourceCommit === '' || $sourceCommit === 'no-git' || $sourceCommit === 'git-error';
// 'rows' renders <tr> rows for a caller-supplied <table class="codebase-facts">.
$asRows = ($layout ?? '') === 'rows';
$open = static fn (string $label): string => ($asRows
    ? '<tr><th scope="row">' . $label . '</th><td>'
    : '<p class="meta">' . $label . ':') . "\n";
$close = ($asRows ? '</td></tr>' : '</p>') . "\n";
?>
<?php if ($evidence_unavailable ?? false): ?>
<?php if ($asRows): ?>
<tr><td colspan="2" role="status">Evidence unavailable. <a href="">Reload</a></td></tr>
<?php else: ?>
<p role="status">Evidence unavailable. <a href="">Reload</a></p>
<?php endif; ?>
<?php endif; ?>
<?php if ($sourcePath !== ''): ?>
<?= $open('Source') ?>
<?php if ($sourcePathHref !== ''): ?>
  <a href="<?= $e($sourcePathHref) ?>"><?= $e($sourcePath) ?></a>
<?php else: ?>
  <span><?= $e($sourcePath) ?></span>
<?php endif; ?>
<?= $close ?>
<?= $open('Commit') ?>
<?php if (!$sourceCommitIsUnavailable): ?>
<?php if ($sourceCommitHref !== ''): ?>
  <a href="<?= $e($sourceCommitHref) ?>" title="<?= $e($sourceCommit) ?>"><?= $e(substr($sourceCommit, 0, 12)) ?></a>
<?php else: ?>
  <span title="<?= $e($sourceCommit) ?>"><?= $e(substr($sourceCommit, 0, 12)) ?></span>
<?php endif; ?>
<?php else: ?>
  <span>commit unavailable</span>
<?php endif; ?>
<?= $close ?>
<?php if ($sourceSignaturePath !== '' || $sourceSignatureStatus !== ''): ?>
<?= $open('Signature') ?>
<?php if ($sourceSignaturePath !== ''): ?>
<?php if ($sourceSignatureHref !== ''): ?>
  <a href="<?= $e($sourceSignatureHref) ?>"><?= $e($sourceSignaturePath) ?></a>
<?php else: ?>
  <span><?= $e($sourceSignaturePath) ?></span>
<?php endif; ?>
<?php else: ?>
  <span><?= $e($sourceSignatureStatus) ?></span>
<?php endif; ?>
<?= $close ?>
<?php endif; ?>
<?php endif; ?>
