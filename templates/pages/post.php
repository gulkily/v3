<section class="stack">
  <article class="card">
    <h1>Post <?= $e($post['post_id']) ?></h1>
    <p class="meta">Thread <a href="/threads/<?= $e($post['thread_id']) ?>"><?= $e($post['thread_id']) ?></a></p>
    <p class="meta"><?= $contentMeta($post) ?></p>
    <p><a href="/compose/reply?thread_id=<?= $e($post['thread_id']) ?>&amp;parent_id=<?= $e($post['post_id']) ?>">Reply to this post</a></p>
  </article>
  <article class="card">
    <div class="body"><?= $br($post['body']) ?></div>
  </article>
</section>
