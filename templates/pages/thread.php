<section class="stack"<?= $createdPostId !== '' ? ' data-created-post-id="' . $e($createdPostId) . '"' : '' ?>>
<?php
$rootPost = null;
$replyPosts = [];
foreach ($posts as $post) {
    if ((string) $post['post_id'] === (string) $thread['root_post_id']) {
        $rootPost = $post;
        continue;
    }

    $replyPosts[] = $post;
}
?>
<?php if ($rootPost !== null): ?>
<?= $indent($partial('partials/thread_root_card.php', ['post' => $rootPost]), 1) ?>
<?php else: ?>
  <article class="card" data-thread-reactions-root data-thread-id="<?= $e($thread['root_post_id']) ?>">
    <h1><?= $e($title) ?></h1>
    <p class="meta"><?= $contentMeta($thread, 'root_post_created_at', '') ?></p>
  </article>
<?php endif; ?>
<?php foreach ($replyPosts as $post): ?>
<?= $indent($partial('partials/post_card.php', ['post' => $post]), 1) ?>
<?php endforeach; ?>
  <article class="card inline-reply-composer" data-compose-root>
    <details class="inline-reply-details" data-inline-reply-details>
      <summary class="inline-reply-summary">
        <textarea
          class="inline-reply-prompt"
          rows="2"
          placeholder="Write a reply..."
          aria-label="Write a reply"
          data-inline-reply-trigger
          readonly
        ></textarea>
      </summary>
      <div class="inline-reply-expanded stack">
        <p class="meta inline-reply-identity-status" data-role="compose-identity-status">Ready.</p>
<?= $indent($partial('partials/reply_form.php', [
    'threadId' => $thread['root_post_id'],
    'parentId' => $thread['root_post_id'],
    'boardTags' => 'general',
    'body' => '',
    'submitLabel' => 'Post reply',
    'showBodyLabel' => false,
]), 2) ?>
      </div>
    </details>
  </article>
</section>
