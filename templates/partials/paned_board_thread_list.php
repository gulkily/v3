<?php
/**
 * @var array<int, array<string, mixed>> $threads
 * @var string $selectedTag
 */
$selectedTag ??= '';
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
?>
<div class="paned-list-pane">
  <div class="paned-list-head">
    <span class="paned-list-subject-head">Subject</span>
    <span class="paned-list-from-head">From</span>
    <span class="paned-list-date-head">Date</span>
    <span class="paned-list-replies-head">Replies</span>
  </div>
  <div class="paned-list-body" data-paned-board-list-body>
<?php foreach ($threads as $thread): ?>
<?php
$tags = $threadTags($thread);
$visible = $selectedTag === '' || in_array($selectedTag, $tags, true);
?>
    <div class="paned-list-row" data-paned-thread-id="<?= $e($thread['root_post_id']) ?>" data-paned-thread-tags="<?= $e(implode(',', $tags)) ?>"<?= $visible ? '' : ' hidden' ?>>
      <span class="paned-list-subject"><?= $e($threadTitle($thread)) ?></span>
      <span class="paned-list-from"><?= $author($thread) ?></span>
      <span class="paned-list-date"><?= $timestamp((string) ($thread['root_post_created_at'] ?? '')) ?></span>
      <span class="paned-list-replies"><?= (int) $thread['reply_count'] ?></span>
    </div>
<?php endforeach; ?>
  </div>
</div>
