<?php
/**
 * The Zen strip: a row of Zen memes at the top of the board's first page, then a
 * link to their tag page. An image card shows the thread's first image, described
 * by its alt text (or the title when it has none); a text card shows the title.
 *
 * @var list<array{root_post_id:string, subject:string, thumbnail?:array{preview:string, width:int, height:int, description:string}}> $threads
 * @var string $allHref
 */
?>
<section class="zen-strip" aria-label="Zen memes">
  <ul class="zen-strip__row">
<?php foreach ($threads as $thread): ?>
<?php $href = '/threads/' . rawurlencode($thread['root_post_id']); ?>
<?php if (isset($thread['thumbnail'])): ?>
<?php $alt = trim($thread['thumbnail']['description']) !== '' ? $thread['thumbnail']['description'] : $thread['subject']; ?>
    <li class="zen-strip__item"><a class="zen-strip__card zen-strip__card--image" href="<?= $e($href) ?>"><img src="<?= $e($thread['thumbnail']['preview']) ?>" alt="<?= $e($alt) ?>" width="<?= (int) $thread['thumbnail']['width'] ?>" height="<?= (int) $thread['thumbnail']['height'] ?>" loading="lazy" decoding="async"></a></li>
<?php else: ?>
    <li class="zen-strip__item"><a class="zen-strip__card zen-strip__card--text" href="<?= $e($href) ?>"><span><?= $e($thread['subject']) ?></span></a></li>
<?php endif; ?>
<?php endforeach; ?>
    <li class="zen-strip__item zen-strip__item--all"><a class="zen-strip__all" href="<?= $e($allHref) ?>">All</a></li>
  </ul>
</section>
