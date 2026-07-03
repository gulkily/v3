<?php
$agentReply = $agentRepliesByPostId[$post['post_id']] ?? null;
$agentReplyPostedId = is_array($agentReply) && isset($agentReply['agent_post_id']) ? (string) $agentReply['agent_post_id'] : '';
$agentReplyWork = (string) ($agentReplyWorkByPostId[$post['post_id']] ?? '');
$isAgentPost = (string) ($post['author_label'] ?? '') === 'reply-agent';
$viewerHasLikedPost = isset($viewerPostLikes[(string) $post['post_id']]);
$viewerHasFlaggedPost = isset($viewerPostFlags[(string) $post['post_id']]);
$postPermalinkLabel = 'Post ' . (string) $post['post_id'];
$postAnchorId = 'post-' . (string) $post['post_id'];
?>
<article id="<?= $e($postAnchorId) ?>" class="card post-card<?= $isAgentPost ? ' agent-authored-post' : '' ?>" data-heat="<?= $heat($post['created_at'] ?? null) ?>" data-post-id="<?= $e($post['post_id']) ?>"<?= $isAgentPost ? ' data-agent-authored="reply-agent"' : '' ?><?= $agentReplyPostedId !== '' ? ' data-agent-reply-posted-id="' . $e($agentReplyPostedId) . '"' : '' ?><?= $agentReplyWork !== '' ? ' data-agent-reply-work="' . $e($agentReplyWork) . '"' : '' ?>>
  <p class="meta"><?= $contentMeta($post, 'created_at', '') ?></p>
<?php if ($isAgentPost): ?>
  <p class="meta"><span class="agent-label">Agent-authored reply</span></p>
<?php endif; ?>
  <div class="body"><?= $br($post['body']) ?></div>
<?php
$postAnalysis = ((bool) ($viewerCanSeePostAnalysis ?? false))
    ? (($postAnalysesByPostId[$post['post_id']] ?? null))
    : null;
$postAnalysisModeration = is_array($postAnalysis) && is_array($postAnalysis['moderation'] ?? null) ? $postAnalysis['moderation'] : [];
$postAnalysisEngagement = is_array($postAnalysis) && is_array($postAnalysis['engagement'] ?? null) ? $postAnalysis['engagement'] : [];
$postAnalysisQuality = is_array($postAnalysis) && is_array($postAnalysis['quality'] ?? null) ? $postAnalysis['quality'] : [];
$postAnalysisRespondability = is_array($postAnalysis) && is_array($postAnalysis['respondability'] ?? null) ? $postAnalysis['respondability'] : [];
$postAnalysisRelatedContent = is_array($postAnalysis) && is_array($postAnalysis['related_content'] ?? null) ? $postAnalysis['related_content'] : [];
$postAnalysisUnicodeRisk = is_array($postAnalysis) && is_array($postAnalysis['unicode_risk'] ?? null) ? $postAnalysis['unicode_risk'] : [];
$postAnalysisUnicodeFacts = is_array($postAnalysisUnicodeRisk['deterministic_facts'] ?? null) ? $postAnalysisUnicodeRisk['deterministic_facts'] : [];
$postAnalysisUnicodeReview = is_array($postAnalysisUnicodeRisk['llm_review'] ?? null) ? $postAnalysisUnicodeRisk['llm_review'] : [];
$postAnalysisUnicodeFields = is_array($postAnalysisUnicodeFacts['fields'] ?? null) ? $postAnalysisUnicodeFacts['fields'] : [];
$postAnalysisUnicodeLabels = [];
$postAnalysisUnicodeScripts = [];
$postAnalysisUnicodeCodePoints = [];
foreach ($postAnalysisUnicodeFields as $unicodeFieldName => $unicodeFieldFacts) {
    if (!is_array($unicodeFieldFacts)) {
        continue;
    }
    foreach (($unicodeFieldFacts['risk_labels'] ?? []) as $riskLabel) {
        $postAnalysisUnicodeLabels[(string) $riskLabel] = true;
    }
    $scripts = $unicodeFieldFacts['scripts_present'] ?? [];
    if (is_array($scripts) && $scripts !== []) {
        $postAnalysisUnicodeScripts[] = (string) $unicodeFieldName . ': ' . implode(', ', array_map('strval', $scripts));
    }
    foreach (($unicodeFieldFacts['suspicious_code_points'] ?? []) as $codePointFinding) {
        if (!is_array($codePointFinding)) {
            continue;
        }
        $findingLabels = is_array($codePointFinding['labels'] ?? null) ? implode(', ', array_map('strval', $codePointFinding['labels'])) : '';
        $postAnalysisUnicodeCodePoints[] = (string) $unicodeFieldName . ' ' . (string) ($codePointFinding['code_point'] ?? '') . ($findingLabels !== '' ? ' (' . $findingLabels . ')' : '');
    }
}
$postAnalysisUnicodeLabels = array_keys($postAnalysisUnicodeLabels);
$postAnalysisSummary = is_array($postAnalysis) ? trim((string) ($postAnalysis['post_summary'] ?? '')) : '';
$postAnalysisLabels = $postAnalysisModeration['labels'] ?? [];
if (!is_array($postAnalysisLabels)) {
    $postAnalysisLabels = [];
}
?>
  <div class="button-row button-row-natural post-card-actions">
    <a href="/compose/reply?thread_id=<?= $e($post['thread_id']) ?>&amp;parent_id=<?= $e($post['post_id']) ?>">Reply</a>
    <button
      type="button"
      class="thread-reaction-button"
      data-action="apply-post-tag"
      data-post-id="<?= $e($post['post_id']) ?>"
      data-tag="like"
      data-applied-label="Liked"
      aria-pressed="<?= $viewerHasLikedPost ? 'true' : 'false' ?>"
