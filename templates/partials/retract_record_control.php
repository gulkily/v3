<?php
/**
 * Retract button for one like, shared by the post's Records list and the record page. It uses
 * post_retraction.js, which signs a retraction naming the record in Target-ID.
 */
$retractViewerIdentityId = is_array($viewerProfile ?? null) ? (string) ($viewerProfile['identity_id'] ?? '') : '';
$retractRecordAuthor = (string) ($recordAuthorIdentityId ?? '');
$canRetractRecord = $retractViewerIdentityId !== '' && $retractRecordAuthor !== ''
    && ($retractViewerIdentityId === $retractRecordAuthor || ($viewerCanRetractOthers ?? false));
$retractConfirm = $retractViewerIdentityId === $retractRecordAuthor
    ? 'Retract your like? It will stop counting, and its record is replaced by a retraction receipt.'
    : 'Retract this like as an operator? It will stop counting, and the receipt will name you.';
?>
<?php if ($canRetractRecord): ?>
<span class="post-retraction-control record-retraction-control" data-role="retraction-control">
  <button type="button" class="thread-reaction-button" data-action="retract-post"
    data-post-id="<?= $e((string) $recordId) ?>" data-confirm="<?= $e($retractConfirm) ?>">Retract</button>
  <span class="meta retraction-feedback" data-role="retraction-feedback" role="status" aria-live="polite" hidden></span>
  <details class="retraction-details" data-role="retraction-details" hidden><summary>Details</summary><p class="meta"></p></details>
</span>
<?php endif; ?>
