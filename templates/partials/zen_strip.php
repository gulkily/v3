<?php
/**
 * The Zen strip: a grid of Zen memes at the top of the board's first page, as tall
 * as its cards need, then a link to their tag page. An image card shows the
 * thread's first image whole, large enough to read, described by its alt text
 * (or the title when it has none); a text card shows the title.
 *
 * @var list<array{root_post_id:string, subject:string, thumbnail?:array{preview:string, full:string, width:int, height:int, description:string}}> $threads
 * @var string $allHref
 */
?>
<section class="zen-strip" aria-label="Zen memes">
  <ul class="zen-strip__grid">
<?php foreach ($threads as $thread): ?>
<?php $href = '/threads/' . rawurlencode($thread['root_post_id']); ?>
<?php if (isset($thread['thumbnail'])): ?>
<?php $image = $thread['thumbnail']; ?>
<?php $alt = trim($image['description']) !== '' ? $image['description'] : $thread['subject']; ?>
<?php $srcset = $image['full'] === $image['preview'] ? null : $image['preview'] . ' 480w, ' . $image['full'] . ' ' . max(481, (int) $image['width']) . 'w'; ?>
    <li class="zen-strip__item"><a class="zen-strip__card zen-strip__card--image" href="<?= $e($href) ?>"><img src="<?= $e($image['preview']) ?>"<?php if ($srcset !== null): ?> srcset="<?= $e($srcset) ?>" sizes="(min-width: 48rem) 22rem, 100vw"<?php endif; ?> alt="<?= $e($alt) ?>" width="<?= (int) $image['width'] ?>" height="<?= (int) $image['height'] ?>" loading="lazy" decoding="async"></a></li>
<?php else: ?>
    <li class="zen-strip__item"><a class="zen-strip__card zen-strip__card--text" href="<?= $e($href) ?>"><span><?= $e($thread['subject']) ?></span></a></li>
<?php endif; ?>
<?php endforeach; ?>
  </ul>
  <p class="zen-strip__more"><a href="<?= $e($allHref) ?>">All</a></p>
</section>
