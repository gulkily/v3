<?php
/**
 * The amount on a reply that carries a `pledge:` line, or why the line is not counted;
 * a withdrawn pledge names the reply that withdrew it. A reply whose `withdraw` line
 * did not take effect shows why. Each fill that counts is listed under the amount: "Delivered
 * by" when the pledger reported it, "Filled by" when the author or an operator did; a fill
 * reply that does not count, or counts only in part, says so. The same for every reader, so
 * thread pages stay static.
 *
 * @var array<string, mixed>|null $pledgeCampaign
 */
$pledgeEntry = is_array($pledgeCampaign ?? null) ? ($pledgeCampaign['pledges'][(string) $post['post_id']] ?? null) : null;
$withdrawNote = is_array($pledgeCampaign ?? null) ? (string) ($pledgeCampaign['withdrawals'][(string) $post['post_id']]['note'] ?? '') : '';
$fillNote = is_array($pledgeCampaign ?? null) ? (string) ($pledgeCampaign['fills'][(string) $post['post_id']]['note'] ?? '') : '';
$fillList = is_array($pledgeEntry) ? ($pledgeEntry['fill_list'] ?? []) : [];
$withdrawnBy = is_array($pledgeEntry) ? (string) ($pledgeEntry['withdrawn_by'] ?? '') : '';
$withdrawnDate = '';
if ($withdrawnBy !== '') {
    try {
        $withdrawnDate = (new DateTimeImmutable((string) ($pledgeEntry['withdrawn_at'] ?? '')))->format('M j');
    } catch (Exception) {
        $withdrawnDate = '';
    }
}
// Withdraw pledge: for the pledger only, on a pledge that still counts. The same viewer test as
// retract_post_control.php. The form is an ordinary reply to the pledge whose body is the withdraw
// line; pledge_withdraw.js signs and sends it, and without JavaScript it posts as any reply form does.
$withdrawViewerId = is_array($viewerProfile ?? null) ? (string) ($viewerProfile['identity_id'] ?? '') : '';
$canWithdrawPledge = is_array($pledgeEntry) && $pledgeEntry['state'] === \ForumRewrite\View\PledgeCampaign::COUNTED
    && $withdrawViewerId !== '' && $withdrawViewerId === (string) ($post['author_identity_id'] ?? '');
?>
<?php if (is_array($pledgeEntry)): ?>
  <p class="pledge-amount" data-pledge-state="<?= $e($pledgeEntry['state']) ?>"><?php if ($pledgeEntry['amount'] !== null): ?><strong><?= $e($pledgeEntry['amount']) ?></strong><?= $pledgeCampaign['unit'] !== '' ? ' ' . $e($pledgeCampaign['unit']) : '' ?><?php endif; ?><?php if ($withdrawnBy !== ''): ?> · <a href="/posts/<?= $e($withdrawnBy) ?>">Withdrawn</a><?= $withdrawnDate !== '' ? ' <span class="meta">' . $e($withdrawnDate) . '</span>' : '' ?><?php endif; ?><?php if ($pledgeEntry['note'] !== ''): ?><?= $pledgeEntry['amount'] !== null ? ' · ' : '' ?><span class="meta"><?= $e($pledgeEntry['note']) ?></span><?php endif; ?></p>
<?php foreach ($fillList as $fill): ?>
  <p class="pledge-amount" data-pledge-state="fill"><a href="/posts/<?= $e($fill['post_id']) ?>"><?= $fill['role'] === 'pledger' ? 'Delivered' : 'Filled' ?> by <?= $e($fill['by'] !== '' ? $fill['by'] : 'unnamed') ?></a><?= $fill['amount'] !== $pledgeEntry['amount'] ? ' · ' . $e($fill['amount']) : '' ?></p>
<?php endforeach; ?>
<?php endif; ?>
<?php if ($canWithdrawPledge): ?>
  <form class="pledge-withdraw" method="post" action="/compose/reply" data-role="pledge-withdraw">
    <input type="hidden" name="thread_id" value="<?= $e($post['thread_id']) ?>">
    <input type="hidden" name="parent_id" value="<?= $e($post['post_id']) ?>">
    <input type="hidden" name="board_tags" value="general">
    <input type="hidden" name="return_to" value="/threads/<?= $e($post['thread_id']) ?>">
    <input type="hidden" name="body" value="<?= $e(\ForumRewrite\Support\WithdrawDirective::BODY) ?>">
    <button type="submit" class="thread-reaction-button">Withdraw pledge</button>
    <span class="meta" data-role="pledge-withdraw-feedback" role="status" aria-live="polite" hidden></span>
  </form>
<?php endif; ?>
<?php if ($fillNote !== ''): ?>
  <p class="pledge-amount" data-pledge-state="<?= ($pledgeCampaign['fills'][(string) $post['post_id']]['effective'] ?? false) ? 'fill-capped' : 'fill-uncounted' ?>"><span class="meta"><?= $e($fillNote) ?></span></p>
<?php endif; ?>
<?php if ($withdrawNote !== ''): ?>
  <p class="pledge-amount" data-pledge-state="withdraw-uncounted"><span class="meta"><?= $e($withdrawNote) ?></span></p>
<?php endif; ?>
