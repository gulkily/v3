<?php
/**
 * The one composer: Title (full new post only), body, tools, then Post and the ··· menu.
 * Attach image stays in the menu: it appears only once the browser knows the viewer may attach images,
 * and in the menu that never shifts the page.
 * thread_compose_form.php and reply_form.php set its options and include it (sharing their variables);
 * every composer on every site renders here.
 *
 * @var string $composerKind 'thread' | 'reply'
 */
$composerKind = (string) ($composerKind ?? 'thread');
$composerIsThread = $composerKind === 'thread';
$composerCompact = (bool) ($composerCompact ?? false);
$composerFull = $composerIsThread && !$composerCompact;
$composerAction = (string) ($composerAction ?? '');
$composerFormClass = (string) ($composerFormClass ?? 'stack');
$composerBodyRows = (int) ($composerBodyRows ?? 7);
$composerBodyPlaceholder = (string) ($composerBodyPlaceholder ?? '');
$composerSubmitLabel = (string) ($composerSubmitLabel ?? 'Post');
$composerAnonymousLabel = (string) ($composerAnonymousLabel ?? 'Post anonymously');
$composerMenuExtra = (string) ($composerMenuExtra ?? '');
$composerFormAttributes = (string) ($composerFormAttributes ?? '');
$composerHiddenFields = (string) ($composerHiddenFields ?? '');
$composerTagsOpen = $composerFull && ((string) $boardTags !== 'general' || ($error ?? null) !== null);
$composerEventValues = array_values(array_filter([(string) ($eventDate ?? ''), (string) ($eventTime ?? ''), (string) ($eventLocation ?? '')], static fn (string $value): bool => $value !== ''));
$composerEventOpen = $composerEventValues !== [] || (string) ($eventLink ?? '') !== '' || ($error ?? null) !== null;
$composerNormalizationStatus = static fn (string $field): string => <<<HTML
  <p class="meta compose-normalization-inline" data-role="compose-field-normalization-status" data-compose-field-status-for="{$field}" hidden>
    <span data-role="compose-field-normalization-message"></span>
    <button
      type="button"
      class="compose-normalization-inline-action"
      data-action="remove-unsupported-compose-characters"
      data-compose-field-remove-for="{$field}"
      hidden
    >Remove unsupported characters</button>
  </p>

HTML;
?>
<form method="post"<?= $composerAction !== '' ? ' action="' . $e($composerAction) . '"' : '' ?> class="<?= $e($composerFormClass) ?>" data-compose-form data-compose-kind="<?= $e($composerKind) ?>"<?= $composerFormAttributes ?>>
<?php if ($composerFull): ?>
  <input type="text" name="subject" class="compose-title" data-compose-field-label="Subject" aria-label="Title" placeholder="Title — or we’ll use your first line" value="<?= $e($subject) ?>">
<?= $composerNormalizationStatus('subject') ?>
<?php endif; ?>
  <textarea name="body" data-compose-field-label="Body" rows="<?= $e($composerBodyRows) ?>" placeholder="<?= $e($composerBodyPlaceholder) ?>" aria-label="Body"><?= $e($body) ?></textarea>
<?= $composerNormalizationStatus('body') ?>
  <p class="meta compose-normalization-inline" data-role="compose-normalization-status" hidden>
    <span data-role="compose-normalization-message"></span>
  </p>
<?php if ($imageContentEnabled ?? false): ?>
<?= $partial('partials/image_compose_fields.php', ['imageChooseInMenu' => true]) ?>
<?php endif; ?>
<?php if ($composerFull): ?>
  <div class="compose-tools">
    <details class="compose-disclosure compose-tool" data-compose-disclosure="tags"<?= $composerTagsOpen ? ' open' : '' ?>>
      <summary aria-label="Tags"># <span data-role="compose-disclosure-summary"><?= $e($boardTags) ?></span></summary>
      <label>Tags<input type="text" name="board_tags" autocapitalize="none" value="<?= $e($boardTags) ?>"></label>
<?= $composerNormalizationStatus('board_tags') ?>
    </details>
<?php if ($eventSupportEnabled ?? false): ?>
    <details class="compose-disclosure compose-tool" data-compose-disclosure="event"<?= $composerEventOpen ? ' open' : '' ?>>
      <summary>Event <span class="meta" data-role="compose-disclosure-summary"><?= $e(implode(' · ', $composerEventValues)) ?></span></summary>
      <label>Date<input type="date" name="event_date" value="<?= $e($eventDate ?? '') ?>"></label>
      <label>Time<input type="time" name="event_time" value="<?= $e($eventTime ?? '') ?>"></label>
      <label>Place<input type="text" name="event_location" value="<?= $e($eventLocation ?? '') ?>"></label>
      <label>Link<input type="text" name="event_link" value="<?= $e($eventLink ?? '') ?>"></label>
    </details>
<?php endif; ?>
  </div>
<?php endif; ?>
  <div class="compose-form-actions<?= $composerIsThread && $composerCompact ? ' compact-thread-compose-actions' : '' ?>">
    <button type="submit"><?= $e($composerSubmitLabel) ?></button>
<?php ob_start(); ?>
<?php if ($imageContentEnabled ?? false): ?>
        <button type="button" class="image-upload-choose" hidden>Attach image</button>
<?php endif; ?>
        <button type="submit" data-action="submit-anonymous-compose"><?= $e($composerAnonymousLabel) ?></button>
        <button type="button" data-action="save-compose-outbox" disabled>Save to Outbox</button>
<?= $composerMenuExtra ?>
        <button type="button" class="compose-clear-button" data-action="clear-compose-fields">Discard draft</button>
<?php $composerMenuItems = (string) ob_get_clean(); ?>
<?= $partial('partials/compose_action_menu.php', ['composeActionMenuItems' => $composerMenuItems]) ?>
  </div>
<?= $composerHiddenFields ?>
</form>
