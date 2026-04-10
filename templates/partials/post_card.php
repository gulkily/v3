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
<article class="card">
  <p class="meta">Post <a href="/posts/<?= $e($post['post_id']) ?>"><?= $e($post['post_id']) ?></a> by <?= $author ?></p>
  <div class="body"><?= $br($post['body']) ?></div>
  <p><a href="/compose/reply?thread_id=<?= $e($post['thread_id']) ?>&amp;parent_id=<?= $e($post['post_id']) ?>">Reply</a></p>
</article>
