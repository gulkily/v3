<section class="stack private-message-access" data-private-message-access data-access-state="checking" data-viewer-identity-id="<?= $e($viewerIdentityId) ?>" data-viewer-approved="0">
  <article class="card">
    <h1>Messages are for approved members</h1>
    <div data-access-for="guest">
      <p>Set up an account with a name. Messages open once a member approves you.</p>
      <form method="get" action="/account/key/"><button type="submit">Set up account</button></form>
    </div>
    <p data-access-for="waiting">You’re waiting for approval as <strong data-role="access-name"></strong>. Messages unlock once someone approves you.</p>
    <p class="message-list-alert" data-access-for="failed" role="alert">Couldn’t sign you in. <button type="button" data-action="access-retry">Retry</button></p>
  </article>
</section>
<?= $partial('partials/account_state_script.php') ?>
<script>
  // Decided before paint: a guest or a waiting key sees its next step; a registered key whose
  // approval this page can't know shows only the heading while private_message_access.js signs it in.
  (function () {
    var root = document.querySelector('[data-private-message-access]');
    var state = window.ForumAccountState.accountState(root);
    root.querySelector('[data-role="access-name"]').textContent = window.ForumAccountState.readStorageValue('forum_pki_username') || 'guest';
    root.dataset.accessState = state === 'guest' || state === 'waiting' ? state : 'checking';
  })();
</script>
