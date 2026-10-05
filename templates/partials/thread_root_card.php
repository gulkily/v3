<?php
$agentReply = $agentRepliesByPostId[$post['post_id']] ?? null;
$agentReplyPostedId = is_array($agentReply) && isset($agentReply['agent_post_id']) ? (string) $agentReply['agent_post_id'] : '';
$agentReplyStatus = is_array($agentReply) ? (string) ($agentReply['status'] ?? '') : '';
$agentReplyWork = (string) ($agentReplyWorkByPostId[$post['post_id']] ?? '');
$isAgentPost = (string) ($post['author_label'] ?? '') === 'reply-agent';
$viewerCanRequestAgentReply = (bool) ($viewerCanRequestAgentReply ?? $viewerCanSeePostAnalysis ?? false);
$showAgentReplyRequestButton = $viewerCanRequestAgentReply && !$isAgentPost && !is_array($agentReply);
$codexHandoff = $codexHandoffsByPostId[$post['post_id']] ?? null;
$codexHandoffStatus = is_array($codexHandoff) ? (string) ($codexHandoff['status'] ?? '') : '';
$codexHandoffId = is_array($codexHandoff) ? (string) ($codexHandoff['handoff_id'] ?? '') : '';
$viewerCanUseCodexHandoff = (bool) ($viewerCanUseCodexHandoff ?? false);
$postCanUseCodexHandoff = (bool) (($codexHandoffEligiblePostIds[(string) $post['post_id']] ?? false));
$showCodexHandoffButton = $viewerCanUseCodexHandoff && $postCanUseCodexHandoff && !is_array($codexHandoff);
$codexHandoffFeedbackText = match ($codexHandoffStatus) {
    'requested' => 'Codex handoff requested.',
    'draft_ready' => 'Codex handoff ready for approval.',
    'approved' => 'Codex handoff approved.',
    'rejected' => 'Codex handoff rejected.',
    'running' => 'Codex handoff running.',
    'completed' => 'Codex handoff completed.',
    'failed' => 'Codex handoff failed.',
    default => '',
};
$codexHandoffSummaryLabel = match ($codexHandoffStatus) {
    'draft_ready' => 'Ready for approval',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'running' => 'Running',
    'completed' => 'Completed',
    'failed' => 'Failed',
    default => 'Requested',
};
$agentReplyFeedbackText = '';
if ($agentReplyPostedId !== '') {
    $agentReplyFeedbackText = 'Agent reply available.';
} elseif ($agentReplyStatus === 'requested') {
    $agentReplyFeedbackText = 'Agent reply requested.';
} elseif (in_array($agentReplyStatus, ['pending', 'complete', 'posting'], true)) {
    $agentReplyFeedbackText = 'Agent reply request in progress.';
} elseif ($agentReplyStatus === 'skipped') {
    $agentReplyFeedbackText = 'Agent reply skipped.';
} elseif ($agentReplyStatus === 'failed') {
    $agentReplyFeedbackText = 'Agent reply failed.';
}
$viewerHasFlaggedPost = isset($viewerPostFlags[(string) $post['post_id']]);
$postPermalinkLabel = 'Post ' . (string) $post['post_id'];
$postAnchorId = 'post-' . (string) $post['post_id'];
$postBody = (string) $post['body'];
$postBodyFirstLineSegments = preg_split('/\r\n|\r|\n/', $postBody, 2);
$postBodyFirstLine = trim($postBodyFirstLineSegments[0] ?? '');
$postBodyDisplay = ($postBodyFirstLine !== '' && $postBodyFirstLine === trim($title))
    ? preg_replace('/^(?:\r\n|\r|\n)+/', '', $postBodyFirstLineSegments[1] ?? '')
    : $postBody;
