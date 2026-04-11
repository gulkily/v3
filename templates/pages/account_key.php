<section class="stack" data-account-key-root>
  <article class="card">
    <h1>Account Key</h1>
<?= $indent($partial('partials/feedback.php', ['notice' => $notice, 'error' => $error]), 2) ?>
    <p>Generate or import a browser-held OpenPGP keypair, then submit the public key to bootstrap an identity.</p>
    <p><strong>Identity hint cookie:</strong> <?= $e($identityHint !== '' ? $identityHint : 'none') ?></p>
    <div class="stack">
      <p class="meta" data-role="browser-key-status">Ready.</p>
      <div class="button-row">
        <button type="button" data-action="generate-browser-key">Generate Browser Key</button>
        <button type="button" data-action="load-browser-key">Load Saved Public Key</button>
        <button type="button" data-action="copy-public-key">Copy Public Key</button>
        <button type="button" data-action="copy-private-key">Copy Private Key</button>
        <button type="button" data-action="clear-browser-key">Clear Saved Keypair</button>
        <button type="button" data-action="undo-clear-browser-key" hidden>Undo Clear</button>
      </div>
      <p><strong>Saved browser username:</strong> <span data-role="username-field">guest</span></p>
      <p class="meta">
        <strong>Saved browser identity:</strong>
        <span data-role="identity-id-field">none</span>
        <span data-role="profile-link-wrap" hidden>
          · <a data-role="profile-link" href="/account/key/">Open profile</a>
        </span>
      </p>
    </div>
    <form method="post" class="stack">
      <label>Public key<textarea name="public_key" rows="10" placeholder="-----BEGIN PGP PUBLIC KEY BLOCK-----" data-role="public-key-field"></textarea></label>
      <button type="submit">Create account</button>
    </form>
  </article>
  <article class="card">
    <h2>Browser Key Material</h2>
    <p class="meta">The private key stays in browser local storage unless you copy it out yourself.</p>
    <label>Saved public key
      <pre data-role="public-key-viewer">
No browser public key saved yet.
      </pre>
    </label>
    <label class="key-material-gap">Saved private key
      <pre data-role="private-key-viewer">
No browser private key saved yet.
      </pre>
    </label>
  </article>
</section>
