<?php
/** @var array<string, mixed>|null $pledgeCampaign from ForumRewrite\View\PledgeCampaign::fromPosts() */
$pledgeCampaign ??= null;
?>
<?php if (is_array($pledgeCampaign)): ?>
<?php
$pledgeUnitSuffix = $pledgeCampaign['unit'] !== '' ? ' ' . $pledgeCampaign['unit'] : '';
$pledgeBarLabel = $pledgeCampaign['pledged'] . ' of ' . $pledgeCampaign['goal'] . $pledgeUnitSuffix . ' pledged';
?>
<div class="pledge-totals" data-pledge-totals>
  <p class="pledge-totals__sum"><strong><?= $e($pledgeCampaign['pledged']) ?></strong> of <?= $e($pledgeCampaign['goal'] . $pledgeUnitSuffix) ?> pledged</p>
  <div class="pledge-bar" role="img" aria-label="<?= $e($pledgeBarLabel) ?>">
    <span class="pledge-bar__fill" style="width: <?= $e((string) $pledgeCampaign['fill_percent']) ?>%"></span>
<?php if ($pledgeCampaign['goal_percent'] !== null): ?>
    <span class="pledge-bar__goal" style="left: <?= $e((string) $pledgeCampaign['goal_percent']) ?>%" title="Goal: <?= $e($pledgeCampaign['goal']) ?>"></span>
<?php endif; ?>
  </div>
  <p class="meta pledge-totals__count"><?= (int) $pledgeCampaign['count'] ?> <?= (int) $pledgeCampaign['count'] === 1 ? 'pledge' : 'pledges' ?></p>
</div>
<?php endif; ?>
