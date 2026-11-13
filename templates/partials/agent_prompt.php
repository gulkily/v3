<?php
// A prompt the visitor pastes into their own agent, like railspike.dev's
// install prompt, folded under one line on About. /about/#agent (the board's
// first-visit intro and the Tools launcher link there) opens it.
// The server fills in this request's origin; the script puts in the page's
// own origin when the HTML came from a cache or snapshot. Copy appears only
// where the clipboard API exists.
?>
<?php if (($agentPrompt ?? '') !== ''): ?>
<details class="agent-prompt" id="agent" data-agent-prompt data-agent-prompt-origin="<?= $e($agentPromptOrigin ?? '') ?>">
  <summary>Use with your agent</summary>
  <div class="agent-prompt-field">
    <textarea rows="5" readonly spellcheck="false" aria-label="Prompt for your agent"><?= $e($agentPrompt) ?></textarea>
    <button type="button" data-agent-prompt-copy hidden>Copy</button>
  </div>
</details>
<script>
  (function (root) {
    var field = root.querySelector('textarea');
    var from = root.getAttribute('data-agent-prompt-origin');
    if (from && from !== location.origin) {
      field.value = field.value.split(from).join(location.origin);
    }
    var openFromHash = function () {
      if (location.hash === '#agent') {
        root.open = true;
      }
    };
    openFromHash();
    addEventListener('hashchange', openFromHash);
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
