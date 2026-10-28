<p class="meta compose-identity" data-compose-identity hidden>Post as <span class="compose-identity__name" data-role="compose-identity-name">guest</span> · <button type="button" class="compose-identity__change" data-action="change-compose-name">Change</button></p>
<script>
  // Set before first paint from the name saved in this browser; without JavaScript the row stays hidden.
  (function (row) {
    function sync() {
      var name = '';
      try { name = window.localStorage.getItem('forum_pki_username') || ''; } catch (error) {}
      row.querySelector('[data-role="compose-identity-name"]').textContent = name || 'guest';
    }
    sync();
    row.hidden = false;
    window.addEventListener('forum:identity-changed', sync);
    window.addEventListener('storage', sync);
  })(document.currentScript.previousElementSibling);
</script>
