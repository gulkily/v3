<?php
/**
 * A campaign's block, in two parts. 'totals', under the thread title: the pledged sum against
 * the goal, one status word once the goal is reached, a bar with the delivered part drawn over
 * the pledged part, and the count line. 'form', below the opening post: the reply composer with
 * an amount field, posting a reply to the opening post, in a framed panel. The same for every
 * reader, so thread pages stay static.
 *
 * @var array<string, mixed>|null $pledgeCampaign from ForumRewrite\View\PledgeCampaign::fromPosts()
 * @var string|null $pledgePart 'totals' (default) or 'form'
 * @var string|null $pledgeReturnTo where posting the form returns to; the thread when unset
 * @var list<int> $pledgePresets amounts offered as buttons beside the field; none when unset
 */
$pledgeCampaign ??= null;
$pledgePart = ($pledgePart ?? 'totals') === 'form' ? 'form' : 'totals';
?>
<?php if (is_array($pledgeCampaign) && $pledgePart === 'totals'): ?>
<?php
$pledgeUnitSuffix = $pledgeCampaign['unit'] !== '' ? ' ' . $pledgeCampaign['unit'] : '';
$pledgeStatus = (string) ($pledgeCampaign['status'] ?? \ForumRewrite\View\PledgeCampaign::STATUS_OPEN);
$pledgeStatusLabel = match ($pledgeStatus) {
    \ForumRewrite\View\PledgeCampaign::STATUS_COMPLETE => 'Complete',
    \ForumRewrite\View\PledgeCampaign::STATUS_GOAL_REACHED => 'Goal reached',
    default => '',
};
$pledgeDelivered = (string) ($pledgeCampaign['filled'] ?? '0');
$pledgeConfirmed = (string) ($pledgeCampaign['confirmed'] ?? '0');
$pledgeBarLabel = $pledgeCampaign['pledged'] . ' of ' . $pledgeCampaign['goal'] . $pledgeUnitSuffix . ' pledged'
    . ($pledgeDelivered !== '0' ? ', ' . $pledgeDelivered . ' delivered' : '');
$pledgeCount = (int) $pledgeCampaign['count'];
?>
<div class="pledge-totals" data-pledge-totals data-pledge-status="<?= $e($pledgeStatus) ?>">
  <p class="pledge-totals__sum"><strong><?= $e($pledgeCampaign['pledged']) ?></strong> of <?= $e($pledgeCampaign['goal'] . $pledgeUnitSuffix) ?> pledged<?php if ($pledgeStatusLabel !== ''): ?> <span class="pledge-status" data-role="pledge-status"><?= $e($pledgeStatusLabel) ?></span><?php endif; ?></p>
  <div class="pledge-bar" role="img" aria-label="<?= $e($pledgeBarLabel) ?>">
    <span class="pledge-bar__fill" style="width: <?= $e((string) $pledgeCampaign['fill_percent']) ?>%"></span>
<?php if ((float) ($pledgeCampaign['delivered_percent'] ?? 0) > 0): ?>
    <span class="pledge-bar__delivered" style="width: <?= $e((string) $pledgeCampaign['delivered_percent']) ?>%"></span>
<?php endif; ?>
<?php if ($pledgeCampaign['goal_percent'] !== null): ?>
    <span class="pledge-bar__goal" style="left: <?= $e((string) $pledgeCampaign['goal_percent']) ?>%" title="Goal: <?= $e($pledgeCampaign['goal']) ?>"></span>
<?php endif; ?>
  </div>
  <p class="meta pledge-totals__count"><?= $pledgeCount ?> <?= $pledgeCount === 1 ? 'pledge' : 'pledges' ?><?php if ($pledgeDelivered !== '0'): ?> · <?= $e($pledgeDelivered) ?> delivered<?php if ($pledgeConfirmed !== '0' && $pledgeConfirmed !== $pledgeDelivered): ?> (<?= $e($pledgeConfirmed) ?> confirmed)<?php endif; ?><?php endif; ?></p>
</div>
<?php elseif (is_array($pledgeCampaign) && isset($thread['root_post_id'])): ?>
<div class="pledge-form" data-compose-root data-unicode-authored-text="<?= ($unicodeAuthoredTextEnabled ?? false) ? '1' : '0' ?>" data-emoji-authored-text="<?= ($emojiAuthoredTextEnabled ?? false) ? '1' : '0' ?>">
  <p class="meta inline-reply-identity-status" data-role="compose-identity-status" hidden></p>
<?= $indent($partial('partials/reply_form.php', [
    'threadId' => $thread['root_post_id'],
    'parentId' => $thread['root_post_id'],
    'boardTags' => 'general',
    'body' => '',
    'submitLabel' => 'Post pledge',
    'bodyRows' => 2,
    'bodyPlaceholder' => 'Note (optional)',
    'showBodyLabel' => false,
    'phoneReplyEnabled' => false,
    'pledgePlacement' => true,
    'pledgePresets' => $pledgePresets ?? [],
    'returnTo' => $pledgeReturnTo ?? null,
]), 1) ?>
</div>
<?php endif; ?>
