<?php
$quoteId = (string) $thread['root_post_id'];
// Imported qdb archive quotes carry their original qdb.us number in their
// Post-ID (thread-<timestamp>-qdb-<quote_id>) so it survives independent of
// the internal record ID; live-authored quotes have no such suffix and
// display their Post-ID unchanged, exactly as before.
$displayNumber = preg_match('/-qdb-(\d+)$/', $quoteId, $quoteNumberMatch) === 1
    ? $quoteNumberMatch[1]
    : $quoteId;
$viewerHasUpvoted = isset($viewerUpvotedThreadIds[$quoteId]);
$viewerHasDownvoted = isset($viewerDownvotedThreadIds[$quoteId]);
$viewerHasFlagged = isset($viewerFlaggedPostIds[$quoteId]);
$scoreTotal = (int) ($thread['score_total'] ?? 0);
$voteCount = (int) ($thread['vote_count'] ?? 0);
$scoreSignClass = $scoreTotal > 0 ? 'quote-card-score-positive' : ($scoreTotal < 0 ? 'quote-card-score-negative' : '');
?>
<article class="card post-card quote-card" data-thread-reactions-root data-thread-id="<?= $e($quoteId) ?>" data-post-id="<?= $e($quoteId) ?>">
  <p class="quote-card-header">
    <a class="quote-card-permalink" href="/threads/<?= $e($quoteId) ?>">#<?= $e($displayNumber) ?></a>
    <span class="meta quote-card-score <?= $e($scoreSignClass) ?>" data-role="thread-score" data-score-format="bare-ratio">(<?= $scoreTotal ?>/<?= $voteCount ?>)</span>
  </p>
  <p class="quote-card-body"><?= $br($thread['root_post_body']) ?></p>
  <div class="button-row button-row-natural quote-card-actions">
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
