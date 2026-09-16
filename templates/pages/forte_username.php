<?php
/**
 * @var string $usernameToken
 * @var array<int, array<string, mixed>> $approvedProfiles
 * @var array<int, array<string, mixed>> $unapprovedProfiles
 * @var int $approvedThreadCount
 * @var int $approvedPostCount
 * @var array<int, array<string, mixed>> $approvedThreads
 * @var array<int, array<string, mixed>> $approvedPosts
 */
?>
<div class="paned-window paned-standalone-window">
  <div class="paned-dialog-titlebar">
    <span>User: <?= $e($usernameToken) ?></span>
  </div>
  <div class="paned-standalone-body">
<?php if ($approvedProfiles === []): ?>
    <p>No approved profiles currently use this username.</p>
<?php else: ?>
    <p><strong>Approved profiles:</strong> <?= count($approvedProfiles) ?></p>
    <p><strong>Combined threads:</strong> <?= (int) $approvedThreadCount ?></p>
    <p><strong>Combined posts:</strong> <?= (int) $approvedPostCount ?></p>

    <h2>Threads</h2>
<?php if ($approvedThreads === []): ?>
    <p>No visible threads.</p>
<?php else: ?>
<?php foreach ($approvedThreads as $thread): ?>
    <p><a href="/forte?selected=<?= $e($thread['root_post_id']) ?>"><?= $e($threadTitle($thread)) ?></a> <span class="meta"><?= $forteContentMeta($thread, 'root_post_created_at', '') ?></span></p>
<?php endforeach; ?>
<?php endif; ?>

    <h2>Posts</h2>
<?php if ($approvedPosts === []): ?>
    <p>No visible posts.</p>
<?php else: ?>
<?php foreach ($approvedPosts as $post): ?>
    <p><a href="/forte?selected=<?= $e($post['thread_id']) ?>&amp;created_post_id=<?= $e($post['post_id']) ?>#post-<?= $e($post['post_id']) ?>"><?= $e($post['post_id']) ?></a> <span class="meta"><?= $forteContentMeta($post, 'created_at', '') ?></span></p>
<?php endforeach; ?>
<?php endif; ?>

    <h2>Approved Profiles</h2>
    <ul>
<?php foreach ($approvedProfiles as $profile): ?>
      <li><a href="/forte/profiles/<?= $e($profile['profile_slug']) ?>"><?= $e($profile['profile_slug']) ?></a></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($unapprovedProfiles !== []): ?>
    <h2>Unapproved Profiles</h2>
    <ul>
<?php foreach ($unapprovedProfiles as $profile): ?>
      <li><a href="/forte/profiles/<?= $e($profile['profile_slug']) ?>"><?= $e($profile['profile_slug']) ?></a></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
    <p class="paned-standalone-back"><a href="/forte">&larr; Back to board</a></p>
  </div>
</div>
