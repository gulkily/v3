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
?>
<article class="card post-card quote-card" data-thread-reactions-root data-thread-id="<?= $e($quoteId) ?>" data-post-id="<?= $e($quoteId) ?>">
  <p class="quote-card-header">
    <a class="quote-card-permalink" href="<?= $e($permalinkHref) ?>">#<?= $e($displayNumber) ?></a>
    <span class="meta quote-card-score <?= $e($scoreSignClass) ?>" data-role="thread-score" data-score-format="bare-ratio">(<?= $scoreTotal ?>/<?= $voteCount ?>)</span>
  </p>
  <p class="quote-card-body"><?= $br($thread['root_post_body']) ?></p>
  <div class="button-row button-row-natural quote-card-actions">
    <button
      type="button"
      class="thread-reaction-button quote-card-vote-button"
      data-action="apply-thread-tag"
      data-tag="<?= $e($upvote['tag']) ?>"
      data-applied-label="<?= $e($upvote['label']) ?>"
      aria-label="Upvote this quote: <?= $e($upvote['label']) ?>"
      aria-pressed="<?= $viewerHasUpvoted ? 'true' : 'false' ?>"
<?= $viewerHasUpvoted ? ' disabled="disabled"' : '' ?>
    >↑ <?= $e($upvote['label']) ?></button>
    <button
      type="button"
      class="thread-reaction-button quote-card-vote-button"
      data-action="apply-thread-tag"
      data-tag="<?= $e($downvote['tag']) ?>"
      data-applied-label="<?= $e($downvote['label']) ?>"
      aria-label="Downvote this quote: <?= $e($downvote['label']) ?>"
      aria-pressed="<?= $viewerHasDownvoted ? 'true' : 'false' ?>"
<?= $viewerHasDownvoted ? ' disabled="disabled"' : '' ?>
    >↓ <?= $e($downvote['label']) ?></button>
    <button
      type="button"
      class="thread-reaction-button quote-card-vote-button"
      data-action="apply-post-tag"
      data-post-id="<?= $e($quoteId) ?>"
      data-tag="flag"
      data-applied-label="[X]"
      aria-label="Flag this quote for review"
      aria-pressed="<?= $viewerHasFlagged ? 'true' : 'false' ?>"
<?= $viewerHasFlagged ? ' disabled="disabled"' : '' ?>
    >[X]</button>
    <p class="meta thread-reaction-feedback" data-role="thread-reaction-feedback" hidden></p>
    <p class="meta thread-reaction-feedback" data-role="post-reaction-feedback" hidden></p>
  </div>
</article>
