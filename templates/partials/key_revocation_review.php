<article class="card stack key-revocation-review" data-key-revocation-review hidden>
  <h2 tabindex="-1" data-revocation-heading>Revoke account key?</h2>
  <dl class="key-revocation-facts">
    <dt>Account</dt><dd data-revocation-account></dd>
    <dt>Account key</dt><dd data-revocation-key></dd>
    <dt>Revocation cutoff</dt><dd data-revocation-cutoff></dd>
  </dl>
  <div class="key-revocation-warning">
    <p>This key will permanently lose account access. Existing sessions will lose access on their next protected request.</p>
    <p>This key cannot be approved again. Previously downloaded information cannot be removed.</p>
    <p data-revocation-last-key hidden><strong>This is the account’s last approved key.</strong> An operator will need to approve a replacement key. The username stays reserved.</p>
  </div>
  <p data-revocation-feedback role="status" hidden></p>
  <div class="button-row button-row-natural">
    <button type="button" data-revocation-cancel>Cancel</button>
    <button type="button" class="key-revocation-danger" data-revocation-confirm>Sign &amp; revoke key</button>
    <a href="" data-revocation-reload hidden>Reload account</a>
  </div>
</article>
