<?php
/**
 * @var array<string, mixed> $item
 * @var bool $isSelected
 */
$itemId = (string) $item['id'];
$forteLink = $item['forte_link'] ?? ['href' => '', 'label' => ''];
// forte_link's label is the target post id for every real content link -
// the one exception is site_feature_flag, whose link isn't a post at all
// (it points at the site feature-flags tool), so it's excluded from the
// content-summary dialog's click-interception.
$isContentLink = (string) ($item['kind'] ?? '') !== 'site_feature_flag';
$commitSha = (string) ($item['source_commit_sha'] ?? '');
// Only this item's own relevant files (its record, an identity_bootstrap's
// paired identity record, its signature, and the signer's public key) are
// shown here - never the rest of the commit, which can run to thousands of
// files for items that happen to share a large historical commit. See
// Application::activityItemRelevantFiles().
$relevantFiles = $item['relevant_files'] ?? [];
$hasRelevantFiles = $relevantFiles !== [];
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
      <p><a href="<?= $e($forteLink['href']) ?>"<?= $isContentLink ? ' data-forte-content-link data-post-id="' . $e($forteLink['label']) . '"' : '' ?>><?= $e($forteLink['label']) ?></a></p>
<?php endif; ?>
<?php if ((string) ($item['author_label'] ?? '') === 'reply-agent'): ?>
      <p class="meta">Author: reply-agent <span class="agent-label">automated reply agent</span></p>
<?php endif; ?>
<?php if ($hasRelevantFiles): ?>
<?= $indent($partial('partials/activity_commit_manifest.php', [
        'files' => $relevantFiles,
        'commit_sha' => $commitSha,
        'commit_href' => $item['source_commit_href'] ?? '',
        'heading' => 'Relevant files',
      ]), 3) ?>
<?php else: ?>
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
