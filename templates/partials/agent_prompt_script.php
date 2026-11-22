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
    // A shared prompt much taller than the cap is cut off (agent.css fades
    // its end) and gets "Show all". A hidden card measures 0; it is measured
    // when search shows it again.
    var cutTallPrompt = function (prompt) {
      var text = prompt.querySelector('[data-copy-prompt-source]');
      var more = prompt.querySelector('[data-copy-prompt-more]');
      if (!more || more.getAttribute('aria-expanded') === 'true' || text.scrollHeight === 0) return;
      text.classList.remove('is-cut');
      var cap = 16 * parseFloat(getComputedStyle(document.documentElement).fontSize);
      var tall = text.scrollHeight > cap * 1.25;
      text.classList.toggle('is-cut', tall);
      more.hidden = !tall;
    };
    var cutTallPrompts = function () {
      page.querySelectorAll('[data-copy-prompt]').forEach(cutTallPrompt);
    };
    window.addEventListener('resize', cutTallPrompts);
    page.querySelectorAll('[data-copy-prompt]').forEach(function (prompt) {
      var field = prompt.querySelector('[data-copy-prompt-source]');
      var button = prompt.querySelector('[data-copy-prompt-button]');
      var more = prompt.querySelector('[data-copy-prompt-more]');
      var status = prompt.querySelector('[data-copy-prompt-status]');
      var timer;
      if (from && from !== location.origin) {
        field.textContent = field.textContent.split(from).join(location.origin);
      }
      cutTallPrompt(prompt);
      if (more) {
        more.addEventListener('click', function () {
          var open = more.getAttribute('aria-expanded') !== 'true';
          more.setAttribute('aria-expanded', open ? 'true' : 'false');
          more.textContent = open ? 'Show less' : 'Show all';
          field.classList.toggle('is-cut', !open);
        });
      }
      button.hidden = false;
      button.addEventListener('click', function () {
        status.textContent = '';
        clearTimeout(timer);
        // The icon becomes a check for 1.5 s; the name follows for screen readers.
        var copied = function () {
          button.setAttribute('data-copied', '');
          button.setAttribute('aria-label', 'Copied');
          timer = setTimeout(function () {
            button.removeAttribute('data-copied');
            button.setAttribute('aria-label', 'Copy prompt');
          }, 1500);
        };
        var manual = function () {
          var range = document.createRange();
          range.selectNodeContents(field);
          var selection = window.getSelection();
          selection.removeAllRanges();
          selection.addRange(range);
          status.textContent = touch
            ? 'Couldn\'t copy. Touch and hold the selected prompt, then tap Copy.'
            : 'Press Ctrl+C (Windows/Linux) or ⌘C (Mac) to copy the selected prompt.';
        };
        // Without the Clipboard API (plain HTTP, older browsers), copy now,
        // inside the tap: iPhone Safari allows execCommand only there.
        if (!navigator.clipboard || !navigator.clipboard.writeText) {
          if (copySelectedText(field.textContent)) {
            copied();
          } else {
            manual();
          }
          return;
        }
        navigator.clipboard.writeText(field.textContent).then(copied, function () {
          if (copySelectedText(field.textContent)) {
            copied();
          } else {
            manual();
          }
        });
      });
    });
    // Search filters the cards on the page; sort and category are links.
    var search = page.querySelector('[data-prompt-search]');
    if (search) {
      var cards = page.querySelectorAll('[data-prompt-card]');
      var none = page.querySelector('[data-prompt-no-match]');
      search.hidden = false;
      search.addEventListener('input', function () {
        var words = search.value.toLowerCase().split(/\s+/).filter(Boolean);
        var shown = 0;
        cards.forEach(function (card) {
          var text = card.getAttribute('data-prompt-text') || '';
          var match = words.every(function (word) { return text.indexOf(word) !== -1; });
          card.hidden = !match;
          if (match) shown++;
        });
        if (none) none.hidden = shown !== 0;
        cutTallPrompts();
      });
    }
  })(document.currentScript.previousElementSibling);
</script>
