<?php
/**
 * Support: the site's support campaign drawn as a wall of squares, with the opening post as
 * the pitch. See SupportPageController. A named thread that does not exist yet, or has no
 * goal line, shows the title and nothing to count.
 *
 * @var string $supportTitle
 * @var string $supportThreadId
 * @var array<string, mixed>|null $supportRoot the opening post, or null
 * @var array<string, mixed>|null $pledgeCampaign from ForumRewrite\View\PledgeCampaign::fromPosts()
 * @var list<array<string, mixed>> $posts
 */
$supportThreadHref = '/threads/' . rawurlencode($supportThreadId);
?>
<section class="support" aria-labelledby="support-title">
  <h1 class="support__title" id="support-title"><?= $e($supportTitle) ?></h1>
<?php if ($supportRoot !== null): ?>
<?php
$supportPitch = (string) ($supportRoot['body'] ?? '');
// The totals show the goal, so its line is left out of the pitch, as on the thread.
if (is_array($pledgeCampaign) && isset($pledgeCampaign['used_lines'][$supportThreadId])) {
    $supportPitch = \ForumRewrite\Support\PledgeLines::hide($supportPitch, \ForumRewrite\Support\PledgeLines::GOAL);
}
?>
  <div class="support__pitch body<?= $isMarkdownPost($supportRoot) ? ' post-markdown' : '' ?>"><?= $renderPostBody($supportRoot, $supportPitch) ?></div>
<?php endif; ?>
<?= $partial('partials/pledge_totals.php', ['pledgeCampaign' => $pledgeCampaign]) ?>
<?php if (is_array($pledgeCampaign)): ?>
<?= $indent($partial('partials/support_wall.php', ['supportWall' => \ForumRewrite\View\SupportWall::build($pledgeCampaign, $posts), 'supportThreadId' => $supportThreadId]), 1) ?>
<?php endif; ?>
<?php if (is_array($pledgeCampaign)): ?>
<?= $partial('partials/pledge_totals.php', [
    'pledgeCampaign' => $pledgeCampaign,
    'pledgePart' => 'form',
    'thread' => ['root_post_id' => $supportThreadId],
    'pledgeReturnTo' => '/support/',
    'pledgePresets' => [1, 3, 5, 10],
]) ?>
<?php endif; ?>
  <p class="meta support__thread-link"><a href="<?= $e($supportThreadHref) ?>">Open as a thread</a></p>
</section>
