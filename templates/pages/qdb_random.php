<section class="stack">
<?php foreach ($threads as $thread): ?>
<?= $indent($partial('partials/quote_card.php', [
    'thread' => $thread,
    'viewerUpvotedThreadIds' => $viewerUpvotedThreadIds ?? [],
    'viewerDownvotedThreadIds' => $viewerDownvotedThreadIds ?? [],
    'viewerFlaggedPostIds' => $viewerFlaggedPostIds ?? [],
]), 1) ?>
<?php endforeach; ?>
</section>
