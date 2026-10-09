<section class="stack">
  <article class="card">
    <form method="get" action="/search" class="stack">
      <input type="text" name="search" value="<?= $e($term) ?>" placeholder="Search quotes..." aria-label="Search quotes">
      <button type="submit">Search</button>
    </form>
<?php if ($term !== ''): ?>
    <p class="meta"><?= $resultCount ?> <?= $resultCount === 1 ? 'result' : 'results' ?> for &quot;<?= $e($term) ?>&quot;</p>
<?php endif; ?>
  </article>
<?php if (!empty($pagination)): ?>
<?= $indent($partial('partials/board_pagination_nav.php', ['pagination' => $pagination]), 0) ?>
<?php endif; ?>
<?php foreach ($threads as $thread): ?>
<?= $indent($partial('partials/quote_card.php', [
    'thread' => $thread,
    'viewerUpvotedThreadIds' => $viewerUpvotedThreadIds ?? [],
    'viewerDownvotedThreadIds' => $viewerDownvotedThreadIds ?? [],
    'viewerFlaggedPostIds' => $viewerFlaggedPostIds ?? [],
    'voteCaptionPair' => $voteCaptionPair ?? null,
]), 1) ?>
<?php endforeach; ?>
<?php if (!empty($pagination)): ?>
<?= $indent($partial('partials/board_pagination_nav.php', ['pagination' => $pagination]), 0) ?>
<?php endif; ?>
</section>
