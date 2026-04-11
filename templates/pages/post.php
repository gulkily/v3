<?php
$author = $e($post['author_label']);
if ($post['author_profile_slug']) {
    if (((int) ($post['author_is_approved'] ?? 0)) === 1 && (string) ($post['author_username_token'] ?? '') !== '') {
        $author = '<a href="/user/' . $e($post['author_username_token']) . '">' . $e($post['author_label']) . '</a>';
    } else {
        $author = '<a href="/profiles/' . $e($post['author_profile_slug']) . '">' . $e($post['author_label']) . '</a> <span class="meta">(unapproved)</span>';
    }
}
?>
<section class="stack">
  <article class="card">
    <h1>Post <?= $e($post['post_id']) ?></h1>
    <p class="meta">Thread <a href="/threads/<?= $e($post['thread_id']) ?>"><?= $e($post['thread_id']) ?></a></p>
    <p class="meta">Author <?= $author ?></p>
    <p><a href="/compose/reply?thread_id=<?= $e($post['thread_id']) ?>&amp;parent_id=<?= $e($post['post_id']) ?>">Reply to this post</a></p>
  </article>
  <article class="card">
    <div class="body"><?= $br($post['body']) ?></div>
  </article>
</section>
