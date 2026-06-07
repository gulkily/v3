<?php
$repositoryRows = [
    ['label' => 'Repository', 'value' => $state['repository']['root_label']],
    ['label' => 'Repository head', 'value' => $state['repository']['head'], 'source' => 'git -C REPOSITORY rev-parse HEAD'],
    ['label' => 'Short head', 'value' => $state['repository']['short_head'], 'source' => 'git -C REPOSITORY rev-parse --short HEAD'],
    ['label' => 'Git checkout', 'value' => $state['repository']['git_exists'], 'source' => 'test -d REPOSITORY/.git'],
    ['label' => 'Records directory', 'value' => $state['repository']['records_exists'], 'source' => 'test -d REPOSITORY/records'],
];
if ($state['repository']['latest_commit'] !== null) {
    $repositoryRows[] = [
        'label' => 'Latest commit',
        'value' => $state['repository']['latest_commit']['short'] . ' ' . $state['repository']['latest_commit']['date'] . ' ' . $state['repository']['latest_commit']['subject'],
        'source' => 'git -C REPOSITORY log -1 --format=%h%x09%cI%x09%s',
    ];
}

$readModelRows = [
    ['label' => 'Database', 'value' => $state['read_model']['database_label']],
    ['label' => 'Database exists', 'value' => $state['read_model']['database_exists'], 'source' => 'test -f DATABASE'],
    ['label' => 'Metadata', 'value' => $state['read_model']['metadata_status'], 'source' => 'SELECT key, value FROM metadata'],
    ['label' => 'Schema version', 'value' => $state['read_model']['schema_version'] . ' / expected ' . $state['read_model']['expected_schema_version'], 'source' => "SELECT value FROM metadata WHERE key = 'schema_version'"],
    ['label' => 'Repository head', 'value' => $state['read_model']['repository_head'], 'source' => "SELECT value FROM metadata WHERE key = 'repository_head'"],
    ['label' => 'Current repository head', 'value' => $state['read_model']['current_repository_head'], 'source' => 'git -C REPOSITORY rev-parse HEAD'],
    ['label' => 'Rebuilt at', 'value' => $state['read_model']['rebuilt_at'], 'source' => "SELECT value FROM metadata WHERE key = 'rebuilt_at'"],
    ['label' => 'Rebuild reason', 'value' => $state['read_model']['rebuild_reason'], 'source' => "SELECT value FROM metadata WHERE key = 'rebuild_reason'"],
    ['label' => 'Lock status', 'value' => $state['read_model']['lock_status'], 'source' => 'flock DATABASE_DIR/forum-rewrite.lock'],
    ['label' => 'Stale marker', 'value' => $state['read_model']['stale_marker'], 'source' => 'test -f DATABASE_DIR/read_model_stale.json'],
    ['label' => 'Stale reason', 'value' => $state['read_model']['stale_reason'], 'source' => 'read DATABASE_DIR/read_model_stale.json reason'],
    ['label' => 'Stale commit', 'value' => $state['read_model']['stale_commit_sha'], 'source' => 'read DATABASE_DIR/read_model_stale.json commit_sha'],
];

$rowCountSources = [
    'Posts' => 'SELECT COUNT(*) FROM posts',
    'Threads' => 'SELECT COUNT(*) FROM threads',
    'Profiles' => 'SELECT COUNT(*) FROM profiles',
    'Username routes' => 'SELECT COUNT(*) FROM username_routes',
    'Activity rows' => 'SELECT COUNT(*) FROM activity',
    'Post analyses' => 'SELECT COUNT(*) FROM post_analyses',
    'Unicode risk rows' => 'SELECT COUNT(*) FROM post_unicode_risks',
    'Generated responses' => 'SELECT COUNT(*) FROM post_generated_responses',
    'Approved profiles' => 'SELECT COUNT(*) FROM profiles WHERE is_approved = 1',
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
    <p class="meta codebase-source"><code>git -C REPOSITORY rev-parse HEAD</code></p>
  </article>
  <article class="card" data-role="codebase-state">
    <h2>Repository</h2>
    <table class="codebase-facts">
      <tbody>
<?php foreach ($repositoryRows as $row): ?>
        <tr>
          <th scope="row"><?= $e($row['label']) ?></th>
          <td>
            <code><?= $e($row['value']) ?></code>
<?php if (isset($row['source'])): ?>
            <div class="codebase-source"><code><?= $e($row['source']) ?></code></div>
<?php endif; ?>
          </td>
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
          <td>
            <code><?= $e($row['value']) ?></code>
<?php if (isset($row['source'])): ?>
            <div class="codebase-source"><code><?= $e($row['source']) ?></code></div>
<?php endif; ?>
          </td>
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
          <td>
            <code><?= $e($value) ?></code>
<?php if (isset($rowCountSources[$label])): ?>
            <div class="codebase-source"><code><?= $e($rowCountSources[$label]) ?></code></div>
<?php endif; ?>
          </td>
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
