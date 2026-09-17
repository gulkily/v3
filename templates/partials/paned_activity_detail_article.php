<?php
/**
 * @var array<string, mixed> $item
 * @var bool $isSelected
 */
$itemId = (string) $item['id'];
$forteLink = $item['forte_link'] ?? ['href' => '', 'label' => ''];
?>
<article class="paned-content-post" data-paned-activity-content-item-id="<?= $e($itemId) ?>"<?= $isSelected ? '' : ' hidden' ?>>
  <div class="paned-content-head">
    <div class="paned-content-subject"><?= $e((string) $item['kind']) ?></div>
    <div class="paned-content-meta">
      <span><?= $timestamp((string) ($item['created_at'] ?? '')) ?></span>
    </div>
  </div>
  <div class="post-card paned-post-card">
    <div class="body">
      <p><?= $e((string) ($item['label'] ?? '')) ?></p>
<?php if ((string) ($forteLink['href'] ?? '') !== ''): ?>
      <p><a href="<?= $e($forteLink['href']) ?>"><?= $e($forteLink['label']) ?></a></p>
<?php endif; ?>
<?php if ((string) ($item['author_label'] ?? '') === 'reply-agent'): ?>
      <p class="meta">Author: reply-agent <span class="agent-label">automated reply agent</span></p>
<?php endif; ?>
<?php if (($item['source_commit_files'] ?? []) === []): ?>
<?= $indent($partial('partials/source_metadata.php', [
        'source_path' => $item['source_path'] ?? '',
        'source_commit_sha' => $item['source_commit_sha'] ?? '',
        'source_path_href' => $item['source_path_href'] ?? '',
        'source_commit_href' => $item['source_commit_href'] ?? '',
        'source_signature_path' => $item['source_signature_path'] ?? '',
        'source_signature_href' => $item['source_signature_href'] ?? '',
        'source_signature_status' => $item['source_signature_status'] ?? '',
      ]), 3) ?>
<?php endif; ?>
<?= $indent($partial('partials/activity_commit_manifest.php', [
        'files' => $item['source_commit_files'] ?? [],
        'commit_sha' => $item['source_commit_sha'] ?? '',
        'commit_href' => $item['source_commit_href'] ?? '',
      ]), 3) ?>
    </div>
  </div>
</article>
