<?php
$retractViewerIdentityId = is_array($viewerProfile ?? null) ? (string) ($viewerProfile['identity_id'] ?? '') : '';
$retractAuthorIdentityId = (string) ($post['author_identity_id'] ?? '');
$retractTags = json_decode((string) ($post['board_tags_json'] ?? '[]'), true);
$retractIsAuthority = is_array($retractTags) && array_intersect($retractTags, ['identity', 'approval', 'invitation']) !== [];
$canRetractPost = ($post['retracted_at'] ?? null) === null
    && $retractViewerIdentityId !== ''
    && $retractAuthorIdentityId !== ''
    && ($retractViewerIdentityId === $retractAuthorIdentityId || ($viewerCanRetractOthers ?? false))
    && !$retractIsAuthority;
?>
<?php if ($canRetractPost): ?>
    <div class="post-retraction-control" data-role="retraction-control">
      <details class="post-more-actions">
        <summary aria-label="More actions">…</summary>
        <div class="post-more-actions-panel">
          <button
            type="button"
            class="thread-reaction-button"
            data-action="retract-post"
            data-post-id="<?= $e($post['post_id']) ?>"
            data-confirm="<?= $e(\ForumRewrite\Support\RetractedPostNotice::CONFIRM) ?>"
            data-done="<?= $e(\ForumRewrite\Support\RetractedPostNotice::DONE) ?>"
          >Retract post</button>
        </div>
      </details>
      <button type="button" class="thread-reaction-button" data-role="retraction-retry" data-action="retract-post"
        data-post-id="<?= $e($post['post_id']) ?>" data-confirm="<?= $e(\ForumRewrite\Support\RetractedPostNotice::CONFIRM) ?>"
        data-done="<?= $e(\ForumRewrite\Support\RetractedPostNotice::DONE) ?>" hidden>Retry retraction</button>
      <p class="meta retraction-feedback" data-role="retraction-feedback" role="status" aria-live="polite" hidden></p>
      <details class="retraction-details" data-role="retraction-details" hidden>
        <summary>Details</summary>
        <p class="meta"></p>
      </details>
    </div>
<?php endif; ?>
