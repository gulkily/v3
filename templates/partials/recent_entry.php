<?php
/**
 * One contribution on Recent, in the board's thread-row shape: the thread
 * title links to the exact post, a one-line excerpt shows what is new, and the
 * compact meta says who, when and whether it is a reply.
 *
 * @var array<string, mixed> $item
 */
$showExcerpt = $item['excerpt'] !== '' && $item['excerpt'] !== $item['context_title'];
?>
<?php $thumbnail = $item['thumbnail'] ?? null; ?>
<article id="post-<?= $e($item['post_id']) ?>" class="thread-row recent-entry<?= $thumbnail !== null ? ' thread-row--thumb' : '' ?>" data-post-id="<?= $e($item['post_id']) ?>" data-heat="<?= $heat($item['created_at'] ?? null) ?>">
<?php if ($thumbnail !== null): ?>
  <?= $partial('partials/row_thumbnail.php', ['thumbnail' => $thumbnail, 'href' => $item['href']]) ?>
<?php endif; ?>
  <h3 class="thread-row__title"><a href="<?= $e($item['href']) ?>"><?= $e($item['context_title']) ?></a></h3>
<?php if ($showExcerpt): ?>
  <p class="thread-row__excerpt"><?= $e($item['excerpt']) ?></p>
<?php endif; ?>
  <p class="meta thread-row__meta"><?= $rowMeta($item) ?> · <?= $item['is_reply'] ? 'Reply' : 'New thread' ?></p>
</article>
