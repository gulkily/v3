<?php
// One copyable prompt, laid out like railspike.dev's: a label, a read-only
// field that selects itself, an icon-only Copy in the field's top right
// corner, and a status line. The copy icon matches the Bookmarklets copy
// button; after a copy it becomes a check for 1.5 s (the Account key copies
// say "Copied" for as long). agent.php's script reveals Copy and wires it up.
// Expects $prompt: array{id:string, label:string, text:string, label_hidden?:bool, variant?:string}.
// Library prompts hide the label (the card's title names them) and cap the field's height.
?>
<div class="copy-prompt<?= ($prompt['variant'] ?? '') === 'library' ? ' copy-prompt--library' : '' ?>" data-copy-prompt>
  <label class="copy-prompt__label<?= !empty($prompt['label_hidden']) ? ' visually-hidden' : '' ?>" for="agent-prompt-<?= $e($prompt['id']) ?>"><?= $e($prompt['label']) ?></label>
  <div class="copy-prompt__field">
    <textarea id="agent-prompt-<?= $e($prompt['id']) ?>" rows="5" readonly spellcheck="false"><?= $e($prompt['text']) ?></textarea>
    <button type="button" class="copy-prompt__button" title="Copy" data-copy-prompt-button aria-label="Copy prompt" hidden><svg class="copy-prompt__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h4"/></svg><svg class="copy-prompt__icon copy-prompt__icon--copied" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></button>
  </div>
  <p class="meta copy-prompt__status" role="status" aria-live="polite" data-copy-prompt-status></p>
</div>
