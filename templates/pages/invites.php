<section class="stack" data-invitation-page>
  <article class="card">
    <h1>Generate invite</h1>
    <p>Create a one-time invitation for a new auto-approved identity.</p>
  </article>
  <article class="card">
    <form data-invitation-issue-form>
      <label class="account-key-label" for="invite-destination">Destination (optional)</label>
      <input id="invite-destination" name="destination" type="text" value="<?= $e($destination) ?>" placeholder="/threads/example">
      <p class="meta">The recipient reaches this internal page only after key authentication.</p>
      <button type="submit">Generate invite link</button>
    </form>
    <p class="meta" data-role="invitation-feedback" hidden></p>
    <div data-role="invitation-result" hidden>
      <label class="account-key-label" for="invite-link">Invite link</label>
      <div class="button-row button-row-split">
        <textarea id="invite-link" readonly rows="3" data-role="invitation-link"></textarea>
        <button type="button" data-action="copy-invitation-link">Copy</button>
      </div>
      <p class="meta" data-role="invitation-hash"></p>
    </div>
  </article>
  <article class="card">
    <h2>Revoke invite</h2>
    <form data-invitation-revoke-form>
      <label class="account-key-label" for="revoke-invitation-id">Invitation ID</label>
      <input id="revoke-invitation-id" name="invitation_id" type="text" required>
      <label class="account-key-label" for="revoke-verification-hash">Verification hash</label>
      <input id="revoke-verification-hash" name="verification_hash" type="text" required>
      <button type="submit">Revoke invite</button>
    </form>
  </article>
</section>