$isQdbQuoteRoot = \ForumRewrite\SiteConfig::siteName() === 'qdb';
$quoteRootId = (string) $thread['root_post_id'];
$quoteRootHasNumber = preg_match('/-qdb-(\d+)$/', $quoteRootId, $quoteRootNumberMatch) === 1;
$quoteRootDisplayNumber = $quoteRootHasNumber ? $quoteRootNumberMatch[1] : $quoteRootId;
$quoteRootPermalinkHref = $quoteRootHasNumber ? '/' . $quoteRootDisplayNumber : '/threads/' . $quoteRootId;
$quoteRootScoreTotal = (int) ($thread['score_total'] ?? 0);
$quoteRootVoteCount = (int) ($thread['vote_count'] ?? 0);
$quoteRootScoreSignClass = $quoteRootScoreTotal > 0 ? 'quote-card-score-positive' : ($quoteRootScoreTotal < 0 ? 'quote-card-score-negative' : '');
$viewerHasUpvoted = (bool) ($viewerHasUpvoted ?? false);
$viewerHasDownvoted = (bool) ($viewerHasDownvoted ?? false);
$metaVisible = (bool) ($metaVisible ?? true);
$rootTimeLabel = '';
if (!$metaVisible) {
    try {
        $rootTimeLabel = (new DateTimeImmutable((string) ($post['created_at'] ?? '')))->format('H:i');
    } catch (\Exception) {
        $rootTimeLabel = '';
    }
}
?>
<article id="<?= $e($postAnchorId) ?>" class="card post-card thread-root-card<?= $isAgentPost ? ' agent-authored-post' : '' ?><?= $metaVisible ? '' : ' meta-deferred' ?>" data-heat="<?= $heat($thread['last_activity_at'] ?? ($post['created_at'] ?? null), (int) ($thread['reply_count'] ?? 0)) ?>" data-thread-reactions-root data-thread-id="<?= $e($thread['root_post_id']) ?>" data-post-id="<?= $e($post['post_id']) ?>" data-author="<?= $e((string) ($post['author_label'] ?? '')) ?>"<?= $rootTimeLabel !== '' ? ' data-time="' . $e($rootTimeLabel) . '"' : '' ?><?= $isAgentPost ? ' data-agent-authored="reply-agent"' : '' ?><?= $agentReplyPostedId !== '' ? ' data-agent-reply-posted-id="' . $e($agentReplyPostedId) . '"' : '' ?><?= $agentReplyWork !== '' ? ' data-agent-reply-work="' . $e($agentReplyWork) . '"' : '' ?>>
<?php if ($isQdbQuoteRoot): ?>
  <p class="quote-card-header">
    <a class="quote-card-permalink" href="<?= $e($quoteRootPermalinkHref) ?>">#<?= $e($quoteRootDisplayNumber) ?></a>
    <span class="meta quote-card-score <?= $e($quoteRootScoreSignClass) ?>" data-role="thread-score" data-score-format="bare-ratio">(<?= $quoteRootScoreTotal ?>/<?= $quoteRootVoteCount ?>)</span>
  </p>
  <p class="quote-card-body"><?= $br($postBody) ?></p>
<?php else: ?>
  <h1><?= $e($title) ?></h1>
  <div class="body"><?= $br($postBodyDisplay) ?></div>
<?php endif; ?>
<?php if ($metaVisible && !$isQdbQuoteRoot): ?>
  <p class="meta"><?= $contentMeta($post, 'created_at', '') ?><?php if ($thread['thread_labels'] !== []): ?> · Labels: <?= $e(implode(', ', $thread['thread_labels'])) ?><?php endif; ?><?php if ($isAgentPost): ?> · <span class="agent-label">Agent-authored reply</span><?php endif; ?><?php $trueReplyCount = (int) ($trueReplyCount ?? 0); if ($trueReplyCount > 0): ?> · <?= $trueReplyCount ?> <?= $trueReplyCount === 1 ? 'reply' : 'replies' ?><?php endif; ?></p>
<?php endif; ?>
<?= $indent($partial('partials/post_identity_details.php', ['post' => $post]), 1) ?>
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
$postLlmExchangesByPostId = is_array($llmExchangesByPostId ?? null) ? $llmExchangesByPostId : [];
$postLlmExchanges = is_array($postLlmExchangesByPostId[$post['post_id']] ?? null) ? $postLlmExchangesByPostId[$post['post_id']] : [];
?>
  <button type="button" class="post-card-actions-toggle thread-reaction-button" aria-label="Show actions for this post">Actions</button>
  <div class="button-row button-row-natural post-card-actions thread-root-actions">
    <a href="/compose/reply?thread_id=<?= $e($post['thread_id']) ?>&amp;parent_id=<?= $e($post['post_id']) ?>">Reply</a>
<?php if ($isQdbQuoteRoot): ?>
    <button
      type="button"
      class="thread-reaction-button quote-card-vote-button"
      data-action="apply-thread-tag"
      data-tag="upvote"
      data-applied-label="+"
      aria-label="Upvote this quote"
      aria-pressed="<?= $viewerHasUpvoted ? 'true' : 'false' ?>"
<?= $viewerHasUpvoted ? ' disabled="disabled"' : '' ?>
    >+</button>
    <button
      type="button"
      class="thread-reaction-button quote-card-vote-button"
      data-action="apply-thread-tag"
      data-tag="downvote"
      data-applied-label="-"
      aria-label="Downvote this quote"
      aria-pressed="<?= $viewerHasDownvoted ? 'true' : 'false' ?>"
