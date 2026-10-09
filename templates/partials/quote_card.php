<?php
$quoteId = (string) $thread['root_post_id'];
$permalink = \ForumRewrite\Qdb\QdbQuoteNumbers::displayPermalink($quoteId);
$displayNumber = $permalink['displayNumber'];
$permalinkHref = $permalink['permalinkHref'];
$viewerHasUpvoted = isset($viewerUpvotedThreadIds[$quoteId]);
$viewerHasDownvoted = isset($viewerDownvotedThreadIds[$quoteId]);
$viewerHasFlagged = isset($viewerFlaggedPostIds[$quoteId]);
$scoreTotal = (int) ($thread['score_total'] ?? 0);
$voteCount = (int) ($thread['vote_count'] ?? 0);
$scoreSignClass = $scoreTotal > 0 ? 'quote-card-score-positive' : ($scoreTotal < 0 ? 'quote-card-score-negative' : '');
$voteCaptionPair = is_array($voteCaptionPair ?? null) ? $voteCaptionPair : null;
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
    'viewerHasUpvoted' => $viewerHasUpvoted,
    'viewerHasDownvoted' => $viewerHasDownvoted,
    'viewerHasFlagged' => $viewerHasFlagged,
]) ?>
  </p>
  <p class="quote-card-body"><?= $br($thread['root_post_body']) ?></p>
</article>
