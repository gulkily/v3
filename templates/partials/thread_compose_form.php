<?php
$isCompactThreadCompose = (bool) ($compact ?? false);
$threadComposeFormClass = (string) ($formClass ?? ($isCompactThreadCompose ? 'stack compact-thread-compose-form' : 'stack'));
$threadComposeBodyRows = $isCompactThreadCompose ? 3 : 7;
$threadComposeSubjectPlaceholder = $isCompactThreadCompose ? 'Subject' : 'Thread subject';
$threadComposeBodyPlaceholder = $isCompactThreadCompose ? 'What do you want to discuss?' : 'Body';
$threadComposeSubmitLabel = $isCompactThreadCompose ? 'Post' : 'Create thread';
$threadComposeAnonymousLabel = $isCompactThreadCompose ? 'Post anonymously' : 'Create thread anonymously';
$threadComposeAction = (string) ($action ?? '/compose/thread');
$threadComposeAuthoringOperation = (string) ($authoringOperation ?? 'thread');
$threadComposeTagsSubjectOpen = (string) $boardTags !== 'general' || (string) $subject !== '' || ($error ?? null) !== null;
$threadComposeTagsSubjectSummary = trim(((string) $subject !== '' ? (string) $subject . ' · ' : '') . (string) $boardTags);
$threadComposeEventValues = array_values(array_filter([(string) ($eventDate ?? ''), (string) ($eventTime ?? ''), (string) ($eventLocation ?? '')], static fn (string $value): bool => $value !== ''));
$threadComposeEventOpen = $threadComposeEventValues !== [] || (string) ($eventLink ?? '') !== '' || ($error ?? null) !== null;
$threadComposeEventSummary = implode(' · ', $threadComposeEventValues);
?>
<form method="post"<?= $threadComposeAction !== '' ? ' action="' . $e($threadComposeAction) . '"' : '' ?> class="<?= $e($threadComposeFormClass) ?>" data-compose-form data-compose-kind="thread" data-authoring-operation="<?= $e($threadComposeAuthoringOperation) ?>">
<?php if ($isCompactThreadCompose): ?>
  <textarea name="body" data-compose-field-label="Body" rows="<?= $e($threadComposeBodyRows) ?>" placeholder="<?= $e($threadComposeBodyPlaceholder) ?>" aria-label="Body"><?= $e($body) ?></textarea>
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
<?php else: ?>
  <label>Body<textarea name="body" data-compose-field-label="Body" rows="<?= $e($threadComposeBodyRows) ?>" placeholder="<?= $e($threadComposeBodyPlaceholder) ?>"><?= $e($body) ?></textarea></label>
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
  <details class="compose-disclosure" data-compose-disclosure="tags-subject"<?= $threadComposeTagsSubjectOpen ? ' open' : '' ?>>
    <summary>Subject &amp; tags <span class="meta" data-role="compose-disclosure-summary"><?= $e($threadComposeTagsSubjectSummary) ?></span></summary>
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
  </details>
<?php if ($eventSupportEnabled): ?>
  <details class="compose-disclosure" data-compose-disclosure="event"<?= $threadComposeEventOpen ? ' open' : '' ?>>
    <summary>Event <span class="meta" data-role="compose-disclosure-summary"><?= $e($threadComposeEventSummary) ?></span></summary>
    <label>Event date<input type="date" name="event_date" value="<?= $e($eventDate ?? '') ?>"></label>
    <label>Event time<input type="time" name="event_time" value="<?= $e($eventTime ?? '') ?>"></label>
    <label>Event location<input type="text" name="event_location" value="<?= $e($eventLocation ?? '') ?>"></label>
    <label>Event link<input type="text" name="event_link" value="<?= $e($eventLink ?? '') ?>"></label>
  </details>
<?php endif; ?>
<?php endif; ?>
  <p class="meta compose-normalization-inline" data-role="compose-normalization-status" hidden>
    <span data-role="compose-normalization-message"></span>
  </p>
<?php if ($imageContentEnabled ?? false): ?>
<?= $partial('partials/image_compose_fields.php') ?>
<?php endif; ?>
  <div class="compose-form-actions<?= $isCompactThreadCompose ? ' compact-thread-compose-actions' : '' ?>">
    <button type="submit"><?= $e($threadComposeSubmitLabel) ?></button>
    <button type="submit" data-action="submit-anonymous-compose"><?= $e($threadComposeAnonymousLabel) ?></button>
<?php if ($threadComposeAuthoringOperation === 'quote'): ?>
    <button type="button" class="compose-clear-button" data-action="clean-quote-text" title="Remove timestamps and tidy nick formatting in the pasted chat">Remove timestamps</button>
<?php endif; ?>
    <button type="button" class="compose-clear-button" data-action="clear-compose-fields">Clear fields</button>
  </div>
  <input type="hidden" name="author_identity_id" value="">
  <input type="hidden" name="return_to" value="<?= $e($returnTo ?? '') ?>">
<?php if ($isCompactThreadCompose): ?>
  <input type="hidden" name="board_tags" value="<?= $e($boardTags) ?>">
  <input type="hidden" name="subject" data-compose-field-label="Subject" value="<?= $e($subject) ?>">
<?php endif; ?>
</form>
