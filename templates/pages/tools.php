<section class="stack">
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
