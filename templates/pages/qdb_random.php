<section class="stack">
<?php foreach ($threads as $thread): ?>
<?= $indent($partial('partials/quote_card.php', [
    'thread' => $thread,
    'viewerUpvotedThreadIds' => $viewerUpvotedThreadIds ?? [],
    'viewerDownvotedThreadIds' => $viewerDownvotedThreadIds ?? [],
    'viewerFlaggedPostIds' => $viewerFlaggedPostIds ?? [],
    'voteCaptionPair' => $voteCaptionPair ?? null,
]), 1) ?>
<?php endforeach; ?>
</section>
