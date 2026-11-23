<?php
// Tools → Backlog → Start: the picked items, the mode, and the prompt to copy.
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
    <ul class="backlog-list">
<?php foreach ($backlogStartItems as $item): ?>
      <li class="backlog-row">
        <span class="backlog-row__title"><?= $e($item['title']) ?></span>
        <span class="backlog-row__meta meta"><span class="backlog-row__id"><?= $e($item['id']) ?></span></span>
      </li>
<?php endforeach; ?>
    </ul>
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
