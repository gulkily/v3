<?php
/**
 * Gallery: posts with an attached image as tiles, newest first. Tiles keep
 * their image's proportions (clamped) in justified rows, so the page needs
 * no script and does not shift as previews load.
 *
 * @var list<array<string, mixed>> $tiles
 */
?>
<section class="stack">
<?php if ($paginationError !== null): ?>
  <p role="alert"><?= $e($paginationError) ?></p>
<?php elseif ($tiles === []): ?>
  <div class="board-empty">
    <h2>No images<?= $pagination['previous_href'] !== null ? ' on this page' : ' yet' ?></h2>
  </div>
<?php else: ?>
  <div class="gallery-grid">
<?php foreach ($tiles as $tile): ?>
<?php $image = $tile['image']; $ratio = max(0.6, min(2.2, $image['width'] / $image['height'])); $replies = (int) $tile['reply_count']; ?>
    <a class="gallery-tile" href="<?= $e($tile['href']) ?>" style="--ratio: <?= round($ratio, 3) ?>">
      <img src="<?= $e($image['preview']) ?>" alt="<?= $e($image['description']) ?>" width="<?= (int) $image['width'] ?>" height="<?= (int) $image['height'] ?>" loading="lazy" decoding="async">
      <span class="gallery-tile__caption"><span class="gallery-tile__title"><?= $e($tile['context_title']) ?></span><span class="gallery-tile__meta"><?= $replies ?> <?= $replies === 1 ? 'reply' : 'replies' ?><?= $tile['is_reply'] ? ' · Reply' : '' ?></span></span>
    </a>
<?php endforeach; ?>
  </div>
<?php endif; ?>
<?= $indent($partial('partials/page_navigation.php', ['pagination' => $pagination]), 1) ?>
</section>
