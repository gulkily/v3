<?php
/** @var array<string, mixed> $thread */
$href = '/threads/' . rawurlencode($thread['root_post_id']);
$title = \ForumRewrite\Support\ThreadTitle::displayTitle($thread['subject'], $thread['body_preview'], $thread['root_post_id']);
?>
<?php if (isset($thread['thumbnail'])): ?>
<?php $image = $thread['thumbnail']; ?>
<?php $alt = trim($image['description']) !== '' ? $image['description'] : $title; ?>
<?php $sources = $image['display']; ?>
<?php $placeholder = $image['placeholder'] ?? null; ?>
<a class="zen-strip__card zen-strip__card--image<?= $placeholder === null ? '' : ' zen-strip__card--standin' ?>" href="<?= $e($href) ?>"><img<?php if ($placeholder !== null): ?> style="background-image:url(<?= $e($placeholder) ?>)"<?php endif; ?> src="<?= $e($sources[min(1, count($sources) - 1)]['url']) ?>"<?php if ($image['display_srcset'] !== null): ?> srcset="<?= $e($image['display_srcset']) ?>" sizes="(min-width: 48rem) 45rem, 100vw"<?php endif; ?> alt="<?= $e($alt) ?>" width="<?= (int) $image['width'] ?>" height="<?= (int) $image['height'] ?>" <?= $featured ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async"></a>
<?php else: ?>
<a class="zen-strip__card zen-strip__card--text" href="<?= $e($href) ?>"><span><?= $e($title) ?></span></a>
<?php endif; ?>
