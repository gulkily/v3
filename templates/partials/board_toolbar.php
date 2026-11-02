<?php
// The board's filter, sort ▾ and Tags controls, shared by the list board and /tags/.
// On /tags/ ($tagsCurrent) Tags is the current page and no filter or sort option is.
$tagsCurrent = (bool) ($tagsCurrent ?? false);
$isCurrent = static fn (array $option): bool => !$tagsCurrent && $option['is_active'];
?>
<div class="board-toolbar">
  <nav class="board-filter" aria-label="Show">
<?php foreach ($viewOptions as $option): ?>
    <a class="nav-link<?= $isCurrent($option) ? ' is-active' : '' ?>" href="<?= $e($option['href']) ?>" data-nav-key="<?= $e($option['key']) ?>"<?= $isCurrent($option) ? ' aria-current="page"' : '' ?>><?php if ($option['key'] === 'liked'): ?><span class="like-mark" aria-hidden="true"></span><?php endif; ?><?= $e($option['label']) ?></a>
<?php endforeach; ?>
  </nav>
<?= $indent($partial('partials/board_sort.php', [
    'sortOptions' => array_map(static fn (array $option): array => ['is_active' => $isCurrent($option)] + $option, $sortOptions),
    'sortLabel' => $sortLabel,
]), 1) ?>
  <a class="nav-link board-tags-link<?= $tagsCurrent ? ' is-active' : '' ?>" href="/tags/"<?= $tagsCurrent ? ' aria-current="page"' : '' ?>>Tags</a>
</div>
