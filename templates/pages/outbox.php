<section class="stack" data-outbox>
  <article class="card">
<?= $indent($partial('partials/tools_nav.php'), 2) ?>
  </article>
  <article class="card">
    <h1>Outbox</h1>
    <p class="meta" data-role="outbox-status" aria-live="polite">Loading local work…</p>
    <div class="stack" data-role="outbox-items"></div>
  </article>
</section>