<?= $viewerHasLikedPost ? ' disabled="disabled"' : '' ?>
    ><?= $viewerHasLikedPost ? 'Liked' : 'Like' ?></button>
    <button
      type="button"
      class="thread-reaction-button"
      data-action="apply-post-tag"
      data-post-id="<?= $e($post['post_id']) ?>"
      data-tag="flag"
      data-applied-label="Flagged"
      aria-pressed="<?= $viewerHasFlaggedPost ? 'true' : 'false' ?>"
<?= $viewerHasFlaggedPost ? ' disabled="disabled"' : '' ?>
    ><?= $viewerHasFlaggedPost ? 'Flagged' : 'Flag' ?></button>
    <p class="meta thread-reaction-feedback" data-role="post-reaction-feedback" hidden></p>
    <p class="meta agent-reply-feedback" data-role="agent-reply-feedback" hidden></p>
<?php if (is_array($postAnalysis) && ($postAnalysis['status'] ?? '') === 'complete'): ?>
  <details class="post-analysis">
    <summary>Post analysis</summary>
    <div class="stack">
      <p class="meta">Provider: <?= $e(($postAnalysis['provider'] ?? 'unknown') . ' / ' . ($postAnalysis['provider_model'] ?? 'unknown')) ?></p>
<?php if ($postAnalysisSummary !== ''): ?>
      <p><strong>Post summary:</strong> <?= $e($postAnalysisSummary) ?></p>
<?php endif; ?>
      <p><strong>Moderation:</strong> <?= $e($postAnalysisModeration['severity'] ?? 'unknown') ?><?= $postAnalysisLabels !== [] ? ' (' . $e(implode(', ', array_map('strval', $postAnalysisLabels))) . ')' : '' ?></p>
<?php if (isset($postAnalysisModeration['recommended_action'])): ?>
      <p><strong>Recommended action:</strong> <?= $e($postAnalysisModeration['recommended_action']) ?></p>
<?php endif; ?>
<?php if (isset($postAnalysisModeration['summary']) && trim((string) $postAnalysisModeration['summary']) !== ''): ?>
      <p><strong>Moderation summary:</strong> <?= $e($postAnalysisModeration['summary']) ?></p>
<?php endif; ?>
<?php if (isset($postAnalysisQuality['discussion_value']) || isset($postAnalysisQuality['good_faith_likelihood'])): ?>
      <p><strong>Quality:</strong> <?= $e($postAnalysisQuality['discussion_value'] ?? 'unknown') ?><?php if (isset($postAnalysisQuality['good_faith_likelihood'])): ?>, good faith <?= $e($postAnalysisQuality['good_faith_likelihood']) ?><?php endif; ?></p>
<?php endif; ?>
<?php if ($postAnalysisRespondability !== []): ?>
      <p><strong>Respondability:</strong> <?= $e($postAnalysisRespondability['overall_score'] ?? 'unknown') ?><?php if (isset($postAnalysisRespondability['best_response_mode'])): ?>, <?= $e($postAnalysisRespondability['best_response_mode']) ?><?php endif; ?><?php if (array_key_exists('should_generate_response', $postAnalysisRespondability)): ?>, generate <?= ((bool) $postAnalysisRespondability['should_generate_response']) ? 'yes' : 'no' ?><?php endif; ?></p>
