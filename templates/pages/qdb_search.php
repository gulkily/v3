<section class="stack">
  <article class="card">
    <form method="get" action="/search" class="stack">
      <input type="text" name="search" value="<?= $e($term) ?>" placeholder="Search quotes..." aria-label="Search quotes">
      <button type="submit">Search</button>
    </form>
<?php if ($term !== ''): ?>
    <p class="meta"><?= count($threads) ?> <?= count($threads) === 1 ? 'result' : 'results' ?> for &quot;<?= $e($term) ?>&quot;</p>
<?php endif; ?>
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
