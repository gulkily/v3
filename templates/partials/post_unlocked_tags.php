<?php
// Buttons for tags a thread tag unlocks on its posts (resources/tagsets.txt).
// Exclusive-group tags are one state (claimed, resolved, closed; open = none). All buttons are
// rendered and shown or hidden by PostStateView, so the script can switch them without a reload.
$tagsets = \ForumRewrite\Support\Tagsets::forApplication();
foreach ($unlockedPostTags as $unlockedTag):
    $isState = $tagsets->exclusiveGroupOf($unlockedTag) !== null;
    $label = $isState ? \ForumRewrite\Support\PostStateView::buttonLabel($unlockedTag, $postResolution) : ucfirst($unlockedTag);
    $hidden = $isState && \ForumRewrite\Support\PostStateView::buttonHidden($unlockedTag, $postResolution);
?>
<button
  type="button"
  class="thread-reaction-button<?= $isState ? ' post-state-button' : '' ?>"
  data-action="apply-post-tag"
  data-post-id="<?= $e($post['post_id']) ?>"
  data-tag="<?= $e($unlockedTag) ?>"
  data-applied-label="<?= $e($label) ?>"
<?= $isState ? '  data-state-tag="1"' . "\n" : '' ?><?= $hidden ? '  hidden' . "\n" : '' ?>><?= $e($label) ?></button>
<?php endforeach; ?>
