<section class="stack">
  <article class="card">
    <h1>User <?= $e($usernameToken) ?></h1>
  </article>

<?php if ($approvedProfiles === []): ?>
  <article class="card">
    <p>No approved profiles currently use this username.</p>
  </article>
<?php else: ?>
  <article class="card">
    <p><strong>Approved profiles:</strong> <?= count($approvedProfiles) ?></p>
    <p><strong>Combined threads:</strong> <?= (int) $approvedThreadCount ?></p>
    <p><strong>Combined posts:</strong> <?= (int) $approvedPostCount ?></p>
  </article>

  <article class="card">
    <h2>Threads</h2>
<?php if ($approvedThreads === []): ?>
    <p>No visible threads.</p>
<?php else: ?>
    <ul>
<?php foreach ($approvedThreads as $thread): ?>
<?php $subject = $thread['subject'] ?: $thread['root_post_id']; ?>
      <li><a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($subject) ?></a></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </article>

  <article class="card">
    <h2>Posts</h2>
<?php if ($approvedPosts === []): ?>
    <p>No visible posts.</p>
<?php else: ?>
    <ul>
<?php foreach ($approvedPosts as $post): ?>
      <li><a href="/posts/<?= $e($post['post_id']) ?>"><?= $e($post['post_id']) ?></a></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </article>

  <article class="card">
    <h2>Approved Profiles</h2>
    <ul>
<?php foreach ($approvedProfiles as $profile): ?>
      <li><a href="/profiles/<?= $e($profile['profile_slug']) ?>"><?= $e($profile['profile_slug']) ?></a></li>
<?php endforeach; ?>
    </ul>
  </article>
<?php endif; ?>

<?php if ($unapprovedProfiles !== []): ?>
  <article class="card">
    <h2>Unapproved Profiles</h2>
    <ul>
<?php foreach ($unapprovedProfiles as $profile): ?>
      <li><a href="/profiles/<?= $e($profile['profile_slug']) ?>"><?= $e($profile['profile_slug']) ?></a></li>
<?php endforeach; ?>
    </ul>
  </article>
<?php endif; ?>
</section>
