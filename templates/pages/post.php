<?php
$postLlmExchanges = $llmExchangesByPostId[$post['post_id']] ?? [];
$authorPublicKeyPath = trim((string) ($post['author_public_key_path'] ?? ''));
$authorPublicKeyHref = trim((string) ($post['author_public_key_href'] ?? ''));
?>
<?= $partial('partials/agent_response_mode_catalog.php') ?>
<section class="stack">
  <article class="card">
    <h1>Post <?= $e($post['post_id']) ?></h1>
    <p class="meta">Thread <a href="/threads/<?= $e($post['thread_id']) ?>"><?= $e($post['thread_id']) ?></a></p>
    <p class="meta">Score: <?= (int) ($post['post_score_total'] ?? 0) ?></p>
<?php if (is_array($fastScore ?? null)): ?>
    <div class="meta">Fastmod<?= !empty($fastScore['legacy']) ? ' (legacy)' : '' ?><?= !empty($fastScore['stale']) ? ' (earlier rubric revision)' : '' ?>:
<?php if (($fastScore['status'] ?? '') === 'scored'): ?>
<?php foreach (($fastScore['scores'] ?? []) as $rubricId => $value): ?>
      <span><?= $e(ucfirst(str_replace('_', ' ', $rubricId))) ?>: <?= $e(number_format((float) $value, 2)) ?></span>
<?php endforeach; ?>
<?php else: ?>
      <span><?= $e(str_replace('_', ' ', (string) $fastScore['status'])) ?></span>
<?php endif; ?>
    </div>
<?php endif; ?>
<?= $indent($partial('partials/source_metadata.php', [
    'source_path' => $post['source_path'] ?? '',
    'source_commit_sha' => $post['source_commit_sha'] ?? '',
    'source_path_href' => $post['source_path_href'] ?? '',
    'source_commit_href' => $post['source_commit_href'] ?? '',
    'source_signature_path' => $post['source_signature_path'] ?? '',
    'source_signature_href' => $post['source_signature_href'] ?? '',
    'source_signature_status' => $post['source_signature_status'] ?? '',
]), 2) ?>
<?php if ($authorPublicKeyHref !== ''): ?>
    <p class="meta">Public key: <a href="<?= $e($authorPublicKeyHref) ?>"><?= $e($authorPublicKeyPath !== '' ? $authorPublicKeyPath : 'Open public key') ?></a></p>
<?php endif; ?>
<?php if ($postLlmExchanges !== []): ?>
    <p class="meta">LLM exchanges: <?php foreach ($postLlmExchanges as $index => $exchange): ?><?php if ($index > 0): ?>, <?php endif; ?><a href="/tools/llm-exchanges/<?= (int) $exchange['id'] ?>">#<?= (int) $exchange['id'] ?></a><?php endforeach; ?></p>
<?php endif; ?>
  </article>
<?= $indent($partial('partials/post_card.php', ['post' => $post]), 1) ?>
</section>
