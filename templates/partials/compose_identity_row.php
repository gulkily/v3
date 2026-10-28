<p class="meta compose-identity" data-compose-identity hidden><span data-role="compose-identity-view">Post as <span class="compose-identity__name" data-role="compose-identity-name">guest</span><span data-role="compose-identity-change"> · <button type="button" class="compose-identity__change" data-action="change-compose-name">Change</button></span></span><span class="compose-identity__editor" data-role="compose-identity-editor" hidden><input type="text" aria-label="Name" data-role="compose-identity-input" autocomplete="nickname" autocapitalize="none" spellcheck="false" maxlength="64"><button type="button" data-action="apply-compose-name">Done</button></span><span class="compose-identity__error" data-role="compose-identity-error" role="alert" hidden></span></p>
<script>
  // Set before first paint from the name saved in this browser; without JavaScript the row stays hidden.
  // Change edits the name in place; Done applies it through the signing tools, leaving the draft alone.
  (function (row) {
    var view = row.querySelector('[data-role="compose-identity-view"]');
    var editor = row.querySelector('[data-role="compose-identity-editor"]');
    var input = row.querySelector('[data-role="compose-identity-input"]');
    var done = row.querySelector('[data-action="apply-compose-name"]');
    var change = row.querySelector('[data-action="change-compose-name"]');
    var error = row.querySelector('[data-role="compose-identity-error"]');
    function stored(key) {
      try { return window.localStorage.getItem(key) || ''; } catch (e) { return ''; }
    }
    function sync() {
      var fingerprint = stored('forum_pki_fingerprint').trim().toUpperCase();
      var registered = fingerprint !== '' && stored('forum_pki_published_fingerprint').trim().toUpperCase() === fingerprint;
      row.querySelector('[data-role="compose-identity-name"]').textContent = stored('forum_pki_username') || 'guest';
      // A registered name changes only where the site allows renaming; the divider goes with Change.
      row.querySelector('[data-role="compose-identity-change"]').hidden = registered && !(window.forumBrowserIdentityOptions || {}).usernameChangesEnabled;
    }
    function edit(open) {
      view.hidden = open;
      editor.hidden = !open;
      error.hidden = true;
      if (open) {
        input.value = stored('forum_pki_username') || '';
        input.focus();
        input.select();
      } else {
        change.focus();
      }
    }
    function apply() {
      var signing = window.ForumBrowserSigning;
      var lazy = window.ForumLazyComposeSigning;
      done.disabled = true;
      error.hidden = true;
      (signing && signing.changeIdentityName ? Promise.resolve() : lazy ? lazy.load() : Promise.reject(new Error('Reload the page and try again.')))
        .then(function () { return window.ForumBrowserSigning.changeIdentityName(input.value); })
        .then(function () { edit(false); }, function (failure) {
          error.textContent = failure && failure.message ? failure.message : 'Couldn’t change your name.';
          error.hidden = false;
        })
        .then(function () { done.disabled = false; });
    }
    sync();
    row.hidden = false;
    change.addEventListener('click', function () { edit(true); });
    done.addEventListener('click', apply);
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') { event.preventDefault(); apply(); }
      if (event.key === 'Escape') { event.preventDefault(); edit(false); }
    });
    window.addEventListener('forum:identity-changed', sync);
    window.addEventListener('storage', sync);
  })(document.currentScript.previousElementSibling);
</script>