<?php if (isset($postAnalysisRespondability['asks_question']) || isset($postAnalysisRespondability['question_type'])): ?>
      <p><strong>Question:</strong> <?= ((bool) ($postAnalysisRespondability['asks_question'] ?? false)) ? 'yes' : 'no' ?><?php if (isset($postAnalysisRespondability['question_type'])): ?>, <?= $e($postAnalysisRespondability['question_type']) ?><?php endif; ?></p>
<?php endif; ?>
<?php if (isset($postAnalysisRespondability['audience_benefit']) || isset($postAnalysisRespondability['response_risk'])): ?>
      <p><strong>Response value:</strong> audience <?= $e($postAnalysisRespondability['audience_benefit'] ?? 'unknown') ?><?php if (isset($postAnalysisRespondability['author_benefit'])): ?>, author <?= $e($postAnalysisRespondability['author_benefit']) ?><?php endif; ?><?php if (isset($postAnalysisRespondability['response_risk'])): ?>, risk <?= $e($postAnalysisRespondability['response_risk']) ?><?php endif; ?></p>
<?php endif; ?>
<?php if (isset($postAnalysisRespondability['reason']) && trim((string) $postAnalysisRespondability['reason']) !== ''): ?>
      <p><strong>Response reason:</strong> <?= $e($postAnalysisRespondability['reason']) ?></p>
<?php endif; ?>
<?php endif; ?>
<?php if (isset($postAnalysisEngagement['suggested_response']) && trim((string) $postAnalysisEngagement['suggested_response']) !== ''): ?>
      <p><strong>Suggested response:</strong> <?= $e($postAnalysisEngagement['suggested_response']) ?></p>
<?php endif; ?>
<?php if ($postAnalysisUnicodeRisk !== []): ?>
      <p><strong>Unicode risk:</strong> <?= $e($postAnalysisUnicodeReview['review_priority'] ?? 'none') ?><?= $postAnalysisUnicodeLabels !== [] ? ' (' . $e(implode(', ', $postAnalysisUnicodeLabels)) . ')' : '' ?></p>
<?php if ($postAnalysisUnicodeScripts !== []): ?>
      <p><strong>Unicode scripts:</strong> <?= $e(implode('; ', $postAnalysisUnicodeScripts)) ?></p>
<?php endif; ?>
<?php if (isset($postAnalysisUnicodeReview['summary']) && trim((string) $postAnalysisUnicodeReview['summary']) !== ''): ?>
      <p><strong>Unicode review:</strong> <?= $e($postAnalysisUnicodeReview['summary']) ?></p>
<?php endif; ?>
<?php if ($postAnalysisUnicodeCodePoints !== []): ?>
      <p><strong>Unicode code points:</strong> <?= $e(implode('; ', array_slice($postAnalysisUnicodeCodePoints, 0, 8))) ?></p>
<?php endif; ?>
<?php endif; ?>
    </div>
  </details>
<?php endif; ?>
  </div>
  <a class="post-card-permalink" href="/posts/<?= $e($post['post_id']) ?>" title="<?= $e($postPermalinkLabel) ?>" aria-label="<?= $e($postPermalinkLabel) ?>">#</a>
</article>
<?php if ($postAnalysisRelatedContent !== []): ?>
<article class="card possibly-related" aria-label="Possibly related">
  <p class="possibly-related-title">Possibly related</p>
  <ul>
<?php foreach (array_slice($postAnalysisRelatedContent, 0, 5) as $related): ?>
<?php
    $relatedPostUrl = (string) ($related['post_url'] ?? '');
    $relatedLabel = trim((string) ($related['subject'] ?? '')) ?: (string) ($related['post_id'] ?? 'Related post');
    $relatedExcerpt = trim((string) ($related['excerpt'] ?? ''));
?>
<?php if ($relatedPostUrl !== ''): ?>
    <li><a href="<?= $e($relatedPostUrl) ?>"><?= $e($relatedLabel) ?></a><?php if ($relatedExcerpt !== ''): ?>: <?= $e($relatedExcerpt) ?><?php endif; ?></li>
<?php endif; ?>
<?php endforeach; ?>
  </ul>
</article>
<?php endif; ?>
