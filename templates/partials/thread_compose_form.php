<?php
// A new post: the shared composer. Compact (board quick compose, QDB add) leaves out Title, tags and event.
$composerKind = 'thread';
$composerCompact = (bool) ($compact ?? false);
$composerAction = (string) ($action ?? '/compose/thread');
$composerFormClass = (string) ($formClass ?? ($composerCompact ? 'stack compact-thread-compose-form' : 'stack'));
$composerBodyRows = $composerCompact ? 3 : 7;
$composerBodyPlaceholder = (string) ($bodyPlaceholder ?? ($composerCompact ? 'What do you want to discuss?' : 'Write your post…'));
$composerSubmitLabel = 'Post';
$composerAnonymousLabel = 'Post anonymously';
$composerAuthoringOperation = (string) ($authoringOperation ?? 'thread');
$composerMenuExtra = $composerAuthoringOperation === 'quote'
    ? '        <button type="button" class="compose-clear-button" data-action="clean-quote-text" title="Remove timestamps and tidy nick formatting in the pasted chat">Remove timestamps</button>' . "\n"
    : '';
$composerFormAttributes = ' data-authoring-operation="' . $e($composerAuthoringOperation) . '"';
$composerHiddenFields = '  <input type="hidden" name="author_identity_id" value="">' . "\n"
    . '  <input type="hidden" name="return_to" value="' . $e($returnTo ?? '') . '">' . "\n"
    . ($composerCompact
        ? '  <input type="hidden" name="board_tags" value="' . $e($boardTags) . '">' . "\n"
            . '  <input type="hidden" name="subject" data-compose-field-label="Subject" value="' . $e($subject) . '">' . "\n"
        : '');
include __DIR__ . '/composer.php';
