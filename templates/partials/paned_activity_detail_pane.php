<?php
/**
 * @var array<int, array<string, mixed>> $items
 * @var string $selectedItemId
 */
$selectedItemId ??= '';
$hasSelectedItem = false;
foreach ($items as $item) {
    if ((string) $item['id'] === $selectedItemId) {
        $hasSelectedItem = true;
        break;
    }
}

// Deduped by commit sha: many items share the same handful of bootstrap/
// seed commits, and each one's manifest can run to thousands of files, so
// it's rendered once here rather than once per item (see
// paned_activity_detail_article.php for how a selected item's article
// finds its matching block).
$commitManifestsBySha = [];
foreach ($items as $item) {
    $files = $item['source_commit_files'] ?? [];
    $sha = (string) ($item['source_commit_sha'] ?? '');
    if ($files !== [] && $sha !== '' && !isset($commitManifestsBySha[$sha])) {
        $commitManifestsBySha[$sha] = [
            'files' => $files,
            'commit_href' => $item['source_commit_href'] ?? '',
        ];
    }
}
?>
<div class="paned-content-pane" data-paned-activity-content-pane>
  <article class="paned-content-post" data-paned-activity-content-placeholder<?= $hasSelectedItem ? ' hidden' : '' ?>>
    <div class="paned-content-head">
      <div class="paned-content-subject">No activity item selected</div>
    </div>
    <div class="body">Select an item from the list to see its detail here.</div>
  </article>
<?php foreach ($items as $item): ?>
<?php
$itemId = (string) $item['id'];
$isSelected = $selectedItemId !== '' && $itemId === $selectedItemId;
?>
<?= $indent($partial('partials/paned_activity_detail_article.php', [
    'item' => $item,
    'isSelected' => $isSelected,
]), 1) ?>
<?php endforeach; ?>
<?php foreach ($commitManifestsBySha as $sha => $manifest): ?>
  <div data-paned-activity-commit-manifest="<?= $e($sha) ?>" hidden>
<?= $indent($partial('partials/activity_commit_manifest.php', [
      'files' => $manifest['files'],
      'commit_sha' => $sha,
      'commit_href' => $manifest['commit_href'],
    ]), 2) ?>
  </div>
<?php endforeach; ?>
</div>
