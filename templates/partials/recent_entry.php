<article class="recent-entry" data-post-id="<?= $e($item['post_id']) ?>">
  <p class="meta recent-byline"><?= $contentMeta($item, 'created_at', '') ?></p>
  <p class="recent-context"><?= $item['is_reply'] ? 'Reply in' : 'New thread' ?>
<?php if ($item['context_id'] !== null): ?>
    <a href="<?= $e('/threads/' . rawurlencode($item['thread_id'])) ?>"><?= $e($item['context_title']) ?></a>
<?php else: ?>
    <span><?= $e($item['context_title']) ?></span>
<?php endif; ?>
  </p>
  <p class="recent-excerpt"><a href="<?= $e($item['href']) ?>"><?= $e($item['excerpt']) ?></a></p>
</article>
