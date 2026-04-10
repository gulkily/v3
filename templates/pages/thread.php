<section class="stack">
  <h1><?= $e($title) ?></h1>
  <p class="meta"><?= (int) $thread['reply_count'] ?> replies</p>
<?php foreach ($posts as $post): ?>
<?php
$author = $post['author_profile_slug']
    ? '<a href="/profiles/' . $e($post['author_profile_slug']) . '">' . $e($post['author_label']) . '</a>'
    : $e($post['author_label']);
?>
  <article class="card">
    <p class="meta">Post <a href="/posts/<?= $e($post['post_id']) ?>"><?= $e($post['post_id']) ?></a> by <?= $author ?></p>
    <div class="body"><?= $br($post['body']) ?></div>
  </article>
<?php endforeach; ?>
</section>
