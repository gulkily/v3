<?php
/**
 * @var array<string, mixed> $thread
 * @var array<int, array{post: array<string, mixed>, children: array}> $replyTree
 * @var bool $isSelectedThread
 */
$replyCount = (int) ($thread['reply_count'] ?? 0);
$viewerHasLiked = isset($viewerLikedThreadIds[(string) $thread['root_post_id']]);
$viewerHasFlaggedRoot = isset($viewerFlaggedPostIds[(string) $thread['root_post_id']]);
?>
  <article class="paned-content-post" data-paned-board-content-post-id="<?= $e($thread['root_post_id']) ?>" data-thread-reactions-root data-thread-id="<?= $e($thread['root_post_id']) ?>"<?= $isSelectedThread ? '' : ' hidden' ?>>
    <div class="paned-content-head">
      <div class="paned-content-subject"><?= $e($threadTitle($thread)) ?></div>
      <div class="paned-content-meta">
        <span>From: <?= $forteAuthor($thread) ?></span>
        <span><?= $timestamp((string) ($thread['root_post_created_at'] ?? '')) ?></span>
      </div>
    </div>
    <div class="post-card paned-post-card" data-post-id="<?= $e($thread['root_post_id']) ?>">
      <div class="body"><?= $br($thread['root_post_body'] ?? $thread['body_preview']) ?></div>
      <div class="paned-reaction-row">
        <button type="button" class="paned-reaction-button" data-action="apply-thread-tag" data-tag="like" data-applied-label="Liked" aria-pressed="<?= $viewerHasLiked ? 'true' : 'false' ?>"<?= $viewerHasLiked ? ' disabled' : '' ?>><?= $viewerHasLiked ? 'Liked' : 'Like' ?></button>
        <button type="button" class="paned-reaction-button" data-action="apply-post-tag" data-tag="flag" data-post-id="<?= $e($thread['root_post_id']) ?>" data-applied-label="Flagged" aria-pressed="<?= $viewerHasFlaggedRoot ? 'true' : 'false' ?>"<?= $viewerHasFlaggedRoot ? ' disabled' : '' ?>><?= $viewerHasFlaggedRoot ? 'Flagged' : 'Flag' ?></button>
        <a class="paned-permalink-link" href="/forte?selected=<?= $e($thread['root_post_id']) ?>&amp;created_post_id=<?= $e($thread['root_post_id']) ?>#post-<?= $e($thread['root_post_id']) ?>" title="Permalink to this post" aria-label="Permalink to this post">#</a>
      </div>
      <p class="paned-reaction-feedback" data-role="post-reaction-feedback" hidden></p>
    </div>
    <p class="paned-reaction-feedback" data-role="thread-reaction-feedback" hidden></p>
<?php if ($replyCount > 0): ?>
<?= $indent($partial('partials/paned_thread_reply_tree.php', ['replyTree' => $replyTree]), 2) ?>
<?php endif; ?>
  </article>
