<section class="stack">
  <h1>Instance</h1>
  <article class="card">
    <p><strong>Name:</strong> <?= $e($siteName) ?></p>
    <p><strong>Admin:</strong>
<?php if ($admins === []): ?>
      none
<?php else: ?>
<?php foreach ($admins as $index => $admin): ?>
<?php if ($index > 0): ?>, <?php endif; ?><a href="/user/<?= $e($admin['username_token']) ?>"><?= $e($admin['username']) ?></a>
<?php endforeach; ?>
<?php endif; ?>
    </p>
  </article>
  <article class="card">
    <h2>Downloads</h2>
    <ul>
<?php foreach ($downloads as $download): ?>
      <li><a href="<?= $e($download['href']) ?>"><?= $e($download['label']) ?></a> - <?= $e($download['description']) ?></li>
<?php endforeach; ?>
    </ul>
    <h3>Why this matters</h3>
    <p>These downloads are complete snapshots of the forum data, not partial exports. They preserve the full board as it exists at that moment, which makes them an insurance policy of sorts: if the board is changed in ways the community did not choose or consent to, the data needed to restore, migrate, or independently preserve it still exists.</p>
    <h3>Explain it like I'm five</h3>
    <p>Think of these downloads like a backup copy of the whole forum. They do not just save a few pieces, they save everything needed to keep the board alive somewhere else. If someone changes the board in a way the community did not agree to, this gives people a way to put it back, move it, or preserve it.</p>
    <h3>For technical users</h3>
    <p>The downloadable artifacts are sufficient to reconstruct the board in a self-hosted or independently archived form. The repository archive preserves the canonical content and its history, while the SQLite read-model provides a ready-made local index for query and inspection. Together, they make the forum portable, auditable, and resilient against unilateral platform or administrator actions.</p>
    <p>In other words, these files reduce trust requirements. Users do not have to rely on a live server remaining benevolent, stable, or even available in order to retain access to the board's full state. If governance fails or the service is modified without community consent, the data needed to verify, migrate, and continue the forum remains available outside that control surface.</p>
  </article>
</section>
