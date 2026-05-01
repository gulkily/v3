<article class="card">
  <p class="meta">Post <a href="/posts/<?= $e($post['post_id']) ?>"><?= $e($post['post_id']) ?></a></p>
  <p class="meta"><?= $contentMeta($post, 'created_at', '') ?></p>
  <div class="body"><?= $br($post['body']) ?></div>
<?php
$postAnalysis = ((bool) ($viewerCanSeePostAnalysis ?? false))
    ? (($postAnalysesByPostId[$post['post_id']] ?? null))
    : null;
$postAnalysisModeration = is_array($postAnalysis) && is_array($postAnalysis['moderation'] ?? null) ? $postAnalysis['moderation'] : [];
$postAnalysisEngagement = is_array($postAnalysis) && is_array($postAnalysis['engagement'] ?? null) ? $postAnalysis['engagement'] : [];
$postAnalysisQuality = is_array($postAnalysis) && is_array($postAnalysis['quality'] ?? null) ? $postAnalysis['quality'] : [];
$postAnalysisLabels = $postAnalysisModeration['labels'] ?? [];
if (!is_array($postAnalysisLabels)) {
    $postAnalysisLabels = [];
}
?>
<?php if (is_array($postAnalysis) && ($postAnalysis['status'] ?? '') === 'complete'): ?>
  <details class="post-analysis">
    <summary>Post analysis</summary>
    <div class="stack">
      <p class="meta">Provider: <?= $e(($postAnalysis['provider'] ?? 'unknown') . ' / ' . ($postAnalysis['provider_model'] ?? 'unknown')) ?></p>
      <p><strong>Moderation:</strong> <?= $e($postAnalysisModeration['severity'] ?? 'unknown') ?><?= $postAnalysisLabels !== [] ? ' (' . $e(implode(', ', array_map('strval', $postAnalysisLabels))) . ')' : '' ?></p>
<?php if (isset($postAnalysisModeration['recommended_action'])): ?>
      <p><strong>Recommended action:</strong> <?= $e($postAnalysisModeration['recommended_action']) ?></p>
<?php endif; ?>
<?php if (isset($postAnalysisModeration['summary']) && trim((string) $postAnalysisModeration['summary']) !== ''): ?>
      <p><strong>Summary:</strong> <?= $e($postAnalysisModeration['summary']) ?></p>
<?php endif; ?>
<?php if (isset($postAnalysisQuality['discussion_value']) || isset($postAnalysisQuality['good_faith_likelihood'])): ?>
      <p><strong>Quality:</strong> <?= $e($postAnalysisQuality['discussion_value'] ?? 'unknown') ?><?php if (isset($postAnalysisQuality['good_faith_likelihood'])): ?>, good faith <?= $e($postAnalysisQuality['good_faith_likelihood']) ?><?php endif; ?></p>
<?php endif; ?>
<?php if (isset($postAnalysisEngagement['suggested_response']) && trim((string) $postAnalysisEngagement['suggested_response']) !== ''): ?>
      <p><strong>Suggested response:</strong> <?= $e($postAnalysisEngagement['suggested_response']) ?></p>
<?php endif; ?>
    </div>
  </details>
<?php endif; ?>
  <p><a href="/compose/reply?thread_id=<?= $e($post['thread_id']) ?>&amp;parent_id=<?= $e($post['post_id']) ?>">Reply</a></p>
</article>
