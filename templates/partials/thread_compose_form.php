<?php
$isCompactThreadCompose = (bool) ($compact ?? false);
$threadComposeFormClass = $isCompactThreadCompose ? 'stack compact-thread-compose-form' : 'stack';
$threadComposeBodyRows = $isCompactThreadCompose ? 3 : 7;
$threadComposeSubjectPlaceholder = $isCompactThreadCompose ? 'Subject' : 'Thread subject';
$threadComposeBodyPlaceholder = $isCompactThreadCompose ? 'What do you want to discuss?' : 'Body';
$threadComposeSubmitLabel = $isCompactThreadCompose ? 'Post' : 'Create thread';
$threadComposeAnonymousLabel = $isCompactThreadCompose ? 'Post anonymously' : 'Create thread anonymously';
?>
<form method="post" class="<?= $e($threadComposeFormClass) ?>" data-compose-form data-compose-kind="thread">
  <input type="hidden" name="author_identity_id" value="">
<?php if ($isCompactThreadCompose): ?>
  <input type="hidden" name="board_tags" value="<?= $e($boardTags) ?>">
  <input type="hidden" name="subject" data-compose-field-label="Subject" value="<?= $e($subject) ?>">
  <textarea name="body" data-compose-field-label="Body" rows="<?= $e($threadComposeBodyRows) ?>" placeholder="<?= $e($threadComposeBodyPlaceholder) ?>" aria-label="Body"><?= $e($body) ?></textarea>
<?php else: ?>
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
  <label>Subject<input type="text" name="subject" data-compose-field-label="Subject" placeholder="<?= $e($threadComposeSubjectPlaceholder) ?>" value="<?= $e($subject) ?>"></label>
  <p class="meta compose-normalization-inline" data-role="compose-field-normalization-status" data-compose-field-status-for="subject" hidden>
    <span data-role="compose-field-normalization-message"></span>
    <button
      type="button"
      class="compose-normalization-inline-action"
      data-action="remove-unsupported-compose-characters"
      data-compose-field-remove-for="subject"
      hidden
    >Remove unsupported characters</button>
  </p>
  <label>Body<textarea name="body" data-compose-field-label="Body" rows="<?= $e($threadComposeBodyRows) ?>" placeholder="<?= $e($threadComposeBodyPlaceholder) ?>"><?= $e($body) ?></textarea></label>
<?php endif; ?>
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
  <div class="compose-form-actions<?= $isCompactThreadCompose ? ' compact-thread-compose-actions' : '' ?>">
    <button type="submit"><?= $e($threadComposeSubmitLabel) ?></button>
    <button type="submit" data-action="submit-anonymous-compose"><?= $e($threadComposeAnonymousLabel) ?></button>
    <button type="button" class="compose-clear-button" data-action="clear-compose-fields">Clear fields</button>
  </div>
</form>
