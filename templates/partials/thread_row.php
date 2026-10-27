<?php
/**
 * One thread in the `list` board: title, reply count, a one-line excerpt
 * when the body adds something beyond the title, and compact metadata.
 *
 * @var array<string, mixed> $thread
 */
$subject = $threadTitle($thread);
$replyCount = (int) $thread['reply_count'];
// Image references are data, not text; the excerpt skips them.
$excerpt = trim((string) preg_replace('/^' . preg_quote(\ForumRewrite\Images\ImageReference::PREFIX, '/') . '.*$/m', '', (string) $thread['body_preview']));
$showExcerpt = $excerpt !== '' && $excerpt !== trim($subject) && ($thread['retracted_at'] ?? null) === null;
?>
<article class="thread-row" data-heat="<?= $heat($thread['last_activity_at'] ?? null, $replyCount) ?>">
  <h3 class="thread-row__title"><a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($subject) ?></a></h3>
  <p class="thread-row__count"><?php if ($replyCount > 0): ?><span class="thread-row__count-number"><?= $replyCount ?></span> <span class="thread-row__count-label"><?= $replyCount === 1 ? 'reply' : 'replies' ?></span><?php endif; ?></p>
<?php if ($showExcerpt): ?>
  <p class="thread-row__excerpt"><?= $e($excerpt) ?></p>
<?php endif; ?>
  <p class="meta thread-row__meta"><?= $rowMeta($thread, 'root_post_created_at') ?></p>
<?= $partial('partials/event_block.php') ?>
</article>
