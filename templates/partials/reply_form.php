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
include __DIR__ . '/composer.php';
