<?php if (!empty($operatorGrantActors)): ?>
    <style>[data-operator-grant] { display: none; } html[data-operator-grant-eligible] [data-operator-grant] { display: block; }</style>
    <script>
      (function () {
        var actors = <?= json_encode($operatorGrantActors, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
        function update() {
          var eligible = false;
          try {
            var fingerprint = (localStorage.getItem('forum_pki_fingerprint') || '').trim().toLowerCase();
            eligible = actors.indexOf(fingerprint) !== -1 && !!localStorage.getItem('forum_pki_private_key') && !!localStorage.getItem('forum_pki_public_key');
          } catch (error) {}
          document.documentElement.toggleAttribute('data-operator-grant-eligible', eligible);
        }
        update();
        window.addEventListener('forum:identity-changed', update);
        window.addEventListener('storage', update);
      })();
    </script>
    <div data-operator-grant data-target-identity-id="<?= $e($profile['identity_id']) ?>">
      <button type="button" data-operator-grant-review>Make root…</button>
      <div data-operator-grant-confirm hidden>
        <p data-operator-grant-question></p>
        <button type="button" data-operator-grant-submit>Make root</button>
        <button type="button" data-operator-grant-cancel>Cancel</button>
      </div>
      <p data-operator-grant-feedback role="status" hidden></p>
    </div>
<?php endif; ?>
