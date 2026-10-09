<section class="stack">
  <article class="card qdb-welcome">
    <div class="qdb-welcome-columns">
      <div class="qdb-welcome-intro">
        <p><strong>Welcome!</strong> Browse away to your amusement, and
        feel free to add some quotes yourself.</p>
        <p class="instructions qdb-welcome-instructions">Use the vote
        buttons on any quote to vote, and flag anything that
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
<?php foreach ($newsThreads as $thread): ?>
        <div class="qdb-news-item">
          <span class="qdb-news-date"><?= $e(substr((string) $thread['root_post_created_at'], 0, 10)) ?></span>
<?php if (trim((string) ($thread['subject'] ?? '')) !== ''): ?>
          <strong class="qdb-news-title"><?= $e(trim((string) $thread['subject'])) ?></strong>
<?php endif; ?>
          <span class="qdb-news-body"><?= $br($thread['root_post_body']) ?></span>
        </div>
<?php endforeach; ?>
<?php if ($hasMoreNews): ?>
        <p class="meta"><a href="/tags/news">All news</a></p>
<?php endif; ?>
<?php endif; ?>
      </div>
    </div>
  </article>
</section>
