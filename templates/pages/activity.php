<section data-railspike-activity hidden aria-label="Private site development activity"></section>
<section class="stack">
  <article class="card">
    <h1><?= $view === 'security' ? 'Security review' : 'Activity' ?></h1>
    <div class="nav">
<?php foreach ($viewOptions as $option): ?>
<?php if ($view === 'security' && $option['label'] === 'RSS') continue; ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </div>
<?php if ($view === 'security'): ?>
<?= $partial('partials/activity_security_notice.php', ['approvalPolicyEnabled' => $approvalPolicyEnabled]) ?>
<?php endif; ?>
  </article>
<?php if ($paginationError !== null): ?>
  <p role="alert"><?= $e($paginationError) ?></p>
<?php elseif ($items === []): ?>
  <p>No activity.</p>
<?php endif; ?>
<?php foreach ($items as $item): ?>
<?php $href = $item['link']['href']; ?>
<?php $linkLabel = $item['link']['label']; ?>
  <article class="card" data-heat="<?= $heat($item['created_at'] ?? null) ?>">
    <p class="meta"><?= $e($item['kind']) ?></p>
    <p><a href="<?= $e($href) ?>"><?= $e($linkLabel) ?></a></p>
    <p><?= $e($item['label']) ?></p>
<?php if ($view === 'security'): ?>
<?= $partial('partials/approval_security_review.php', ['item' => $item]) ?>
<?php endif; ?>
<?php if ((string) ($item['author_label'] ?? '') === 'reply-agent'): ?>
    <p class="meta">Author: reply-agent <span class="agent-label">automated reply agent</span></p>
<?php endif; ?>
<?php if (($item['source_commit_files'] ?? []) === []): ?>
<?= $indent($partial('partials/source_metadata.php', [
    'source_path' => $item['source_path'] ?? '',
    'source_commit_sha' => $item['source_commit_sha'] ?? '',
    'source_path_href' => $item['source_path_href'] ?? '',
    'source_commit_href' => $item['source_commit_href'] ?? '',
    'source_signature_path' => $item['source_signature_path'] ?? '',
    'source_signature_href' => $item['source_signature_href'] ?? '',
    'source_signature_status' => $item['source_signature_status'] ?? '',
]), 2) ?>
<?php endif; ?>
<?= $indent($partial('partials/activity_commit_manifest.php', [
        'signature_status' => in_array($item['record_family'] ?? '', ['thread_label', 'post_reaction', 'profile_name'], true) ? ($item['source_signature_status'] ?? '') : '',
    'files' => $item['source_commit_files'] ?? [],
    'commit_sha' => $item['source_commit_sha'] ?? '',
    'commit_href' => $item['source_commit_href'] ?? '',
]), 2) ?>
    <p class="meta"><?= $contentMeta($item, 'created_at', '') ?></p>
  </article>
<?php endforeach; ?>
<?= $partial('partials/page_navigation.php', ['pagination' => $pagination]) ?>
</section>
