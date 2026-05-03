<?php
$formClass = (string) ($formClass ?? 'stack');
$submitLabel = (string) ($submitLabel ?? 'Create reply');
$bodyRows = (int) ($bodyRows ?? 7);
if ($bodyRows < 1) {
    $bodyRows = 7;
}
?>
<form method="post" class="<?= $e($formClass) ?>" data-compose-form data-compose-kind="reply">
  <input type="hidden" name="thread_id" value="<?= $e($threadId) ?>">
  <input type="hidden" name="parent_id" value="<?= $e($parentId) ?>">
  <input type="hidden" name="author_identity_id" value="">
  <label>Board tags<input type="text" name="board_tags" value="<?= $e($boardTags) ?>"></label>
  <p class="meta compose-normalization-inline" data-role="compose-field-normalization-status" data-compose-field-status-for="board_tags" hidden>
    <span data-role="compose-field-normalization-message"></span>
    <button
      type="button"
      class="compose-normalization-inline-action"
      data-action="remove-unsupported-compose-characters"
      data-compose-field-remove-for="board_tags"
      hidden
    >Remove unsupported characters</button>
  </p>
  <label>Body<textarea name="body" data-compose-field-label="Body" rows="<?= $e($bodyRows) ?>" placeholder="ASCII reply body"><?= $e($body) ?></textarea></label>
  <p class="meta compose-normalization-inline" data-role="compose-field-normalization-status" data-compose-field-status-for="body" hidden>
    <span data-role="compose-field-normalization-message"></span>
    <button
      type="button"
      class="compose-normalization-inline-action"
      data-action="remove-unsupported-compose-characters"
      data-compose-field-remove-for="body"
      hidden
    >Remove unsupported characters</button>
  </p>
  <p class="meta compose-normalization-inline" data-role="compose-normalization-status" hidden>
    <span data-role="compose-normalization-message"></span>
  </p>
  <button type="submit"><?= $e($submitLabel) ?></button>
</form>
