<section class="stack">
  <article class="card">
    <h1><?= $e($title) ?></h1>
    <p class="meta"><a href="/threads/<?= $e($thread['root_post_id']) ?>">Back to standard thread view</a></p>
  </article>
<?= $indent($partial('partials/paned_list_pane.php', ['replyTree' => $replyTree]), 1) ?>
<?= $indent($partial('partials/paned_content_pane.php', ['posts' => $posts, 'thread' => $thread]), 1) ?>
</section>
