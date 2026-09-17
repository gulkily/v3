<?php
/**
 * @var array<string, mixed> $item
 * @var bool $isSelected
 */
$itemId = (string) $item['id'];
$forteLink = $item['forte_link'] ?? ['href' => '', 'label' => ''];
$commitSha = (string) ($item['source_commit_sha'] ?? '');
// The commit manifest itself is rendered once per unique commit sha (see
// paned_activity_detail_pane.php), not inline here: most items reuse a
// handful of shared bootstrap/seed commits, and duplicating a
// thousands-of-files manifest into every item that touched one made the
// page tens of megabytes. JS moves the matching shared block into view
// here (data-paned-activity-commit-sha is how it finds it) when this
// article is selected.
$hasCommitManifest = ($item['source_commit_files'] ?? []) !== [] && $commitSha !== '';
?>
<article class="paned-content-post" data-paned-activity-content-item-id="<?= $e($itemId) ?>"<?= $hasCommitManifest ? ' data-paned-activity-commit-sha="' . $e($commitSha) . '"' : '' ?><?= $isSelected ? '' : ' hidden' ?>>
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
<?php if (!$hasCommitManifest): ?>
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
    </div>
  </div>
</article>
