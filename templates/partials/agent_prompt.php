<?php
// One copyable prompt: a caption, the prompt as selectable text in a quiet
// box, an icon-only Copy in the box's top right corner, and a status line.
// No form field: a text field is for writing. The copy icon matches the
// Bookmarklets copy button; after a copy it becomes a check for 1.5 s (the
// Account key copies say "Copied" for as long). agent.php's script reveals
// Copy and wires it up.
// Expects $prompt: array{id:string, label:string, text:string, label_hidden?:bool, variant?:string}.
// Library prompts hide the caption (the card's title names them); the script
// cuts a tall one off and reveals "Show all".
$isLibraryPrompt = ($prompt['variant'] ?? '') === 'library';
?>
<div class="copy-prompt<?= $isLibraryPrompt ? ' copy-prompt--library' : '' ?>" data-copy-prompt>
  <p class="copy-prompt__label<?= !empty($prompt['label_hidden']) ? ' visually-hidden' : '' ?>" id="agent-prompt-<?= $e($prompt['id']) ?>-label"><?= $e($prompt['label']) ?></p>
  <div class="copy-prompt__field">
    <pre class="copy-prompt__text" id="agent-prompt-<?= $e($prompt['id']) ?>" aria-labelledby="agent-prompt-<?= $e($prompt['id']) ?>-label" data-copy-prompt-source><?= $e($prompt['text']) ?></pre>
    <button type="button" class="copy-prompt__button" title="Copy" data-copy-prompt-button aria-label="Copy prompt" hidden><svg class="copy-prompt__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" focusable="false"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h4"/></svg><svg class="copy-prompt__icon copy-prompt__icon--copied" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></button>
  </div>
<?php if ($isLibraryPrompt): ?>
  <button type="button" class="copy-prompt__more" aria-controls="agent-prompt-<?= $e($prompt['id']) ?>" aria-expanded="false" data-copy-prompt-more hidden>Show all</button>
<?php endif; ?>
  <p class="meta copy-prompt__status" role="status" aria-live="polite" data-copy-prompt-status></p>
</div>
