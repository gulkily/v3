<?php $event = \ForumRewrite\Activity\AccessHistoryPresenter::present($item); ?>
<details class="access-history-event" data-access-history-id="<?= $e($item['id']) ?>">
  <summary>
    <span class="access-history-icon" aria-hidden="true"><?= $e($event['icon']) ?></span>
    <span><span class="access-history-title"><?= $e($event['title']) ?></span><span class="access-history-byline"><?= $e($event['byline']) ?></span></span>
    <time datetime="<?= $e($event['created_at']) ?>"><?= $e(str_replace(['T', 'Z'], [' ', ' UTC'], $event['created_at'])) ?></time>
    <span class="access-history-chevron" aria-hidden="true">›</span>
  </summary>
  <div class="access-history-evidence">
<?php if (($item['kind'] ?? '') === 'site_feature_flag'): ?>
    <p><?= $e($item['label']) ?></p>
<?php endif; ?>
<?= $partial('partials/approval_security_review.php', ['item' => $item]) ?>
    <details class="access-history-source">
      <summary>Source evidence</summary>
<?php if (($item['link']['href'] ?? '') !== ''): ?>
      <p><a href="<?= $e($item['link']['href']) ?>"><?= $e($item['link']['label']) ?></a></p>
<?php endif; ?>
<?php if (($item['relevant_files'] ?? []) !== []): ?>
<?= $partial('partials/activity_commit_manifest.php', [
    'files' => $item['relevant_files'],
    'commit_sha' => $item['source_commit_sha'] ?? '',
    'commit_href' => $item['source_commit_href'] ?? '',
    'heading' => 'Relevant files',
]) ?>
<?php else: ?>
<?= $partial('partials/source_metadata.php', [
    'source_path' => $item['source_path'] ?? '',
    'source_commit_sha' => $item['source_commit_sha'] ?? '',
    'source_path_href' => $item['source_path_href'] ?? '',
    'source_commit_href' => $item['source_commit_href'] ?? '',
    'source_signature_path' => $item['source_signature_path'] ?? '',
    'source_signature_href' => $item['source_signature_href'] ?? '',
    'source_signature_status' => $item['source_signature_status'] ?? '',
]) ?>
<?php endif; ?>
    </details>
  </div>
</details>
