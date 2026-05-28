<section class="stack">
  <article class="card">
    <div class="nav board-controls-nav">
<?php foreach ($toolNavOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </div>
  </article>
<?php foreach ($toolPages as $toolPage): ?>
  <article class="card">
    <h2><a href="<?= $e($toolPage['href']) ?>"><?= $e($toolPage['label']) ?></a></h2>
    <p><?= $e($toolPage['description']) ?></p>
  </article>
<?php endforeach; ?>
</section>
