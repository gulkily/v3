<?php
// The board's "Sort by ▾" menu: a <details> whose summary shows the current sort and whose
// menu lists one link per option. Shared by the board toolbar and the Users directory.
// account_menu.js closes an open menu when the viewer clicks elsewhere.
?>
<details class="board-sort" data-role="board-sort">
  <summary class="nav-link"><span class="visually-hidden">Sort by </span><?= $e($sortLabel) ?></summary>
  <div class="board-sort__menu">
<?php foreach ($sortOptions as $option): ?>
    <a class="nav-link<?= $option['is_active'] ? ' is-active' : '' ?>" href="<?= $e($option['href']) ?>" data-nav-key="<?= $e($option['key']) ?>"<?= $option['is_active'] ? ' aria-current="true"' : '' ?>><?= $e($option['label']) ?></a>
<?php endforeach; ?>
  </div>
</details>
