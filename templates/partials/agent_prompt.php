<?php
// One copyable prompt, laid out like railspike.dev's: a label, a read-only
// field that selects itself, Copy in the field's corner, and a status line.
// The button and its icon match the Bookmarklets copy button; feedback
// matches the Account key copies ("Copied" for 1.5 s). agent.php's script
// reveals Copy and wires it up.
// Expects $prompt: array{id:string, label:string, text:string}.
?>
<div class="copy-prompt" data-copy-prompt>
  <label class="copy-prompt__label" for="agent-prompt-<?= $e($prompt['id']) ?>"><?= $e($prompt['label']) ?></label>
  <div class="copy-prompt__field">
    <textarea id="agent-prompt-<?= $e($prompt['id']) ?>" rows="5" readonly spellcheck="false"><?= $e($prompt['text']) ?></textarea>
    <button type="button" class="copy-prompt__button" data-copy-prompt-button aria-label="Copy prompt" hidden><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h4"/></svg> <span data-copy-prompt-text>Copy</span></button>
  </div>
  <p class="meta copy-prompt__status" role="status" aria-live="polite" data-copy-prompt-status></p>
</div>
