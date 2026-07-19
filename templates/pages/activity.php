<section class="stack">
  <article class="card">
    <h1>Activity</h1>
    <p class="meta">View: <?= $e($view) ?></p>
    <div class="nav">
<?php foreach ($viewOptions as $option): ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </div>
  </article>
<?php foreach ($items as $item): ?>
<?php if ($item['kind'] === 'site_feature_flag'): ?>
<?php $href = '/tools/feature-flags/'; ?>
<?php $linkLabel = 'site feature flags'; ?>
<?php elseif ($item['kind'] === 'thread_label_add'): ?>
<?php $href = '/threads/' . $item['thread_id']; ?>
<?php $linkLabel = $item['thread_id']; ?>
<?php else: ?>
<?php $href = '/posts/' . $item['post_id']; ?>
<?php $linkLabel = $item['post_id']; ?>
<?php endif; ?>
  <article class="card" data-heat="<?= $heat($item['created_at'] ?? null) ?>">
    <p class="meta"><?= $e($item['kind']) ?></p>
    <p><a href="<?= $e($href) ?>"><?= $e($linkLabel) ?></a></p>
    <p><?= $e($item['label']) ?></p>
<?php if ((string) ($item['author_label'] ?? '') === 'reply-agent'): ?>
    <p class="meta">Author: reply-agent <span class="agent-label">automated reply agent</span></p>
<?php endif; ?>
<?= $indent($partial('partials/source_metadata.php', [
    'source_path' => $item['source_path'] ?? '',
    'source_commit_sha' => $item['source_commit_sha'] ?? '',
    'source_path_href' => $item['source_path_href'] ?? '',
    'source_commit_href' => $item['source_commit_href'] ?? '',
    'source_signature_path' => $item['source_signature_path'] ?? '',
    'source_signature_href' => $item['source_signature_href'] ?? '',
]), 2) ?>
    <p class="meta"><?= $contentMeta($item, 'created_at', '') ?></p>
  </article>
<?php endforeach; ?>
</section>
