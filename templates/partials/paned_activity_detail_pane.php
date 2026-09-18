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
?>
<?= $indent($partial('partials/paned_activity_detail_article.php', [
    'item' => $item,
    'isSelected' => $isSelected,
]), 1) ?>
<?php endforeach; ?>
</div>
