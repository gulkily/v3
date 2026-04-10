<?php
$author = $post['author_profile_slug']
    ? '<a href="/profiles/' . $e($post['author_profile_slug']) . '">' . $e($post['author_label']) . '</a>'
    : $e($post['author_label']);
?>
<article class="card">
  <p class="meta">Post <a href="/posts/<?= $e($post['post_id']) ?>"><?= $e($post['post_id']) ?></a> by <?= $author ?></p>
  <div class="body"><?= $br($post['body']) ?></div>
</article>
