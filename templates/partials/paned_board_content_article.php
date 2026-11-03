<?php
/**
 * @var array<string, mixed> $thread
 * @var array<int, array{post: array<string, mixed>, children: array}> $replyTree
 * @var bool $isSelectedThread
 */
$replyCount = (int) ($thread['reply_count'] ?? 0);
$viewerHasLiked = isset($viewerLikedThreadIds[(string) $thread['root_post_id']]);
$viewerHasFlaggedRoot = isset($viewerFlaggedPostIds[(string) $thread['root_post_id']]);
$signalActors ??= null;
$rootSignalPosts = is_array($signalActors) ? ($signalActors['posts'][(string) $thread['root_post_id']] ?? []) : [];
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
<?php if (($thread['retracted_at'] ?? null) !== null): ?>
      <div class="body retracted-post" data-retracted="true"><p class="meta"><?= $e(\ForumRewrite\Support\RetractedPostNotice::text($thread)) ?></p></div>
<?php else: ?>
<?php if (isset($thread['root_post_body'])): ?>
      <div class="body<?= $isMarkdownPost($thread, 'root_post_body_format') ? ' post-markdown' : '' ?>"><?= $renderPostBody($thread, (string) $thread['root_post_body'], 'root_post_body_format') ?></div>
<?php else: ?>
      <div class="body"><?= $br($thread['body_preview']) ?></div>
<?php endif; ?>
<?php endif; ?>
<?= $indent($partial('partials/post_signals.php', [
    'signals' => [
        'like' => is_array($signalActors) ? ($signalActors['thread_likes'] ?? []) : [],
        'flag' => $rootSignalPosts['flag'] ?? [],
        'title_author' => is_array($signalActors) ? ($signalActors['title_author'] ?? null) : null,
    ],
    'signalsScope' => 'thread',
    'signalsClass' => 'paned-post-signals',
]), 3) ?>
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
