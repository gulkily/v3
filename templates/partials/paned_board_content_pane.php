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
<?= $indent($partial('partials/paned_thread_reply_tree.php', ['replyTree' => $replyTreesByThreadId[$thread['root_post_id']] ?? []]), 2) ?>
<?php endif; ?>
  </article>
<?php endforeach; ?>
<?= $indent($partial('partials/paned_board_compose_panel.php'), 1) ?>
</div>
