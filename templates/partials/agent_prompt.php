<?php
// A prompt the visitor pastes into their own agent, like railspike.dev's
// install prompt. The server fills in this request's origin; the script puts
// in the page's own origin when the HTML came from a cache or snapshot.
// Copy appears only where the clipboard API exists.
?>
<?php if (($agentPrompt ?? '') !== ''): ?>
<div class="agent-prompt" data-agent-prompt data-agent-prompt-origin="<?= $e($agentPromptOrigin ?? '') ?>">
  <label for="<?= $e($promptId) ?>">Paste this into your agent:</label>
  <textarea id="<?= $e($promptId) ?>" rows="5" readonly spellcheck="false"><?= $e($agentPrompt) ?></textarea>
  <button type="button" data-agent-prompt-copy hidden>Copy</button>
</div>
<script>
  (function (root) {
    var field = root.querySelector('textarea');
    var from = root.getAttribute('data-agent-prompt-origin');
    if (from && from !== location.origin) {
      field.value = field.value.split(from).join(location.origin);
    }
    var button = root.querySelector('[data-agent-prompt-copy]');
    if (!navigator.clipboard || !navigator.clipboard.writeText) {
      return;
    }
    button.hidden = false;
    button.addEventListener('click', function () {
      navigator.clipboard.writeText(field.value).then(function () {
        button.textContent = 'Copied';
        setTimeout(function () { button.textContent = 'Copy'; }, 2000);
      }, function () {
        field.select();
      });
    });
  })(document.currentScript.previousElementSibling);
</script>
<?php endif; ?>
