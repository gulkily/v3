<section class="stack">
  <article class="card qdb-welcome">
    <div class="qdb-welcome-columns">
      <div class="qdb-welcome-intro">
        <p><strong>Welcome!</strong> Browse away to your amusement, and
        feel free to add some quotes yourself.</p>
        <p class="instructions qdb-welcome-instructions">Use the + / -
        buttons on any quote to vote, and [X] to flag something that
        doesn't belong.</p>
        <ul>
          <li><a href="/latest">Latest quotes</a></li>
          <li><a href="/random">Random quotes</a></li>
          <li><a href="/search">Search</a></li>
        </ul>
        <p class="meta"><?= (int) $qdbQuoteCount ?> <?= (int) $qdbQuoteCount === 1 ? 'quote' : 'quotes' ?> so far.</p>
      </div>
      <div class="qdb-welcome-divider"></div>
      <div class="qdb-welcome-recent">
        <h2>Site News</h2>
<?php if ($newsThreads === []): ?>
        <p class="meta">No site news yet.</p>
<?php else: ?>
        <ul>
<?php foreach ($newsThreads as $thread): ?>
          <li><a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($threadTitle($thread)) ?></a> <span class="meta"><?= $contentMeta($thread, 'root_post_created_at', '') ?></span></li>
<?php endforeach; ?>
        </ul>
<?php if ($hasMoreNews): ?>
        <p class="meta"><a href="/tags/news">All news</a></p>
<?php endif; ?>
<?php endif; ?>
      </div>
    </div>
  </article>
</section>
