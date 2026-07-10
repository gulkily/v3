<section class="stack">
  <article class="card">
    <div class="nav board-controls-nav">
<?php foreach ($toolNavOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </div>
  </article>
  <article class="card tool-launcher">
    <h2>Tools</h2>
    <ul class="tool-launcher-list">
<?php foreach ($toolPages as $toolPage): ?>
      <li class="tool-launcher-item">
        <a class="tool-launcher-button" href="<?= $e($toolPage['href']) ?>"><?= $e($toolPage['label']) ?></a>
        <p class="tool-launcher-description"><?= $e($toolPage['description']) ?></p>
      </li>
<?php endforeach; ?>
    </ul>
  </article>
</section>
