<?php
$agentReply = $agentRepliesByPostId[$post['post_id']] ?? null;
$agentReplyPostedId = is_array($agentReply) && isset($agentReply['agent_post_id']) ? (string) $agentReply['agent_post_id'] : '';
$isAgentPost = (string) ($post['author_label'] ?? '') === 'reply-agent';
?>
<article class="card<?= $isAgentPost ? ' agent-authored-post' : '' ?>" data-post-id="<?= $e($post['post_id']) ?>"<?= $isAgentPost ? ' data-agent-authored="reply-agent"' : '' ?><?= $agentReplyPostedId !== '' ? ' data-agent-reply-posted-id="' . $e($agentReplyPostedId) . '"' : '' ?>>
  <p class="meta">Post <a href="/posts/<?= $e($post['post_id']) ?>"><?= $e($post['post_id']) ?></a></p>
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
$postAnalysisLabels = $postAnalysisModeration['labels'] ?? [];
if (!is_array($postAnalysisLabels)) {
    $postAnalysisLabels = [];
}
?>
  <div class="button-row button-row-natural post-card-actions">
    <a href="/compose/reply?thread_id=<?= $e($post['thread_id']) ?>&amp;parent_id=<?= $e($post['post_id']) ?>">Reply</a>
    <p class="meta agent-reply-feedback" data-role="agent-reply-feedback" hidden></p>
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
    </div>
  </details>
<?php endif; ?>
  </div>
</article>
