<section class="stack">
  <article class="card">
    <h1>Welcome!</h1>
    <p>Browse away to your amusement, and feel free to add some quotes
    yourself.</p>
    <p class="meta"><?= (int) $qdbQuoteCount ?> <?= (int) $qdbQuoteCount === 1 ? 'quote' : 'quotes' ?> so far.</p>
    <p>
      <a href="/latest">Browse the latest quotes</a> &middot;
      <a href="/random">See some random ones</a> &middot;
      <a href="/add">Add your own</a> &middot;
      <a href="/search">Search</a>
    </p>
  </article>
</section>
