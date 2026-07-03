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
<?php $sourcePath = (string) ($item['source_path'] ?? ''); ?>
<?php $sourceCommit = (string) ($item['source_commit_sha'] ?? ''); ?>
<?php $sourcePathHref = (string) ($item['source_path_href'] ?? ''); ?>
<?php $sourceCommitHref = (string) ($item['source_commit_href'] ?? ''); ?>
<?php $sourceCommitIsUnavailable = $sourceCommit === '' || $sourceCommit === 'no-git' || $sourceCommit === 'git-error'; ?>
<?php if ($sourcePath !== ''): ?>
    <p class="meta">Source:
<?php if ($sourcePathHref !== ''): ?>
      <a href="<?= $e($sourcePathHref) ?>"><?= $e($sourcePath) ?></a>
<?php else: ?>
      <span><?= $e($sourcePath) ?></span>
<?php endif; ?>
    </p>
    <p class="meta">Commit:
<?php if (!$sourceCommitIsUnavailable): ?>
<?php if ($sourceCommitHref !== ''): ?>
      <a href="<?= $e($sourceCommitHref) ?>" title="<?= $e($sourceCommit) ?>"><?= $e(substr($sourceCommit, 0, 12)) ?></a>
<?php else: ?>
      <span title="<?= $e($sourceCommit) ?>"><?= $e(substr($sourceCommit, 0, 12)) ?></span>
<?php endif; ?>
<?php else: ?>
      <span>commit unavailable</span>
<?php endif; ?>
    </p>
<?php endif; ?>
    <p class="meta"><?= $contentMeta($item, 'created_at', '') ?></p>
  </article>
<?php endforeach; ?>
</section>
