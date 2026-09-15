<?php
/**
 * @var array<int, array<string, mixed>> $threads
 * @var array<string, array<int, array{post: array<string, mixed>, children: array}>> $replyTreesByThreadId
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
  <article class="paned-content-post" data-paned-board-content-post-id="<?= $e($thread['root_post_id']) ?>" data-thread-reactions-root data-thread-id="<?= $e($thread['root_post_id']) ?>" hidden>
    <div class="paned-content-head">
      <div class="paned-content-subject"><?= $e($threadTitle($thread)) ?></div>
      <div class="paned-content-meta">
        <span>From: <?= $author($thread) ?></span>
        <span><?= $timestamp((string) ($thread['root_post_created_at'] ?? '')) ?></span>
      </div>
    </div>
    <div class="post-card paned-post-card" data-post-id="<?= $e($thread['root_post_id']) ?>">
      <div class="body"><?= $br($thread['root_post_body'] ?? $thread['body_preview']) ?></div>
      <div class="paned-reaction-row">
        <button type="button" class="paned-reaction-button" data-action="apply-thread-tag" data-tag="like" data-applied-label="Liked" aria-pressed="false">Like</button>
        <button type="button" class="paned-reaction-button" data-action="apply-post-tag" data-tag="flag" data-post-id="<?= $e($thread['root_post_id']) ?>" data-applied-label="Flagged" aria-pressed="false">Flag</button>
      </div>
      <p class="paned-reaction-feedback" data-role="post-reaction-feedback" hidden></p>
    </div>
    <p class="paned-reaction-feedback" data-role="thread-reaction-feedback" hidden></p>
<?php if ($replyCount > 0): ?>
<?= $indent($partial('partials/paned_thread_reply_tree.php', ['replyTree' => $replyTreesByThreadId[$thread['root_post_id']] ?? []]), 2) ?>
<?php endif; ?>
  </article>
<?php endforeach; ?>
<?= $indent($partial('partials/paned_board_compose_panel.php'), 1) ?>
</div>
