<?php
$postLlmExchanges = $llmExchangesByPostId[$post['post_id']] ?? [];
$authorPublicKeyPath = trim((string) ($post['author_public_key_path'] ?? ''));
$authorPublicKeyHref = trim((string) ($post['author_public_key_href'] ?? ''));
?>
<?= $partial('partials/agent_response_mode_catalog.php') ?>
<section class="stack">
  <article class="card">
    <h1>Post <?= $e($post['post_id']) ?></h1>
    <table class="codebase-facts post-facts">
      <tbody>
        <tr><th scope="row">Thread</th><td><a href="/threads/<?= $e($post['thread_id']) ?>"><?= $e($post['thread_id']) ?></a></td></tr>
        <tr><th scope="row">Score</th><td><?= (int) ($post['post_score_total'] ?? 0) ?></td></tr>
<?php if (is_array($fastScore ?? null)): ?>
        <tr><th scope="row">Fastmod<?= !empty($fastScore['legacy']) ? ' (legacy)' : '' ?><?= !empty($fastScore['stale']) ? ' (earlier rubric revision)' : '' ?></th><td>
<?php if (($fastScore['status'] ?? '') === 'scored'): ?>
<?php foreach (($fastScore['scores'] ?? []) as $rubricId => $value): ?>
          <span><?= $e(ucfirst(str_replace('_', ' ', $rubricId))) ?>: <?= $e(number_format((float) $value, 2)) ?></span>
<?php endforeach; ?>
<?php else: ?>
          <span><?= $e(str_replace('_', ' ', (string) $fastScore['status'])) ?></span>
<?php endif; ?>
        </td></tr>
<?php endif; ?>
<?= $indent($partial('partials/source_metadata.php', [
    'layout' => 'rows',
    'source_path' => $post['source_path'] ?? '',
    'source_commit_sha' => $post['source_commit_sha'] ?? '',
    'source_path_href' => $post['source_path_href'] ?? '',
    'source_commit_href' => $post['source_commit_href'] ?? '',
    'source_signature_path' => $post['source_signature_path'] ?? '',
    'source_signature_href' => $post['source_signature_href'] ?? '',
    'source_signature_status' => $post['source_signature_status'] ?? '',
]), 4) ?>
<?php if ($authorPublicKeyHref !== ''): ?>
        <tr><th scope="row">Public key</th><td><a href="<?= $e($authorPublicKeyHref) ?>"><?= $e($authorPublicKeyPath !== '' ? $authorPublicKeyPath : 'Open public key') ?></a></td></tr>
<?php endif; ?>
<?php if ($postLlmExchanges !== []): ?>
        <tr><th scope="row">LLM exchanges</th><td><?php foreach ($postLlmExchanges as $index => $exchange): ?><?php if ($index > 0): ?>, <?php endif; ?><a href="/tools/llm-exchanges/<?= (int) $exchange['id'] ?>">#<?= (int) $exchange['id'] ?></a><?php endforeach; ?></td></tr>
<?php endif; ?>
      </tbody>
    </table>
  </article>
<?= $indent($partial('partials/post_card.php', ['post' => $post]), 1) ?>
<?php if (is_array($postRecords ?? null)): ?>
<?= $indent($partial('partials/post_records.php', ['records' => $postRecords, 'viewerProfile' => $viewerProfile ?? null, 'viewerCanRetractOthers' => $viewerCanRetractOthers ?? false]), 1) ?>
<?php endif; ?>
</section>
