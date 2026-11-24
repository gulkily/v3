<?php
// Tools → Backlog → Start: the picked items, the mode, and the prompt to copy.
// On a site with a claims thread each item also says who holds it. A viewer the
// server recognises gets a Claim form for the picked items they have no claim on:
// an ordinary reply to the claims thread whose body is the claim line, signed and
// sent by backlog_claim.js. It is sent without a tap only when this page was
// reached by pressing Start; a reload or a pasted address never claims.
$claimIds = [];
if ($backlogClaimsThread !== null && $backlogViewerId !== '') {
    foreach ($backlogStartItems as $item) {
        if (!\ForumRewrite\Feedback\BacklogClaims::hasClaimBy($item['claim'], $backlogViewerId)) {
            $claimIds[] = $item['id'];
        }
    }
}
?>
<section class="stack backlog-page agent-page" data-agent-prompt-origin="<?= $e($agentPromptOrigin) ?>">
  <h1 class="visually-hidden">Start</h1>
<?php if ($backlogStartUnknown !== []): ?>
  <p class="feedback" role="status">Left out, no such item: <?= $e(implode(', ', $backlogStartUnknown)) ?></p>
<?php endif; ?>
<?php if ($backlogStartClosed !== []): ?>
  <p class="feedback" role="status">Left out, already closed: <?= $e(implode(', ', $backlogStartClosed)) ?></p>
<?php endif; ?>
<?php if ($backlogStartPrompt === null): ?>
  <article class="card">
    <p class="meta">Nothing to start.</p>
  </article>
<?php else: ?>
  <article class="card backlog-card">
<?= $backlogClaimsThread !== null ? '    <div data-role="backlog-start-claims">' . "\n" : '' ?>
    <ul class="backlog-list">
<?php foreach ($backlogStartItems as $item): ?>
      <li class="backlog-row">
        <span class="backlog-row__title"><?= $e($item['title']) ?></span>
        <span class="backlog-row__meta meta"><span class="backlog-row__id"><?= $e($item['id']) ?></span></span>
<?php if ($backlogClaimsThread !== null): ?>
<?php $claimLines = \ForumRewrite\Feedback\BacklogClaims::lines($item['claim'], true); ?>
        <span class="backlog-row__claim meta"><span data-role="backlog-claim" data-claim-id="<?= $e($item['id']) ?>"><?= $e($claimLines === [] ? 'Not claimed' : implode(' · ', $claimLines)) ?></span><?php if ($item['release_post'] !== null): ?> <button type="submit" class="backlog-release requires-script" form="release-<?= $e($item['id']) ?>">Release</button><span data-role="backlog-claim-feedback" data-feedback-for="release-<?= $e($item['id']) ?>" role="status" aria-live="polite" hidden></span><?php endif; ?></span>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ul>
<?= $backlogClaimsThread === null ? '' : $partial('partials/backlog_release.php', [
    'backlogClaimsThread' => $backlogClaimsThread,
    'releases' => array_values(array_filter(array_map(static fn (array $item): ?array => $item['release_post'] === null ? null : [$item['id'], $item['release_post']], $backlogStartItems))),
    'refresh' => static fn (string $id): string => '[data-role="backlog-start-claims"]',
]) ?>
<?php if ($claimIds !== []): ?>
    <form class="backlog-claim-form requires-script" method="post" action="/compose/reply" data-role="backlog-claim-form" data-claim-ids="<?= $e(implode(',', $claimIds)) ?>">
      <input type="hidden" name="thread_id" value="<?= $e($backlogClaimsThread) ?>">
      <input type="hidden" name="parent_id" value="<?= $e($backlogClaimsThread) ?>">
      <input type="hidden" name="board_tags" value="general">
      <input type="hidden" name="body" value="<?= $e(\ForumRewrite\Support\ClaimDirective::body($claimIds)) ?>">
      <button type="submit">Claim</button>
      <span class="meta" data-role="backlog-claim-feedback" role="status" aria-live="polite" hidden></span>
    </form>
<?php endif; ?>
<?= $backlogClaimsThread !== null ? "    </div>\n" : '' ?>
  </article>
  <nav class="board-filter backlog-filter" aria-label="How">
<?php foreach ($backlogStartModes as $option): ?>
    <a class="nav-link<?= $option['is_active'] ? ' is-active' : '' ?>" href="<?= $e($option['href']) ?>"<?= $option['is_active'] ? ' aria-current="true"' : '' ?>><?= $e($option['label']) ?></a>
<?php endforeach; ?>
  </nav>
  <article class="card">
<?= $partial('partials/agent_prompt.php', ['prompt' => $backlogStartPrompt]) ?>
    <p class="meta backlog-count"><a href="<?= $e($backlogStartTextHref) ?>">text</a></p>
  </article>
<?php endif; ?>
  <p><a href="/backlog">Backlog</a></p>
</section>
<?= $partial('partials/agent_prompt_script.php') ?>
