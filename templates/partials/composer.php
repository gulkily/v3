<?php
/**
 * The one composer: body, then Post and the ··· menu, then Title and tools (full new post only).
 * Options (post format and theme) is a tool on the full composer and its own row on the others.
 * The body comes first so that on a phone it and Post fit above the keyboard; the optional Title and tags follow.
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
// Shown on the closed Tags tool as "#general #art"; compose_disclosure.js keeps it current.
$composerTagsSummary = implode(' ', array_map(static fn (string $tag): string => '#' . ltrim($tag, '#'), preg_split('/[\s,]+/', trim((string) $boardTags), -1, PREG_SPLIT_NO_EMPTY) ?: []));
$composerTagsOpen = $composerFull && ((string) $boardTags !== 'general' || ($error ?? null) !== null);
$composerEventSpan = (string) ($eventDate ?? '') !== '' && (string) ($eventEndDate ?? '') !== '' ? $eventDate . ' – ' . $eventEndDate : (string) ($eventDate ?? '');
$composerEventValues = array_values(array_filter([$composerEventSpan, (string) ($eventTime ?? ''), (string) ($eventLocation ?? '')], static fn (string $value): bool => $value !== ''));
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
<?php ob_start(); ?>
    <details class="compose-disclosure compose-tool compose-options" data-compose-disclosure="options">
      <summary>Options <span class="meta" data-role="compose-disclosure-summary"></span></summary>
      <div class="compose-options-list">
        <input type="hidden" name="body_format" value="plain">
        <label class="compose-options-row">
          <span class="compose-options-name">Markdown <a class="compose-options-help" href="/docs/reference/markdown_syntax.md" target="_blank" rel="noopener" aria-label="Formatting help" title="Formatting help">?</a></span>
          <input type="checkbox" class="compose-switch" role="switch" name="body_format" value="markdown" data-role="markdown-toggle">
        </label>
        <label class="compose-options-row"<?= $composerIsThread ? '' : ' title="Counts only from the thread\'s author or an operator"' ?>>
          <span class="compose-options-name"><?= $composerIsThread ? 'Theme' : 'Thread theme' ?></span>
          <select name="theme" data-role="thread-theme-choice">
            <option value=""><?= $composerIsThread ? 'Site default' : 'Unchanged' ?></option>
<?php foreach (\ForumRewrite\View\ThreadTheme::choices() as $composerThemeChoice): ?>
            <option value="<?= $e($composerThemeChoice['name']) ?>"><?= $e($composerThemeChoice['label']) ?></option>
<?php endforeach; ?>
<?php if (!$composerIsThread): ?>
            <option value="none">Clear thread theme</option>
<?php endif; ?>
          </select>
        </label>
      </div>
    </details>
<?php $composerOptions = (string) ob_get_clean(); ?>
<form method="post"<?= $composerAction !== '' ? ' action="' . $e($composerAction) . '"' : '' ?> class="<?= $e($composerFormClass) ?>" data-compose-form data-compose-kind="<?= $e($composerKind) ?>"<?= $composerFormAttributes ?>>
  <textarea name="body" data-compose-field-label="Body" rows="<?= $e($composerBodyRows) ?>" placeholder="<?= $e($composerBodyPlaceholder) ?>" aria-label="Body"><?= $e($body) ?></textarea>
<?= $composerNormalizationStatus('body') ?>
  <p class="meta compose-normalization-inline" data-role="compose-normalization-status" hidden>
    <span data-role="compose-normalization-message"></span>
  </p>
<?php if ($imageContentEnabled ?? false): ?>
<?= $partial('partials/image_compose_fields.php', ['imageChooseInMenu' => true]) ?>
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
<?php if ($composerFull): ?>
  <input type="text" name="subject" class="compose-title" data-compose-field-label="Subject" aria-label="Title" placeholder="Title — or we’ll use your first line" value="<?= $e($subject) ?>">
<?= $composerNormalizationStatus('subject') ?>
<?php endif; ?>
<?php if ($composerFull): ?>
  <div class="compose-tools">
    <details class="compose-disclosure compose-tool" data-compose-disclosure="tags"<?= $composerTagsOpen ? ' open' : '' ?>>
      <summary>Tags <span class="meta" data-role="compose-disclosure-summary"><?= $e($composerTagsSummary) ?></span></summary>
      <label>Tags<input type="text" name="board_tags" autocapitalize="none" value="<?= $e($boardTags) ?>"></label>
<?= $composerNormalizationStatus('board_tags') ?>
    </details>
<?php if ($eventSupportEnabled ?? false): ?>
    <details class="compose-disclosure compose-tool" data-compose-disclosure="event"<?= $composerEventOpen ? ' open' : '' ?>>
      <summary>Event <span class="meta" data-role="compose-disclosure-summary"><?= $e(implode(' · ', $composerEventValues)) ?></span></summary>
      <label>Date<input type="date" name="event_date" value="<?= $e($eventDate ?? '') ?>"<?= ($composerEventDateFixed ?? false) ? ' readonly' : '' ?>></label>
      <label>Until<input type="date" name="event_end_date" value="<?= $e($eventEndDate ?? '') ?>"></label>
      <label>Time<input type="time" name="event_time" value="<?= $e($eventTime ?? '') ?>"></label>
      <label>Place<input type="text" name="event_location" value="<?= $e($eventLocation ?? '') ?>"></label>
      <label>Link<input type="text" name="event_link" value="<?= $e($eventLink ?? '') ?>"></label>
    </details>
<?php endif; ?>
<?= $composerOptions ?>
  </div>
<?php else: ?>
<?= $composerOptions ?>
<?php endif; ?>
<?= $composerHiddenFields ?>
</form>
