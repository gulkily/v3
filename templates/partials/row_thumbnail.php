<?php
/**
 * The attached image of a board or Recent row, as tall as the row. It repeats
 * the title link, so it stays out of the tab order and the accessibility tree.
 *
 * @var array{src:string, srcset:?string, width:int, height:int} $thumbnail
 * @var string $href
 */
?>
<a class="thread-row__thumb" href="<?= $e($href) ?>" tabindex="-1" aria-hidden="true"><img src="<?= $e($thumbnail['src']) ?>"<?php if (($thumbnail['srcset'] ?? null) !== null): ?> srcset="<?= $e($thumbnail['srcset']) ?>"<?php endif; ?> alt="" width="<?= (int) $thumbnail['width'] ?>" height="<?= (int) $thumbnail['height'] ?>" loading="lazy" decoding="async"></a>
