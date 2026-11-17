<section class="stack" data-private-site-auth-state data-authenticated-identity-id="" data-auth-return-to="<?= $e($returnTo) ?>">
  <article class="card">
    <h1 class="requires-script">Reconnecting</h1>
    <h1 class="without-script">Members only</h1>
    <p class="requires-script">Verifying this browser identity before returning you to the page you requested.</p>
    <p class="meta" data-role="private-site-auth-status" hidden></p>
    <p class="meta"><span class="requires-script">If this does not complete automatically, check</span><span class="without-script">Check</span> <a href="/account/key/?return_to=<?= $e(rawurlencode($returnTo)) ?>">your browser key</a> or <a href="/lobby/?return_to=<?= $e(rawurlencode($returnTo)) ?>">go to the lobby</a>.</p>
  </article>
</section>
