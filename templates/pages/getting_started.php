<section class="stack">
  <article class="card stack">
    <h1>Getting started</h1>
<?= $indent($partial('partials/getting_started_links.php', ['part' => 'browse']), 2) ?>
  </article>
  <article class="card stack">
    <h2>Import public content</h2>
    <p>Bring in threads from another instance. Local posts and settings stay; conflicts keep the local version.</p>
    <ul class="stack">
<?php foreach ($knownInstances as $instance): ?>
      <li><strong><?= $e($instance['title']) ?></strong> <span class="meta"><?= $e($instance['url']) ?></span><br><code>./v3 import-instance <?= $e($instance['name']) ?> --dry-run</code></li>
<?php endforeach; ?>
      <li><strong>Another instance</strong><br><code>./v3 import-instance forum.example --dry-run</code></li>
    </ul>
    <p class="meta">Run it again without <code>--dry-run</code> to import.<?php if ($importPageEnabled): ?> Operators can also use <a href="/tools/import/">Tools → Import</a>.<?php endif; ?></p>
  </article>
  <article class="card stack">
    <h2>Try the forum</h2>
<?= $indent($partial('partials/getting_started_links.php', ['part' => 'try']), 2) ?>
  </article>
</section>
