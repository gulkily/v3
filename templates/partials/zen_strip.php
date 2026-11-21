<?php
/**
 * The Zen memes at the top of the home page: the featured meme (the first
 * thread, top-scored), then one row of tiny thumbnails of the others. The
 * featured meme shows its first image whole (or its title), linking to its
 * thread. The row is a single link to the memes' tag page; there is no
 * separate All link. With one Zen meme there is no row.
 *
 * @var list<array{root_post_id:string, subject:string, thumbnail?:array{src:string, srcset:?string, preview:string, full:string, width:int, height:int, description:string}}> $threads
 * @var string $allHref
 */
$featured = $threads[0];
$others = array_slice($threads, 1);
$href = '/threads/' . rawurlencode($featured['root_post_id']);
?>
<section class="zen-strip" aria-label="Zen memes">
<?php if (isset($featured['thumbnail'])): ?>
<?php $image = $featured['thumbnail']; ?>
<?php $alt = trim($image['description']) !== '' ? $image['description'] : $featured['subject']; ?>
<?php $srcset = $image['full'] === $image['preview'] ? null : $image['preview'] . ' 480w, ' . $image['full'] . ' ' . max(481, (int) $image['width']) . 'w'; ?>
  <a class="zen-strip__card zen-strip__card--image" href="<?= $e($href) ?>"><img src="<?= $e($image['preview']) ?>"<?php if ($srcset !== null): ?> srcset="<?= $e($srcset) ?>" sizes="(min-width: 48rem) 24rem, 100vw"<?php endif; ?> alt="<?= $e($alt) ?>" width="<?= (int) $image['width'] ?>" height="<?= (int) $image['height'] ?>" decoding="async"></a>
<?php else: ?>
  <a class="zen-strip__card zen-strip__card--text" href="<?= $e($href) ?>"><span><?= $e($featured['subject']) ?></span></a>
<?php endif; ?>
<?php if ($others !== []): ?>
  <a class="zen-strip__row" href="<?= $e($allHref) ?>" aria-label="All Zen memes">
<?php foreach ($others as $thread): ?>
<?php if (isset($thread['thumbnail'])): ?>
    <img class="zen-strip__thumb" src="<?= $e($thread['thumbnail']['src']) ?>"<?php if (($thread['thumbnail']['srcset'] ?? null) !== null): ?> srcset="<?= $e($thread['thumbnail']['srcset']) ?>"<?php endif; ?> alt="" width="48" height="48" loading="lazy" decoding="async">
<?php else: ?>
    <span class="zen-strip__thumb zen-strip__thumb--text" aria-hidden="true"><?= $e($thread['subject']) ?></span>
<?php endif; ?>
<?php endforeach; ?>
  </a>
<?php endif; ?>
</section>
