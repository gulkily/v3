<section class="stack agent-page" data-agent-prompt-origin="<?= $e($agentPromptOrigin) ?>">
  <article class="card agent-page__prompts" id="start">
    <h1 class="agent-page__start-heading">Use this site with your agent</h1>
<?php foreach ($agentPrompts as $prompt): ?>
    <section class="agent-page__prompt" id="<?= $e($prompt['id']) ?>">
      <h2><?= $e($prompt['heading']) ?></h2>
<?= $partial('partials/agent_prompt.php', ['prompt' => $prompt]) ?>
    </section>
<?php endforeach; ?>
  </article>
</section>
<?= $partial('partials/agent_prompt_script.php') ?>
