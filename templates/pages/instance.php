<section class="stack">
  <h1>Instance</h1>
  <article class="card">
    <p><strong>Name:</strong> <?= $e($instance['instance_name']) ?></p>
    <p><strong>Admin:</strong> <?= $e($instance['admin_name']) ?></p>
    <p><strong>Contact:</strong> <?= $e($instance['admin_contact']) ?></p>
    <p><strong>Installed:</strong> <?= $e($instance['install_date']) ?></p>
    <p><strong>Retention:</strong> <?= $e($instance['retention_policy']) ?></p>
  </article>
  <article class="card">
    <div class="body"><?= $br($instance['body']) ?></div>
  </article>
</section>
