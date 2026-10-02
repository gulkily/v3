<section class="stack">
  <article class="card">
    <h1>Random</h1>
    <p class="meta"><a href="/random">Shuffle again</a></p>
  </article>
<?php foreach ($threads as $thread): ?>
<?= $indent($partial('partials/quote_card.php', [
    'thread' => $thread,
    'viewerUpvotedThreadIds' => $viewerUpvotedThreadIds ?? [],
    'viewerDownvotedThreadIds' => $viewerDownvotedThreadIds ?? [],
    'viewerFlaggedPostIds' => $viewerFlaggedPostIds ?? [],
]), 1) ?>
<?php endforeach; ?>
</section>
