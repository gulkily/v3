<?php
// Buttons for tags a thread tag unlocks on its replies (resources/tagsets.txt).
// Exclusive-group tags are one state: an open reply offers the states, a marked one offers
// Reopen. All are rendered so the script can switch them without a reload.
$tagsets = \ForumRewrite\Support\Tagsets::forApplication();
$stateLabels = ['resolved' => 'Resolved', 'closed' => 'Closed', 'open' => 'Reopen'];
foreach ($unlockedPostTags as $unlockedTag):
    $isState = $tagsets->exclusiveGroupOf($unlockedTag) !== null;
    $label = $stateLabels[$unlockedTag] ?? ucfirst($unlockedTag);
    $hidden = $isState && (($unlockedTag === 'open') !== ($postResolution !== null));
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
