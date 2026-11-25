<?php
/**
 * Gallery: posts with an attached image as tiles, newest first. Tiles keep
 * their image's proportions (clamped) in justified rows, so the page needs
 * no script and does not shift as previews load. Smaller and Larger (and a pinch
 * on phones) change the tile size (gallery_zoom.js); layout.php restores it before paint.
 *
 * @var list<array<string, mixed>> $tiles
 * @var list<array<string, mixed>> $zenThreads Zen view only
 */
?>
<section class="stack gallery-page">
<?php if ($paginationError !== null): ?>
  <p role="alert"><?= $e($paginationError) ?></p>
<?php elseif (isset($zenThreads)): ?>
  <h1>Zen memes</h1>
<?php if ($zenThreads === []): ?>
  <div class="board-empty">
    <p>No Zen memes<?= $pagination['previous_href'] !== null ? ' on this page' : ' yet' ?></p>
  </div>
<?php else: ?>
  <div class="stack zen-gallery">
<?php foreach ($zenThreads as $thread): ?>
<?= $indent($partial('partials/zen_meme_card.php', ['thread' => $thread, 'featured' => false]), 2) ?>
<?php endforeach; ?>
  </div>
<?php endif; ?>
<?php elseif ($tiles === []): ?>
  <div class="board-empty">
    <h2>No images<?= $pagination['previous_href'] !== null ? ' on this page' : ' yet' ?></h2>
  </div>
<?php else: ?>
  <?= \ForumRewrite\View\ZoomButtons::group('div', 'gallery-zoom', 'data-gallery-zoom', ['smaller' => 'out', 'larger' => 'in'], 'Image size') ?>
  <div class="gallery-grid">
<?php foreach ($tiles as $tile): ?>
<?php $image = $tile['image']; $ratio = max(0.6, min(2.2, $image['width'] / $image['height'])); ?>
    <a id="post-<?= $e($tile['post_id']) ?>" class="gallery-tile" href="<?= $e($tile['href']) ?>" style="--ratio: <?= round($ratio, 3) ?>">
      <img src="<?= $e($image['preview']) ?>" alt="<?= $e($image['description']) ?>" width="<?= (int) $image['width'] ?>" height="<?= (int) $image['height'] ?>" loading="lazy" decoding="async">
<?php if ($tile['caption'] !== ''): ?>
      <span class="gallery-tile__caption"><span class="gallery-tile__title"><?= $e($tile['caption']) ?></span></span>
<?php endif; ?>
    </a>
<?php endforeach; ?>
  </div>
<?php endif; ?>
<?= $indent($partial('partials/page_navigation.php', ['pagination' => $pagination]), 1) ?>
</section>
