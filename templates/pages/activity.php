<section class="stack">
  <article class="card">
    <h1><?= $view === 'security' ? 'Access history' : 'Activity' ?></h1>
    <div class="nav">
<?php foreach ($viewOptions as $option): ?>
<?php if ($view === 'security' && $option['label'] === 'RSS') continue; ?>
<?php $class = $option['is_active'] ? 'nav-link is-active' : 'nav-link'; ?>
      <a class="<?= $e($class) ?>" href="<?= $e($option['href']) ?>"><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </div>
<?php if ($view === 'security'): ?>
    <p><a href="/tools/user-access/#access-history">User Access</a></p>
<?= $partial('partials/activity_security_notice.php', ['approvalPolicyEnabled' => $approvalPolicyEnabled]) ?>
<?php endif; ?>
  </article>
<?php if ($paginationError !== null): ?>
  <p role="alert"><?= $e($paginationError) ?></p>
<?php elseif ($items === []): ?>
  <p><?= $view === 'security' ? 'No access changes recorded yet.' : 'No activity.' ?></p>
<?php endif; ?>
<?php foreach ($items as $item): ?>
<?php if ($view === 'security'): ?>
  <article class="card">
<?= $partial('partials/access_history_event.php', ['item' => $item]) ?>
  </article>
<?php continue; endif; ?>
<?php $href = $item['link']['href']; ?>
<?php $linkLabel = $item['link']['label']; ?>
  <article class="card" data-heat="<?= $heat($item['created_at'] ?? null) ?>">
    <p class="meta"><?= $e($item['kind']) ?></p>
    <p><a href="<?= $e($href) ?>"><?= $e($linkLabel) ?></a></p>
    <p><?= $e($item['label']) ?></p>
<?php if ((string) ($item['author_label'] ?? '') === 'reply-agent'): ?>
    <p class="meta">Author: reply-agent <span class="agent-label">automated reply agent</span></p>
<?php endif; ?>
<?php if (($item['relevant_files'] ?? []) === []): ?>
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
        'signature_status' => $item['source_signature_status'] ?? '',
    'files' => $item['relevant_files'] ?? [],
    'heading' => 'Relevant files',
    'commit_sha' => ($item['source_commit_href'] ?? '') !== '' ? ($item['source_commit_sha'] ?? '') : '',
    'show_unavailable_commit' => true,
    'commit_href' => $item['source_commit_href'] ?? '',
]), 2) ?>
    <p class="meta"><?= $contentMeta($item, 'created_at', '') ?></p>
  </article>
<?php endforeach; ?>
<?= $partial('partials/page_navigation.php', ['pagination' => $pagination]) ?>
</section>
