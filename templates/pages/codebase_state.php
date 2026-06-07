<?php
$repositoryRows = [
    ['label' => 'Repository', 'value' => $state['repository']['root_label']],
    ['label' => 'Repository head', 'value' => $state['repository']['head']],
    ['label' => 'Short head', 'value' => $state['repository']['short_head']],
    ['label' => 'Git checkout', 'value' => $state['repository']['git_exists']],
    ['label' => 'Records directory', 'value' => $state['repository']['records_exists']],
];
if ($state['repository']['latest_commit'] !== null) {
    $repositoryRows[] = [
        'label' => 'Latest commit',
        'value' => $state['repository']['latest_commit']['short'] . ' ' . $state['repository']['latest_commit']['date'] . ' ' . $state['repository']['latest_commit']['subject'],
    ];
}

$readModelRows = [
    ['label' => 'Database', 'value' => $state['read_model']['database_label']],
    ['label' => 'Database exists', 'value' => $state['read_model']['database_exists']],
    ['label' => 'Metadata', 'value' => $state['read_model']['metadata_status']],
    ['label' => 'Schema version', 'value' => $state['read_model']['schema_version'] . ' / expected ' . $state['read_model']['expected_schema_version']],
    ['label' => 'Repository head', 'value' => $state['read_model']['repository_head']],
    ['label' => 'Current repository head', 'value' => $state['read_model']['current_repository_head']],
    ['label' => 'Rebuilt at', 'value' => $state['read_model']['rebuilt_at']],
    ['label' => 'Rebuild reason', 'value' => $state['read_model']['rebuild_reason']],
    ['label' => 'Lock status', 'value' => $state['read_model']['lock_status']],
    ['label' => 'Stale marker', 'value' => $state['read_model']['stale_marker']],
    ['label' => 'Stale reason', 'value' => $state['read_model']['stale_reason']],
    ['label' => 'Stale commit', 'value' => $state['read_model']['stale_commit_sha']],
];
?>
<section class="stack">
  <article class="card">
    <div class="nav board-controls-nav">
<?php foreach ($toolNavOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </div>
  </article>
  <article class="card codebase-status-card" data-role="codebase-state">
    <h1>System State</h1>
    <p class="codebase-status" data-status="<?= $e($state['overall_status']) ?>"><?= $e($state['overall_status']) ?></p>
    <p class="meta">App version <code><?= $e($state['app_version']) ?></code></p>
  </article>
  <article class="card" data-role="codebase-state">
    <h2>Repository</h2>
    <table class="codebase-facts">
      <tbody>
<?php foreach ($repositoryRows as $row): ?>
        <tr>
          <th scope="row"><?= $e($row['label']) ?></th>
          <td><code><?= $e($row['value']) ?></code></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </article>
  <article class="card" data-role="codebase-state">
    <h2>Read model</h2>
    <table class="codebase-facts">
      <tbody>
<?php foreach ($readModelRows as $row): ?>
        <tr>
          <th scope="row"><?= $e($row['label']) ?></th>
          <td><code><?= $e($row['value']) ?></code></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </article>
  <article class="card" data-role="codebase-state">
    <h2>Read-model rows</h2>
    <table class="codebase-facts">
      <tbody>
<?php foreach ($state['read_model']['row_counts'] as $label => $value): ?>
        <tr>
          <th scope="row"><?= $e($label) ?></th>
          <td><code><?= $e($value) ?></code></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </article>
  <article class="card" data-role="codebase-state">
    <h2>Downloads</h2>
    <ul class="codebase-downloads">
<?php foreach ($state['downloads'] as $download): ?>
      <li><a href="<?= $e($download['href']) ?>"><?= $e($download['label']) ?></a></li>
<?php endforeach; ?>
    </ul>
  </article>
</section>
