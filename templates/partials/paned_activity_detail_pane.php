<?php
/**
 * @var array<int, array<string, mixed>> $items
 * @var string $selectedItemId
 */
$selectedItemId ??= '';
$hasSelectedItem = false;
foreach ($items as $item) {
    if ((string) $item['id'] === $selectedItemId) {
        $hasSelectedItem = true;
        break;
    }
}
?>
<div class="paned-content-pane" data-paned-activity-content-pane>
  <article class="paned-content-post" data-paned-activity-content-placeholder<?= $hasSelectedItem ? ' hidden' : '' ?>>
    <div class="paned-content-head">
      <div class="paned-content-subject">No activity item selected</div>
    </div>
    <div class="body">Select an item from the list to see its detail here.</div>
  </article>
<?php foreach ($items as $item): ?>
<?php
$itemId = (string) $item['id'];
$isSelected = $selectedItemId !== '' && $itemId === $selectedItemId;
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
<?= $indent($partial('partials/source_metadata.php', [
          'source_path' => $item['source_path'] ?? '',
          'source_commit_sha' => $item['source_commit_sha'] ?? '',
          'source_path_href' => $item['source_path_href'] ?? '',
          'source_commit_href' => $item['source_commit_href'] ?? '',
          'source_signature_path' => $item['source_signature_path'] ?? '',
          'source_signature_href' => $item['source_signature_href'] ?? '',
          'source_signature_status' => $item['source_signature_status'] ?? '',
        ]), 4) ?>
      </div>
    </div>
  </article>
<?php endforeach; ?>
</div>
