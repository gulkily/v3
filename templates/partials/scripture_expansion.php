<?php
$expansion = $scriptureExpansionsByPostId[$post['post_id']] ?? null;
if (!is_array($expansion) || ($post['retracted_at'] ?? null) !== null) { return; }
$expansionState = (string) $expansion['generation_status'];
$hasExpansion = $expansionState === 'generated';
$canExpand = (bool) ($viewerCanRequestAgentReply ?? false);
$expansionMessage = match ($expansionState) {
    'requested', 'in_progress' => 'Expansion requested. Additional reflection will appear here when ready.',
    'failed' => (string) ($expansion['failure_message'] ?? 'Expansion failed. An operator can inspect and retry.'),
    'not_recommended' => 'Expansion is unavailable for this response or its current source.',
    default => '',
};
?>
<section class="scripture-expansion" data-scripture-expansion data-post-id="<?= $e($post['post_id']) ?>" data-expansion-state="<?= $e($expansionState) ?>">
<?php if ($canExpand && !$hasExpansion && $expansionState !== 'not_recommended'): ?>
  <button type="button" data-action="expand-scripture"<?= in_array($expansionState, ['requested', 'in_progress'], true) ? ' disabled' : '' ?>><?= $expansionState === 'failed' ? 'Check expansion status' : 'Expand' ?></button>
<?php endif; ?>
  <p class="meta" role="status" aria-live="polite" data-expansion-feedback><?= $e($expansionMessage) ?></p>
  <details data-expansion-details<?= !$hasExpansion ? ' hidden' : '' ?>>
    <summary>Further scripture reflection</summary>
    <div class="body" data-expansion-body><?= $hasExpansion ? $br((string) $expansion['expansion_body']) : '' ?></div>
  </details>
</section>
