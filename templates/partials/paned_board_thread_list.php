<?php
/**
 * @var array<int, array<string, mixed>> $threads
 * @var string $selectedTag
 * @var string $sortColumn
 * @var string $sortDir
 */
$selectedTag ??= '';
$sortColumn ??= '';
$sortDir ??= '';
$ariaSort = static function (string $column) use ($sortColumn, $sortDir): string {
    if ($column !== $sortColumn) {
        return 'none';
    }

    return $sortDir === 'desc' ? 'descending' : 'ascending';
};
$threadTags = static function (array $thread): array {
    $tags = [];
    foreach (['board_tags', 'thread_labels'] as $field) {
        $values = $thread[$field] ?? [];
        if (!is_array($values)) {
            continue;
        }

        foreach ($values as $value) {
            if (is_string($value) && $value !== '' && !in_array($value, $tags, true)) {
                $tags[] = $value;
            }
        }
    }

    return $tags;
};
$authorText = static function (array $thread): string {
    $label = trim((string) ($thread['author_label'] ?? ''));

    return $label === '' ? 'guest' : $label;
};
$tabStopAssigned = false;
?>
<div class="paned-list-pane">
  <div class="paned-list-head" data-paned-sort-head>
    <span class="paned-list-subject-head" aria-sort="<?= $ariaSort('subject') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="subject">Subject</button></span>
    <span class="paned-list-from-head" aria-sort="<?= $ariaSort('from') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="from">From</button></span>
    <span class="paned-list-date-head" aria-sort="<?= $ariaSort('date') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="date">Date</button></span>
    <span class="paned-list-replies-head" aria-sort="<?= $ariaSort('replies') ?>"><button type="button" class="paned-sort-button" data-paned-sort-column="replies">Replies</button></span>
  </div>
  <div class="paned-list-body" data-paned-board-list-body role="listbox" aria-label="Threads">
<?php foreach ($threads as $thread): ?>
<?php
$tags = $threadTags($thread);
$visible = $selectedTag === '' || in_array($selectedTag, $tags, true);
$isTabStop = $visible && !$tabStopAssigned;
if ($isTabStop) {
    $tabStopAssigned = true;
}
?>
    <div
      class="paned-list-row"
      data-paned-thread-id="<?= $e($thread['root_post_id']) ?>"
      data-paned-thread-tags="<?= $e(implode(',', $tags)) ?>"
      role="option"
      aria-selected="false"
      tabindex="<?= $isTabStop ? '0' : '-1' ?>"
      <?= $visible ? '' : 'hidden' ?>
    >
      <span class="paned-list-subject"><?= $e($threadTitle($thread)) ?></span>
      <span class="paned-list-from"><?= $e($authorText($thread)) ?></span>
      <span class="paned-list-date"><?= $timestamp((string) ($thread['root_post_created_at'] ?? '')) ?></span>
      <span class="paned-list-replies"><?= (int) $thread['reply_count'] ?></span>
    </div>
<?php endforeach; ?>
  </div>
</div>
