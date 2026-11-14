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
    var touch = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
    // Copies through a hidden read-only field (selected with
    // setSelectionRange, which iPhone Safari needs; 16px so it doesn't zoom).
    var copySelectedText = function (text) {
      var scratch = document.createElement('textarea');
      scratch.value = text;
      scratch.setAttribute('readonly', '');
      scratch.setAttribute('aria-hidden', 'true');
      scratch.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;font-size:16px';
      document.body.appendChild(scratch);
      var ok = false;
      try {
        scratch.focus();
        scratch.select();
        scratch.setSelectionRange(0, text.length);
        ok = typeof document.execCommand === 'function' && document.execCommand('copy');
      } catch (error) {
        ok = false;
      }
      scratch.remove();
      return ok;
    };
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
          field.setSelectionRange(0, field.value.length);
          status.textContent = touch
            ? 'Couldn\'t copy. Touch and hold the selected prompt, then tap Copy.'
            : 'Press Ctrl+C (Windows/Linux) or ⌘C (Mac) to copy the selected prompt.';
        };
        // Without the Clipboard API (plain HTTP, older browsers), copy now,
        // inside the tap: iPhone Safari allows execCommand only there.
        if (!navigator.clipboard || !navigator.clipboard.writeText) {
          if (copySelectedText(field.value)) {
            copied();
          } else {
            manual();
          }
          return;
        }
        navigator.clipboard.writeText(field.value).then(copied, function () {
          if (copySelectedText(field.value)) {
            copied();
          } else {
            manual();
          }
        });
      });
    });
  })(document.currentScript.previousElementSibling);
</script>
