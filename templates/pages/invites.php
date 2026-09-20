<section class="stack" data-invitation-page>
  <article class="card">
    <h1>Generate invite</h1>
  </article>
  <article class="card">
    <form data-invitation-issue-form>
      <label class="invitation-destination-toggle">
        <input name="include_destination" type="checkbox" data-action="toggle-invitation-destination" aria-controls="invite-destination-fields" aria-expanded="false">
        Include destination URL
      </label>
      <div id="invite-destination-fields" data-role="invitation-destination-fields" hidden>
        <label class="account-key-label" for="invite-destination">Destination (optional)</label>
        <div class="invitation-destination-input">
          <input id="invite-destination" name="destination" type="text" value="" data-source-destination="<?= $e($destination) ?>" placeholder="/threads/example" autocomplete="off" disabled>
          <details class="invitation-destination-menu" data-role="invitation-destination-menu">
            <summary>Choose destination</summary>
            <div class="button-row button-row-natural invitation-destination-options" data-role="invitation-destination-options">
              <button type="button" data-destination-value="/">Board</button>
              <button type="button" data-destination-value="/activity/">Activity</button>
              <button type="button" data-destination-value="/users/">Users</button>
              <button type="button" data-destination-value="/tools/">Tools</button>
            </div>
          </details>
        </div>
      </div>
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
