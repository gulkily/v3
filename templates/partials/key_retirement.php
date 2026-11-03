<?php if (!empty($profile['retired_at'])): ?>
    <p>This key is permanently retired. Past posts and signatures remain.</p>
<?php elseif (!empty($retirementActors)): ?>
    <style>[data-key-retirement] { display: none; } html[data-key-retirement-eligible] [data-key-retirement] { display: block; }</style>
    <script>
      (function () {
        var actors = <?= json_encode($retirementActors, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
        function update() {
          var eligible = false;
          try {
            var fingerprint = (localStorage.getItem('forum_pki_fingerprint') || '').trim().toLowerCase();
            eligible = actors.indexOf(fingerprint) !== -1 && !!localStorage.getItem('forum_pki_private_key') && !!localStorage.getItem('forum_pki_public_key');
          } catch (error) {}
          document.documentElement.toggleAttribute('data-key-retirement-eligible', eligible);
        }
        update();
        window.addEventListener('forum:identity-changed', update);
        window.addEventListener('storage', update);
      })();
    </script>
    <div data-key-retirement data-target-identity-id="<?= $e($profile['identity_id']) ?>">
      <button type="button" data-retirement-review>Retire this key…</button>
      <div data-retirement-confirm hidden>
        <p data-retirement-warning></p>
        <p>Key: <code data-retirement-target style="overflow-wrap:anywhere"></code></p>
        <p>Replacement: <code data-retirement-replacement style="overflow-wrap:anywhere"></code></p>
        <button type="button" data-retirement-submit>Retire permanently</button>
        <button type="button" data-retirement-cancel>Cancel</button>
      </div>
      <p data-retirement-feedback role="status" hidden></p>
    </div>
<?php endif; ?>
