<section class="stack">
  <article class="card">
    <div class="nav board-controls-nav">
      <a class="nav-link" href="/tags/">Tags</a>
<?php foreach ($viewOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
<?php foreach ($sortOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
      <a class="nav-link" href="/compose/thread">New Post</a>
    </div>
  </article>
  <article class="card compact-thread-compose" data-compose-root data-unicode-authored-text="<?= $unicodeAuthoredTextEnabled ? '1' : '0' ?>">
    <p class="meta" data-role="compose-identity-status" hidden></p>
<?= $indent($partial('partials/thread_compose_form.php', [
    'boardTags' => 'general',
    'subject' => '',
    'body' => '',
    'notice' => null,
    'error' => null,
    'compact' => true,
]), 2) ?>
  </article>
<?php foreach ($threads as $thread): ?>
<?php $subject = $thread['subject'] ?: $thread['root_post_id']; ?>
<?php $isPinned = in_array('pinned', $thread['thread_labels'] ?? [], true); ?>
  <article class="card">
    <h2><a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($subject) ?></a><?php if ($isPinned): ?> <span class="pinned-thread-marker">Pinned</span><?php endif; ?></h2>
    <p class="meta"><?= $contentMeta($thread, 'root_post_created_at', '') ?></p>
    <p><?= $br($thread['body_preview']) ?></p>
<?php if ((int) $thread['reply_count'] > 0): ?>
    <p class="meta"><?= (int) $thread['reply_count'] ?> <?= (int) $thread['reply_count'] === 1 ? 'reply' : 'replies' ?></p>
<?php endif; ?>
  </article>
<?php endforeach; ?>
</section>
