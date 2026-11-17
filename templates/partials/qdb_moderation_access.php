<?php if (!empty($qdbModerationActors)): ?>
  <style>html:not([data-qdb-moderator]) [data-qdb-moderation-actions] { display: none !important; }</style>
  <script>
    (function () {
      var actors = <?= json_encode(array_values($qdbModerationActors), JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
      function update() {
        var moderator = false;
        try {
          var fingerprint = (localStorage.getItem('forum_pki_fingerprint') || '').trim().toLowerCase();
          moderator = actors.indexOf(fingerprint) !== -1 && !!localStorage.getItem('forum_pki_private_key');
        } catch (error) {}
        document.documentElement.toggleAttribute('data-qdb-moderator', moderator);
      }
      update();
      window.addEventListener('forum:identity-changed', update);
      window.addEventListener('storage', update);
    })();
  </script>
<?php endif; ?>
