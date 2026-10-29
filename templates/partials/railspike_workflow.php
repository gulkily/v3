<?php if ($railspikeEnabled && !empty($railspikeOperators) && \ForumRewrite\Railspike\WorkflowService::eligible($post, $railspikeThread ?? [])): ?>
<details data-railspike-shell hidden style="flex-basis:100%;min-width:0;width:100%">
  <summary>Development</summary>
  <div data-railspike-post="<?= $e((string)$post['post_id']) ?>" data-railspike-thread="<?= $e((string)$post['thread_id']) ?>"></div>
</details>
<script>
(function (script) {
  try {
    var fingerprints = <?= json_encode($railspikeOperators, JSON_HEX_TAG | JSON_THROW_ON_ERROR) ?>;
    var fingerprint = (localStorage.getItem('forum_pki_fingerprint') || '').toLowerCase();
    if (fingerprints.indexOf(fingerprint) !== -1 && localStorage.getItem('forum_pki_private_key') && localStorage.getItem('forum_pki_public_key')) script.previousElementSibling.hidden = false;
  } catch (_) {}
})(document.currentScript);
</script>
<?php endif; ?>
