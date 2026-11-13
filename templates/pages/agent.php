<section class="stack agent-page" data-agent-prompt-origin="<?= $e($agentPromptOrigin) ?>">
  <h1>Use with your agent</h1>
  <article class="card agent-page__prompts">
<?php foreach ($agentPrompts as $prompt): ?>
    <section class="agent-page__prompt" id="<?= $e($prompt['id']) ?>">
      <h2><?= $e($prompt['heading']) ?></h2>
<?= $partial('partials/agent_prompt.php', ['prompt' => $prompt]) ?>
    </section>
<?php endforeach; ?>
  </article>
</section>
<script>
  // Runs before first paint. Pages rendered without a request (caches,
  // offline snapshots) name a placeholder origin; use this page's own.
  (function (page) {
    var from = page.getAttribute('data-agent-prompt-origin');
    page.querySelectorAll('[data-copy-prompt]').forEach(function (prompt) {
      var field = prompt.querySelector('textarea');
      var button = prompt.querySelector('[data-copy-prompt-button]');
      var label = button.querySelector('[data-copy-prompt-text]');
      var status = prompt.querySelector('[data-copy-prompt-status]');
      var timer;
      if (from && from !== location.origin) {
        field.value = field.value.split(from).join(location.origin);
      }
      field.addEventListener('focus', function () { field.select(); });
      field.addEventListener('mouseup', function (event) {
        event.preventDefault();
        field.select();
      });
      button.hidden = false;
      button.addEventListener('click', function () {
        status.textContent = '';
        clearTimeout(timer);
        var copied = function () {
          label.textContent = 'Copied';
          timer = setTimeout(function () { label.textContent = 'Copy'; }, 1500);
        };
        var manual = function () {
          field.focus();
          field.select();
          status.textContent = 'Press Ctrl+C (Windows/Linux) or ⌘C (Mac) to copy the selected prompt.';
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(field.value).then(copied, manual);
        } else {
          manual();
        }
      });
    });
  })(document.currentScript.previousElementSibling);
</script>
