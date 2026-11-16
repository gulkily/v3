<?php
/**
 * The amount on a reply that carries a `pledge:` line, or why the line is not counted;
 * a withdrawn pledge names the reply that withdrew it. A reply whose `withdraw` line
 * did not take effect shows why. The same for every reader, so thread pages stay static.
 *
 * @var array<string, mixed>|null $pledgeCampaign
 */
$pledgeEntry = is_array($pledgeCampaign ?? null) ? ($pledgeCampaign['pledges'][(string) $post['post_id']] ?? null) : null;
$withdrawNote = is_array($pledgeCampaign ?? null) ? (string) ($pledgeCampaign['withdrawals'][(string) $post['post_id']]['note'] ?? '') : '';
$withdrawnBy = is_array($pledgeEntry) ? (string) ($pledgeEntry['withdrawn_by'] ?? '') : '';
$withdrawnDate = '';
if ($withdrawnBy !== '') {
    try {
        $withdrawnDate = (new DateTimeImmutable((string) ($pledgeEntry['withdrawn_at'] ?? '')))->format('M j');
    } catch (Exception) {
        $withdrawnDate = '';
    }
}
?>
<?php if (is_array($pledgeEntry)): ?>
  <p class="pledge-amount" data-pledge-state="<?= $e($pledgeEntry['state']) ?>"><?php if ($pledgeEntry['amount'] !== null): ?><strong><?= $e($pledgeEntry['amount']) ?></strong><?= $pledgeCampaign['unit'] !== '' ? ' ' . $e($pledgeCampaign['unit']) : '' ?><?php endif; ?><?php if ($withdrawnBy !== ''): ?> · <a href="/posts/<?= $e($withdrawnBy) ?>">Withdrawn</a><?= $withdrawnDate !== '' ? ' <span class="meta">' . $e($withdrawnDate) . '</span>' : '' ?><?php endif; ?><?php if ($pledgeEntry['note'] !== ''): ?><?= $pledgeEntry['amount'] !== null ? ' · ' : '' ?><span class="meta"><?= $e($pledgeEntry['note']) ?></span><?php endif; ?></p>
<?php endif; ?>
<?php if ($withdrawNote !== ''): ?>
  <p class="pledge-amount" data-pledge-state="withdraw-uncounted"><span class="meta"><?= $e($withdrawNote) ?></span></p>
<?php endif; ?>
