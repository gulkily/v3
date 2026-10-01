<?php
$quoteId = (string) $thread['root_post_id'];
$viewerHasUpvoted = isset($viewerUpvotedThreadIds[$quoteId]);
$viewerHasDownvoted = isset($viewerDownvotedThreadIds[$quoteId]);
$viewerHasFlagged = isset($viewerFlaggedPostIds[$quoteId]);
$scoreTotal = (int) ($thread['score_total'] ?? 0);
$scoreSignClass = $scoreTotal > 0 ? 'quote-card-score-positive' : ($scoreTotal < 0 ? 'quote-card-score-negative' : '');
?>
<article class="card post-card quote-card" data-thread-reactions-root data-thread-id="<?= $e($quoteId) ?>" data-post-id="<?= $e($quoteId) ?>">
  <p class="quote-card-header">
    <a class="quote-card-permalink" href="/threads/<?= $e($quoteId) ?>">#<?= $e($quoteId) ?></a>
    <span class="meta quote-card-score <?= $e($scoreSignClass) ?>" data-role="thread-score" data-score-format="bare">(<?= $scoreTotal ?>)</span>
  </p>
  <p class="quote-card-body"><?= $br($thread['root_post_body']) ?></p>
  <div class="button-row button-row-natural quote-card-actions">
    <button
      type="button"
      class="thread-reaction-button"
      data-action="apply-thread-tag"
      data-tag="upvote"
      data-applied-label="Upvoted"
      aria-pressed="<?= $viewerHasUpvoted ? 'true' : 'false' ?>"
<?= $viewerHasUpvoted ? ' disabled="disabled"' : '' ?>
    ><?= $viewerHasUpvoted ? 'Upvoted' : 'Upvote' ?></button>
    <button
      type="button"
      class="thread-reaction-button"
      data-action="apply-thread-tag"
      data-tag="downvote"
      data-applied-label="Downvoted"
      aria-pressed="<?= $viewerHasDownvoted ? 'true' : 'false' ?>"
<?= $viewerHasDownvoted ? ' disabled="disabled"' : '' ?>
    ><?= $viewerHasDownvoted ? 'Downvoted' : 'Downvote' ?></button>
    <button
      type="button"
      class="thread-reaction-button"
      data-action="apply-post-tag"
      data-post-id="<?= $e($quoteId) ?>"
      data-tag="flag"
      data-applied-label="Flagged"
      aria-pressed="<?= $viewerHasFlagged ? 'true' : 'false' ?>"
<?= $viewerHasFlagged ? ' disabled="disabled"' : '' ?>
    ><?= $viewerHasFlagged ? 'Flagged' : 'Flag' ?></button>
    <p class="meta thread-reaction-feedback" data-role="thread-reaction-feedback" hidden></p>
    <p class="meta thread-reaction-feedback" data-role="post-reaction-feedback" hidden></p>
  </div>
</article>
