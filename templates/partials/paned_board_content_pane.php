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
  <article class="paned-content-post" data-paned-board-content-post-id="<?= $e($thread['root_post_id']) ?>" hidden>
    <div class="paned-content-head">
      <div class="paned-content-subject"><?= $e($threadTitle($thread)) ?></div>
      <div class="paned-content-meta">
        <span>From: <?= $author($thread) ?></span>
        <span><?= $timestamp((string) ($thread['root_post_created_at'] ?? '')) ?></span>
      </div>
    </div>
    <div class="body"><?= $br($thread['body_preview']) ?></div>
    <div class="paned-content-actions">
      <a href="/threads/<?= $e($thread['root_post_id']) ?>/forte">Open in Forte &rarr;</a>
    </div>
  </article>
<?php endforeach; ?>
</div>
