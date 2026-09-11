<?php
/**
 * @var array<int, array<string, mixed>> $threads
 */
?>
<div class="paned-content-pane" data-paned-board-content-pane>
  <article class="paned-content-post" data-paned-board-content-placeholder>
    <div class="paned-content-head">
      <div class="paned-content-subject">No thread selected</div>
    </div>
    <div class="body">Select a thread from the list to preview it here.</div>
  </article>
<?php foreach ($threads as $thread): ?>
<?php $replyCount = (int) ($thread['reply_count'] ?? 0); ?>
  <article class="paned-content-post" data-paned-board-content-post-id="<?= $e($thread['root_post_id']) ?>" hidden>
    <div class="paned-content-head">
      <div class="paned-content-subject"><?= $e($threadTitle($thread)) ?></div>
      <div class="paned-content-meta">
        <span>From: <?= $author($thread) ?></span>
        <span><?= $timestamp((string) ($thread['root_post_created_at'] ?? '')) ?></span>
      </div>
    </div>
    <div class="body"><?= $br($thread['root_post_body'] ?? $thread['body_preview']) ?></div>
<?php if ($replyCount > 0): ?>
    <button
      type="button"
      class="paned-reply-toggle"
      data-paned-reply-toggle="<?= $e($thread['root_post_id']) ?>"
      data-paned-reply-url="/forte/threads/<?= $e($thread['root_post_id']) ?>/replies"
      data-paned-reply-label-collapsed="Show <?= $replyCount ?> repl<?= $replyCount === 1 ? 'y' : 'ies' ?>"
      data-paned-reply-label-expanded="Hide replies"
      aria-expanded="false"
    >Show <?= $replyCount ?> repl<?= $replyCount === 1 ? 'y' : 'ies' ?></button>
    <div class="paned-reply-container" data-paned-reply-container="<?= $e($thread['root_post_id']) ?>" hidden></div>
<?php endif; ?>
  </article>
<?php endforeach; ?>
</div>
