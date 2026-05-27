<section class="stack">
  <article class="card">
    <h1>Codebase</h1>
    <p class="meta">Status: <?= $e($state['overall_status']) ?></p>
    <p class="meta">App version: <?= $e($state['app_version']) ?></p>
  </article>
  <article class="card">
    <h2>Repository</h2>
    <p><strong>Repository:</strong> <?= $e($state['repository']['root_label']) ?></p>
    <p><strong>Repository head:</strong> <?= $e($state['repository']['head']) ?></p>
    <p><strong>Short head:</strong> <?= $e($state['repository']['short_head']) ?></p>
    <p><strong>Git checkout:</strong> <?= $e($state['repository']['git_exists']) ?></p>
    <p><strong>Records directory:</strong> <?= $e($state['repository']['records_exists']) ?></p>
<?php if ($state['repository']['latest_commit'] !== null): ?>
    <p><strong>Latest commit:</strong> <?= $e($state['repository']['latest_commit']['short']) ?> <?= $e($state['repository']['latest_commit']['date']) ?> <?= $e($state['repository']['latest_commit']['subject']) ?></p>
<?php endif; ?>
  </article>
  <article class="card">
    <h2>Read model</h2>
    <p><strong>Database:</strong> <?= $e($state['read_model']['database_label']) ?></p>
    <p><strong>Database exists:</strong> <?= $e($state['read_model']['database_exists']) ?></p>
    <p><strong>Metadata:</strong> <?= $e($state['read_model']['metadata_status']) ?></p>
    <p><strong>Schema version:</strong> <?= $e($state['read_model']['schema_version']) ?> / expected <?= $e($state['read_model']['expected_schema_version']) ?></p>
    <p><strong>Repository head:</strong> <?= $e($state['read_model']['repository_head']) ?></p>
    <p><strong>Current repository head:</strong> <?= $e($state['read_model']['current_repository_head']) ?></p>
    <p><strong>Rebuilt at:</strong> <?= $e($state['read_model']['rebuilt_at']) ?></p>
    <p><strong>Rebuild reason:</strong> <?= $e($state['read_model']['rebuild_reason']) ?></p>
    <p><strong>Lock status:</strong> <?= $e($state['read_model']['lock_status']) ?></p>
    <p><strong>Stale marker:</strong> <?= $e($state['read_model']['stale_marker']) ?></p>
    <p><strong>Stale reason:</strong> <?= $e($state['read_model']['stale_reason']) ?></p>
    <p><strong>Stale commit:</strong> <?= $e($state['read_model']['stale_commit_sha']) ?></p>
  </article>
  <article class="card">
    <h2>Downloads</h2>
    <ul>
<?php foreach ($state['downloads'] as $download): ?>
      <li><a href="<?= $e($download['href']) ?>"><?= $e($download['label']) ?></a></li>
<?php endforeach; ?>
    </ul>
  </article>
</section>