<?= $viewerHasDownvoted ? ' disabled="disabled"' : '' ?>
    >-</button>
    <button
      type="button"
      class="thread-reaction-button quote-card-vote-button"
      data-action="apply-post-tag"
      data-post-id="<?= $e($post['post_id']) ?>"
      data-tag="flag"
      data-applied-label="[X]"
      aria-label="Flag this quote for review"
      aria-pressed="<?= $viewerHasFlaggedPost ? 'true' : 'false' ?>"
<?= $viewerHasFlaggedPost ? ' disabled="disabled"' : '' ?>
    >[X]</button>
<?php else: ?>
    <button
      type="button"
      class="thread-reaction-button"
      data-action="apply-thread-tag"
      data-tag="like"
      data-applied-label="Liked"
      aria-pressed="<?= $viewerHasLiked ? 'true' : 'false' ?>"
<?= $viewerHasLiked ? ' disabled="disabled"' : '' ?>
    ><?= $viewerHasLiked ? 'Liked' : 'Like' ?></button>
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
<?php endif; ?>
<?php if ($showAgentReplyRequestButton): ?>
    <button
      type="button"
      class="thread-reaction-button"
      data-action="request-agent-reply"
      data-post-id="<?= $e($post['post_id']) ?>"
    >Request agent response</button>
<?php endif; ?>
<?php if ($showCodexHandoffButton): ?>
    <button
      type="button"
      class="thread-reaction-button"
      data-action="request-codex-handoff"
      data-post-id="<?= $e($post['post_id']) ?>"
    >Handoff to Codex</button>
<?php endif; ?>
<?php if ($postLlmExchanges !== []): ?>
    <span class="meta">LLM exchanges: <?php foreach ($postLlmExchanges as $index => $exchange): ?><?php if ($index > 0): ?>, <?php endif; ?><a href="/tools/llm-exchanges/<?= (int) $exchange['id'] ?>">#<?= (int) $exchange['id'] ?></a><?php endforeach; ?></span>
<?php endif; ?>
    <p class="meta thread-reaction-feedback" data-role="post-reaction-feedback" hidden></p>
    <p class="meta thread-reaction-feedback" data-role="thread-reaction-feedback" hidden></p>
    <p class="meta agent-reply-feedback" data-role="agent-reply-feedback"<?= $agentReplyFeedbackText === '' ? ' hidden' : '' ?>><?= $e($agentReplyFeedbackText) ?><?php if ($agentReplyPostedId !== ''): ?> <a href="/posts/<?= $e($agentReplyPostedId) ?>">View agent reply.</a><?php endif; ?></p>
    <p class="meta codex-handoff-feedback" data-role="codex-handoff-feedback"<?= $codexHandoffFeedbackText === '' ? ' hidden' : '' ?>><?= $e($codexHandoffFeedbackText) ?></p>
<?php if (is_array($codexHandoff)): ?>
  <details class="codex-handoff-preview" data-role="codex-handoff-preview" data-handoff-id="<?= $e($codexHandoffId) ?>" data-handoff-status="<?= $e($codexHandoffStatus) ?>"<?= $codexHandoffStatus === 'draft_ready' ? ' open' : '' ?>>
    <summary>Codex handoff: <?= $e($codexHandoffSummaryLabel) ?></summary>
    <div class="stack">
<?php if (trim((string) ($codexHandoff['user_story'] ?? '')) !== ''): ?>
      <p><strong>User story:</strong> <?= $e($codexHandoff['user_story']) ?></p>
<?php endif; ?>
<?php if (trim((string) ($codexHandoff['confidence_summary'] ?? '')) !== ''): ?>
      <p><strong>Confidence:</strong> <?= $e($codexHandoff['confidence_summary']) ?></p>
<?php endif; ?>
<?php if (trim((string) ($codexHandoff['fdp_step1'] ?? '')) !== ''): ?>
      <pre class="codex-handoff-draft"><?= $e($codexHandoff['fdp_step1']) ?></pre>
<?php endif; ?>
<?php if ($codexHandoffStatus === 'draft_ready'): ?>
      <div class="button-row button-row-natural codex-handoff-actions">
        <button type="button" class="thread-reaction-button" data-action="approve-codex-handoff" data-handoff-id="<?= $e($codexHandoffId) ?>">Approve handoff</button>
        <button type="button" class="thread-reaction-button" data-action="reject-codex-handoff" data-handoff-id="<?= $e($codexHandoffId) ?>">Reject handoff</button>
      </div>
<?php endif; ?>
    </div>
  </details>
<?php endif; ?>
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
