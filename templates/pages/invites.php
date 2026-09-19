<section class="stack" data-invitation-page>
  <article class="card">
    <h1>Generate invite</h1>
  </article>
  <article class="card">
    <form data-invitation-issue-form>
      <label class="invitation-destination-toggle">
        <input name="include_destination" type="checkbox" data-action="toggle-invitation-destination">
        Include destination URL
      </label>
      <label class="account-key-label" for="invite-destination">Destination (optional)</label>
      <input id="invite-destination" name="destination" type="text" value="<?= $e($destination) ?>" placeholder="/threads/example" disabled>
      <button type="submit">Generate invite link</button>
    </form>
    <p class="meta" data-role="invitation-feedback" hidden></p>
    <div data-role="invitation-result" hidden>
      <div class="button-row button-row-split">
        <textarea id="invite-link" readonly rows="3" data-role="invitation-link"></textarea>
        <button type="button" data-action="copy-invitation-link">Copy</button>
      </div>
    </div>
  </article>
  <article class="card">
    <details>
      <summary>Revoke invite</summary>
      <form data-invitation-revoke-form>
        <label class="account-key-label" for="revoke-invitation-id">Invitation ID</label>
        <input id="revoke-invitation-id" name="invitation_id" type="text" required>
        <label class="account-key-label" for="revoke-verification-hash">Verification hash</label>
        <input id="revoke-verification-hash" name="verification_hash" type="text" required>
        <button type="submit">Revoke invite</button>
      </form>
    </details>
  </article>
</section>
