<?php
// Release: a reply to a claim whose body is `release: <ID>`, so the claim keeps its other
// items. backlog_claim.js signs and sends it. On the list the rows sit inside the Start
// form, where a form cannot nest, so the form itself is drawn after the list and the
// button in the row names it. $releases is a list of [item ID, claim post ID].
foreach ($releases as [$releaseId, $releasePost]): ?>
<form id="release-<?= $e($releaseId) ?>" method="post" action="/compose/reply" data-role="backlog-release-form" data-failure="Not released." data-refresh="<?= $e($refresh($releaseId)) ?>" hidden>
  <input type="hidden" name="thread_id" value="<?= $e($backlogClaimsThread) ?>">
  <input type="hidden" name="parent_id" value="<?= $e($releasePost) ?>">
  <input type="hidden" name="board_tags" value="general">
  <input type="hidden" name="body" value="<?= $e(\ForumRewrite\Support\ReleaseDirective::body([$releaseId])) ?>">
</form>
<?php endforeach;
