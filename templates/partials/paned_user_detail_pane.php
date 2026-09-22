<?php
/**
 * @var string $usernameToken
 * @var int $approvedThreadCount
 * @var int $approvedPostCount
 * @var array<int, array<string, mixed>> $approvedThreads
 * @var array<int, array<string, mixed>> $approvedPosts
 */
?>
<article class="paned-content-post" data-paned-user-detail-token="<?= $e($usernameToken) ?>">
  <div class="paned-content-head">
    <div class="paned-content-subject"><?= $e($usernameToken) ?></div>
    <div class="paned-content-meta">
      <span><?= (int) $approvedThreadCount ?> thread<?= $approvedThreadCount === 1 ? '' : 's' ?></span>
      <span><?= (int) $approvedPostCount ?> post<?= $approvedPostCount === 1 ? '' : 's' ?></span>
    </div>
  </div>
  <div class="paned-user-detail-section">
    <h3>Threads</h3>
<?php if ($approvedThreads === []): ?>
    <p class="meta">No visible threads.</p>
<?php else: ?>
<?php foreach ($approvedThreads as $thread): ?>
    <p><a href="/forte?selected=<?= $e($thread['root_post_id']) ?>"><?= $e($threadTitle($thread)) ?></a> <span class="meta"><?= $forteContentMeta($thread, 'root_post_created_at', '') ?></span></p>
<?php endforeach; ?>
<?php endif; ?>
  </div>
  <div class="paned-user-detail-section">
    <h3>Posts</h3>
<?php if ($approvedPosts === []): ?>
    <p class="meta">No visible posts.</p>
<?php else: ?>
<?php foreach ($approvedPosts as $post): ?>
    <p><a href="/forte?selected=<?= $e($post['thread_id']) ?>&amp;created_post_id=<?= $e($post['post_id']) ?>#post-<?= $e($post['post_id']) ?>"><?= $e($post['post_id']) ?></a> <span class="meta"><?= $forteContentMeta($post, 'created_at', '') ?></span></p>
<?php endforeach; ?>
<?php endif; ?>
  </div>
  <p class="paned-standalone-back"><a href="/forte/user/<?= $e($usernameToken) ?>">View full profile</a></p>
</article>
