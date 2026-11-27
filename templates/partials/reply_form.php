<?php
// A reply: the shared composer without Title, tags or event.
$composerKind = 'reply';
$composerCompact = false;
$composerAction = '/compose/reply';
$composerFormClass = (string) ($formClass ?? 'stack');
$composerBodyRows = (int) ($bodyRows ?? 7);
if ($composerBodyRows < 1) {
    $composerBodyRows = 7;
}
$composerBodyPlaceholder = (string) ($bodyPlaceholder ?? 'Write your reply…');
$composerSubmitLabel = (string) ($submitLabel ?? 'Post reply');
$composerAnonymousLabel = 'Reply anonymously';
$composerMenuExtra = '';
$composerFormAttributes = ($phoneReplyEnabled ?? false) ? ' data-phone-reply' : '';
$composerHiddenFields = '  <input type="hidden" name="thread_id" value="' . $e($threadId) . '">' . "\n"
    . '  <input type="hidden" name="parent_id" value="' . $e($parentId) . '">' . "\n"
    . '  <input type="hidden" name="author_identity_id" value="">' . "\n"
    . '  <input type="hidden" name="board_tags" value="' . $e($boardTags) . '">' . "\n"
    . '  <input type="hidden" name="return_to" value="' . $e($returnTo ?? '') . '">' . "\n";
// The pledge form in a campaign's totals block: the same reply composer with an amount field before
// the note, and its own draft so it never mixes with the reply box at the bottom of the page.
$composerLeadFields = '';
if (($pledgePlacement ?? false) && is_array($pledgeCampaign ?? null)) {
    // Presets are buttons pledge_field.js draws beside the field; without script the field is enough.
    $pledgePresetList = implode(',', array_map('intval', $pledgePresets ?? []));
    $composerLeadFields = '  <label class="pledge-field">Pledge<input type="text" name="pledge_amount" inputmode="decimal" autocomplete="off" size="8" maxlength="20" data-role="pledge-amount"'
        . ($pledgePresetList !== '' ? ' data-pledge-presets="' . $e($pledgePresetList) . '"' : '') . '>'
        . ($pledgeCampaign['unit'] !== '' ? '<span class="pledge-field__unit">' . $e($pledgeCampaign['unit']) . '</span>' : '') . '</label>' . "\n";
    $composerFormAttributes .= ' data-pledge-form data-compose-draft-scope="pledge"';
    $composerShowOptions = false;
}
include __DIR__ . '/composer.php';
