<?php if ($canSignIn): ?>
<div data-private-site-auth-state data-public-auth-resume="true" data-authenticated-identity-id="" data-auth-return-to="<?= $e($authReturnTo) ?>">
  <p>Sign in with your operator account to view access history.</p>
  <div class="button-row button-row-natural">
    <button type="button" data-access-history-sign-in>Sign in</button>
    <a href="/account/key/">Restore or choose an account</a>
  </div>
  <p class="meta" data-role="private-site-auth-status" role="status" hidden></p>
</div>
<?php else: ?>
<p>Authenticated root-approved operator access required.</p>
<?php endif; ?>
