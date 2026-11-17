<?php
$quoteId = (string) $thread['root_post_id'];
$permalink = \ForumRewrite\Qdb\QdbQuoteNumbers::displayPermalink($quoteId);
$displayNumber = $permalink['displayNumber'];
$permalinkHref = $permalink['permalinkHref'];
$viewerHasVoted = isset($viewerVotedThreadIds[$quoteId]);
$viewerHasFlagged = isset($viewerFlaggedPostIds[$quoteId]);
$scoreTotal = (int) ($thread['score_total'] ?? 0);
$voteCount = (int) ($thread['vote_count'] ?? 0);
$scoreSignClass = $scoreTotal > 0 ? 'quote-card-score-positive' : ($scoreTotal < 0 ? 'quote-card-score-negative' : '');
$voteCaptionPair = is_array($voteCaptionPair ?? null) ? $voteCaptionPair : null;
$qdbVoteTags = is_array($qdbVoteTags ?? null) ? $qdbVoteTags : ['upvote', 'downvote'];
$upvote = is_array($voteCaptionPair['positive'] ?? null) ? $voteCaptionPair['positive'] : ['tag' => 'upvote', 'label' => '+'];
$downvote = is_array($voteCaptionPair['negative'] ?? null) ? $voteCaptionPair['negative'] : ['tag' => 'downvote', 'label' => '-'];
$scoreValueClass = 'quote-card-score-value' . ($scoreSignClass === '' ? '' : ' ' . $scoreSignClass);
?>
<article class="card post-card quote-card" data-thread-reactions-root data-thread-id="<?= $e($quoteId) ?>" data-post-id="<?= $e($quoteId) ?>">
  <p class="quote-card-header">
    <a class="quote-card-permalink" href="<?= $e($permalinkHref) ?>">#<?= $e($displayNumber) ?></a>
    <span class="meta quote-card-score" data-role="thread-score" data-score-format="bare-ratio">(<span class="<?= $e($scoreValueClass) ?>" data-role="thread-score-value"><?= $scoreTotal ?></span>/<span data-role="thread-vote-count"><?= $voteCount ?></span>)</span>
<?= $partial('partials/qdb_quote_actions.php', [
    'quotePostId' => $quoteId,
    'upvote' => $upvote,
    'downvote' => $downvote,
    'qdbVoteTags' => $qdbVoteTags,
    'viewerHasVoted' => $viewerHasVoted,
    'viewerHasFlagged' => $viewerHasFlagged,
]) ?>
<?php if (!empty($qdbModerationActors)): ?>
    <span class="quote-card-header-actions" data-qdb-moderation-actions>
      <button type="button" class="thread-reaction-button quote-card-vote-button" data-action="apply-thread-tag" data-tag="approved" data-applied-label="✓ Approved">Approve</button>
      <button type="button" class="thread-reaction-button quote-card-vote-button" data-action="apply-thread-tag" data-tag="rejected" data-applied-label="Rejected">Reject</button>
    </span>
<?php endif; ?>
  </p>
  <p class="quote-card-body"><?= $br($plainText($thread, (string) $thread['root_post_body'], 'root_post_body_format')) ?></p>
</article>
