<?php
$subject = $threadTitle($thread);
$showPinnedMarker ??= false;
$showLabels ??= false;
$isPinned = $showPinnedMarker && in_array('pinned', $thread['thread_labels'] ?? [], true);
?>
<article class="card thread-card" data-heat="<?= $heat($thread['last_activity_at'] ?? null, (int) ($thread['reply_count'] ?? 0)) ?>">
  <h2><a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($subject) ?></a><?php if ($isPinned): ?> <span class="pinned-thread-marker">Pinned</span><?php endif; ?></h2>
  <p class="meta"><?= $contentMeta($thread, 'root_post_created_at', '') ?></p>
<?php if ($showLabels && ($thread['thread_labels'] ?? []) !== []): ?>
  <p class="meta">Labels: <?= $e(implode(', ', $thread['thread_labels'])) ?></p>
<?php endif; ?>
  <p class="thread-card__preview"><?= $br($thread['body_preview']) ?></p>
<?php if ((int) $thread['reply_count'] > 0): ?>
  <p class="meta"><?= (int) $thread['reply_count'] ?> <?= (int) $thread['reply_count'] === 1 ? 'reply' : 'replies' ?></p>
<?php endif; ?>
</article>
