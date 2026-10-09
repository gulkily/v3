<?php
$quotePostId = (string) ($quotePostId ?? '');
$upvote = is_array($upvote ?? null) ? $upvote : ['tag' => 'upvote', 'label' => '+'];
$downvote = is_array($downvote ?? null) ? $downvote : ['tag' => 'downvote', 'label' => '-'];
$qdbVoteTags = is_array($qdbVoteTags ?? null) ? $qdbVoteTags : ['upvote', 'downvote'];
$viewerHasVoted = (bool) ($viewerHasVoted ?? false);
$viewerHasFlagged = (bool) ($viewerHasFlagged ?? false);
?>
<span class="quote-card-header-actions" data-qdb-vote-pair data-qdb-vote-tags="<?= $e(json_encode($qdbVoteTags, JSON_THROW_ON_ERROR)) ?>">
  <button
    type="button"
    class="thread-reaction-button quote-card-vote-button"
    data-action="apply-thread-tag"
    data-tag="<?= $e($upvote['tag']) ?>"
    data-applied-label="<?= $e($upvote['label']) ?>"
    aria-label="Upvote this quote: <?= $e($upvote['label']) ?>"
    aria-pressed="<?= $viewerHasVoted ? 'true' : 'false' ?>"
<?= $viewerHasVoted ? ' disabled="disabled"' : '' ?>
  >↑ <?= $e($upvote['label']) ?></button>
  <button
    type="button"
    class="thread-reaction-button quote-card-vote-button"
    data-action="apply-thread-tag"
    data-tag="<?= $e($downvote['tag']) ?>"
    data-applied-label="<?= $e($downvote['label']) ?>"
    aria-label="Downvote this quote: <?= $e($downvote['label']) ?>"
    aria-pressed="<?= $viewerHasVoted ? 'true' : 'false' ?>"
<?= $viewerHasVoted ? ' disabled="disabled"' : '' ?>
  >↓ <?= $e($downvote['label']) ?></button>
  <button
    type="button"
    class="thread-reaction-button quote-card-vote-button"
    data-action="apply-post-tag"
    data-post-id="<?= $e($quotePostId) ?>"
    data-tag="flag"
    data-applied-label="⚑ Flagged"
    aria-label="Flag this quote for review"
    aria-pressed="<?= $viewerHasFlagged ? 'true' : 'false' ?>"
<?= $viewerHasFlagged ? ' disabled="disabled"' : '' ?>
  >⚑ Flag</button>
  <span class="meta quote-card-reaction-feedback" data-role="qdb-reaction-feedback" aria-live="polite" hidden></span>
</span>
