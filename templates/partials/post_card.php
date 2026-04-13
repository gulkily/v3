<article class="card">
  <p class="meta">Post <a href="/posts/<?= $e($post['post_id']) ?>"><?= $e($post['post_id']) ?></a></p>
  <p class="meta"><?= $contentMeta($post, 'created_at', '') ?></p>
  <div class="body"><?= $br($post['body']) ?></div>
  <p><a href="/compose/reply?thread_id=<?= $e($post['thread_id']) ?>&amp;parent_id=<?= $e($post['post_id']) ?>">Reply</a></p>
</article>
