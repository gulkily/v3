<?php
/**
 * The Zen memes at the top of the home page: the featured meme (the first
 * thread, top-scored), then one row of tiny thumbnails of the others. The
 * featured meme shows its first image whole (or its title), linking to its
 * thread: a compressed copy sized for the screen, never the stored original,
 * fetched first, over a tiny blurred stand-in while it loads. The row links
 * to the collection. With one Zen meme there is no row.
 *
 * @var list<array{root_post_id:string, subject:string, body_preview:string, thumbnail?:array{src:string, srcset:?string, display:list<array{url:string, width:int}>, display_srcset:?string, placeholder?:?string, width:int, height:int, description:string}}> $threads
 * @var string $allHref
 */
$featured = $threads[0];
$others = array_slice($threads, 1);
?>
<section class="zen-strip" aria-label="Zen memes">
<?= $indent($partial('partials/zen_meme_card.php', ['thread' => $featured, 'featured' => true]), 1) ?>
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
