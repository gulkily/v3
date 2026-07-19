<section class="stack">
  <article class="card">
    <h1>Post <?= $e($post['post_id']) ?></h1>
    <p class="meta">Thread <a href="/threads/<?= $e($post['thread_id']) ?>"><?= $e($post['thread_id']) ?></a></p>
    <p class="meta">Score: <?= (int) ($post['post_score_total'] ?? 0) ?></p>
<?= $indent($partial('partials/source_metadata.php', [
    'source_path' => $post['source_path'] ?? '',
    'source_commit_sha' => $post['source_commit_sha'] ?? '',
    'source_path_href' => $post['source_path_href'] ?? '',
    'source_commit_href' => $post['source_commit_href'] ?? '',
    'source_signature_path' => $post['source_signature_path'] ?? '',
    'source_signature_href' => $post['source_signature_href'] ?? '',
    'source_signature_status' => $post['source_signature_status'] ?? '',
]), 2) ?>
    <p><a href="/compose/reply?thread_id=<?= $e($post['thread_id']) ?>&amp;parent_id=<?= $e($post['post_id']) ?>">Reply to this post</a></p>
  </article>
<?= $indent($partial('partials/post_card.php', ['post' => $post]), 1) ?>
</section>
