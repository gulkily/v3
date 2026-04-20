<section class="stack">
  <article class="card">
    <h1>Tools</h1>
    <p>Utility pages for capture, backup, and local account setup.</p>
  </article>
<?php foreach ($toolPages as $toolPage): ?>
  <article class="card">
    <h2><a href="<?= $e($toolPage['href']) ?>"><?= $e($toolPage['label']) ?></a></h2>
    <p><?= $e($toolPage['description']) ?></p>
    <p><a class="nav-link" href="<?= $e($toolPage['href']) ?>"><?= $e($toolPage['label']) ?></a></p>
  </article>
<?php endforeach; ?>
</section>
