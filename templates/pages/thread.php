<section class="stack"<?= $createdPostId !== '' ? ' data-created-post-id="' . $e($createdPostId) . '"' : '' ?>>
  <article class="card" data-thread-reactions-root data-thread-id="<?= $e($thread['root_post_id']) ?>">
    <h1><?= $e($title) ?></h1>
    <p class="meta"><?= $contentMeta($thread, 'root_post_created_at', '') ?></p>
<?php if ($thread['thread_labels'] !== []): ?>
    <p class="meta">Labels: <?= $e(implode(', ', $thread['thread_labels'])) ?></p>
<?php endif; ?>
<?php if ((int) $thread['reply_count'] > 0): ?>
    <p class="meta"><?= (int) $thread['reply_count'] ?> <?= (int) $thread['reply_count'] === 1 ? 'reply' : 'replies' ?></p>
<?php endif; ?>
    <div class="button-row button-row-natural thread-reaction-row">
      <button
        type="button"
        class="thread-reaction-button"
        data-action="apply-thread-tag"
        data-tag="like"
        data-applied-label="Liked"
        aria-pressed="<?= $viewerHasLiked ? 'true' : 'false' ?>"
<?= $viewerHasLiked ? ' disabled="disabled"' : '' ?>
      ><?= $viewerHasLiked ? 'Liked' : 'Like' ?></button>
    </div>
    <p class="meta thread-reaction-feedback" data-role="thread-reaction-feedback" hidden></p>
  </article>
<?php foreach ($posts as $post): ?>
<?= $indent($partial('partials/post_card.php', ['post' => $post]), 1) ?>
<?php endforeach; ?>
</section>
