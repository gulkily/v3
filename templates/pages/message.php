<section class="stack">
  <article class="card">
    <h1><?= $e($heading) ?></h1>
    <p><?= $e($message) ?></p>
<?php if (($actionUrl ?? '') !== ''): ?>
    <p><a href="<?= $e($actionUrl) ?>"><?= $e($actionLabel ?? 'Continue') ?></a></p>
<?php endif; ?>
  </article>
</section>
