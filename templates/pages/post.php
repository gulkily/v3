<?php
$author = $post['author_profile_slug']
    ? '<a href="/profiles/' . $e($post['author_profile_slug']) . '">' . $e($post['author_label']) . '</a>'
    : $e($post['author_label']);
?>
<section class="stack">
  <h1>Post <?= $e($post['post_id']) ?></h1>
  <p class="meta">Thread <a href="/threads/<?= $e($post['thread_id']) ?>"><?= $e($post['thread_id']) ?></a></p>
  <p class="meta">Author <?= $author ?></p>
  <article class="card">
    <div class="body"><?= $br($post['body']) ?></div>
  </article>
</section>
