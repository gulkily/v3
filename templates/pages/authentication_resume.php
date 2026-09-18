<section class="stack" data-private-site-auth-state data-authenticated-identity-id="" data-auth-return-to="<?= $e($returnTo) ?>">
  <article class="card">
    <h1>Reconnecting</h1>
    <p>Verifying this browser identity before returning you to the page you requested.</p>
    <p class="meta" data-role="private-site-auth-status" hidden></p>
    <p class="meta">If this does not complete automatically, <a href="/account/key/?return_to=<?= $e(rawurlencode($returnTo)) ?>">check your browser key</a> or <a href="/lobby/?return_to=<?= $e(rawurlencode($returnTo)) ?>">go to the lobby</a>.</p>
  </article>
</section>
