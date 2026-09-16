<dialog class="paned-new-thread-dialog paned-profile-summary-dialog" data-paned-profile-summary-dialog>
  <div class="paned-dialog-titlebar">
    <span data-role="profile-summary-title">Profile</span>
    <button type="button" class="paned-dialog-close" data-paned-profile-summary-close aria-label="Close">
      <svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M1 1 L9 9 M9 1 L1 9" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
    </button>
  </div>
  <div class="paned-standalone-body">
    <p data-role="profile-summary-loading">Loading…</p>
    <div data-role="profile-summary-content" hidden>
      <p><strong>Approved:</strong> <span data-role="profile-summary-approved"></span></p>
      <p data-role="profile-summary-approved-by-row" hidden><strong>Approved by:</strong> <span data-role="profile-summary-approved-by"></span></p>
      <p><strong>Threads:</strong> <span data-role="profile-summary-threads"></span></p>
      <p><strong>Posts:</strong> <span data-role="profile-summary-posts"></span></p>
      <p class="paned-standalone-back"><a data-role="profile-summary-full-link" href="#">View full profile</a></p>
    </div>
    <p data-role="profile-summary-error" hidden>Unable to load profile.</p>
  </div>
</dialog>
