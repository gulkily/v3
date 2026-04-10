<section class="stack">
  <h1><?= $e($title) ?></h1>
  <p class="meta"><?= (int) $thread['reply_count'] ?> replies</p>
<?php foreach ($posts as $post): ?>
<?= $partial('partials/post_card.php', ['post' => $post]) ?>
<?php endforeach; ?>
</section>
