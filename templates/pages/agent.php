<?php
// Prompts: the library of shared #prompt threads, then the site's own prompts.
// Sort and category are plain links (?sort=new, ?tag=coding); search filters
// the cards already on the page and appears only with script. Like on a card
// is the thread page's Like (thread_reactions.js); the count is its score node.
$promptHref = static function (string $sort, string $tag): string {
    $query = array_filter(['sort' => $sort === 'new' ? 'new' : '', 'tag' => $tag], static fn (string $value): bool => $value !== '');
    return '/agent/' . ($query === [] ? '' : '?' . http_build_query($query));
};
$plural = static fn (int $count, string $one, string $many): string => $count . ' ' . ($count === 1 ? $one : $many);
?>
<section class="stack agent-page" data-agent-prompt-origin="<?= $e($agentPromptOrigin) ?>">
  <article class="card prompt-library" id="prompts">
    <div class="board-toolbar prompt-library__toolbar">
      <nav class="board-filter" aria-label="Sort prompts">
<?php foreach (['top' => 'Top', 'new' => 'New'] as $sortKey => $sortLabel): ?>
        <a class="nav-link<?= $promptSort === $sortKey ? ' is-active' : '' ?>" href="<?= $e($promptHref($sortKey, $promptTag)) ?>"<?= $promptSort === $sortKey ? ' aria-current="page"' : '' ?>><?= $e($sortLabel) ?></a>
<?php endforeach; ?>
      </nav>
<?php if ($sharedPromptCount > 0): ?>
      <input type="search" class="board-toolbar__search" placeholder="Search prompts" aria-label="Search prompts" autocomplete="off" spellcheck="false" data-prompt-search hidden>
<?php endif; ?>
      <a class="nav-link prompt-library__share" href="<?= $e($promptShareHref) ?>">Share a prompt</a>
    </div>
<?php if ($promptCategories !== []): ?>
    <nav class="board-filter prompt-library__categories" aria-label="Categories">
      <a class="nav-link<?= $promptTag === '' ? ' is-active' : '' ?>" href="<?= $e($promptHref($promptSort, '')) ?>"<?= $promptTag === '' ? ' aria-current="page"' : '' ?>>All</a>
<?php foreach ($promptCategories as $category): ?>
      <a class="nav-link<?= $promptTag === $category ? ' is-active' : '' ?>" href="<?= $e($promptHref($promptSort, $category)) ?>"<?= $promptTag === $category ? ' aria-current="page"' : '' ?>>#<?= $e($category) ?></a>
<?php endforeach; ?>
    </nav>
<?php endif; ?>
<?php if ($sharedPrompts === []): ?>
    <p class="meta prompt-library__empty"><?= $promptTag !== '' ? 'No prompts tagged #' . $e($promptTag) . ' yet.' : 'No shared prompts yet. Post one tagged #prompt.' ?></p>
<?php else: ?>
    <div class="prompt-library__list">
<?php foreach ($sharedPrompts as $shared): ?>
<?php
    $threadId = (string) $shared['root_post_id'];
    $title = $threadTitle($shared);
    $likes = max(0, (int) $shared['score_total']);
    $replies = (int) $shared['reply_count'];
    $search = strtolower((string) preg_replace('/\s+/', ' ', $title . ' ' . implode(' ', $shared['prompt_tags']) . ' ' . (string) $shared['body']));
?>
      <section class="prompt-card" id="prompt-<?= $e($threadId) ?>" data-thread-reactions-root data-thread-id="<?= $e($threadId) ?>" data-prompt-card data-prompt-text="<?= $e($search) ?>">
        <h2 class="prompt-card__title"><a href="/threads/<?= $e($threadId) ?>"><?= $e($title) ?></a></h2>
<?= $partial('partials/agent_prompt.php', ['prompt' => [
    'id' => 'shared-' . $threadId,
    'label' => $title,
    'label_hidden' => true,
    'variant' => 'library',
    // Without the body's closing newline, which would show as an empty last line.
    'text' => rtrim((string) $shared['body']),
]]) ?>
        <div class="prompt-card__footer">
<?php if ($shared['prompt_tags'] !== []): ?>
          <p class="prompt-card__tags"><?php foreach ($shared['prompt_tags'] as $category): ?><a class="chip" href="<?= $e($promptHref($promptSort, (string) $category)) ?>">#<?= $e($category) ?></a> <?php endforeach; ?></p>
<?php endif; ?>
          <p class="meta prompt-card__meta"><?= $rowMeta($shared, 'root_post_created_at') ?><span class="prompt-card__likes" data-role="thread-score" data-score-format="likes"><?= $likes > 0 ? $e($plural($likes, 'like', 'likes')) : '' ?></span> · <a href="/threads/<?= $e($threadId) ?>"><?= $replies > 0 ? $e($plural($replies, 'reply', 'replies')) : 'Discuss' ?></a> <button type="button" class="thread-reaction-button prompt-card__like" data-action="apply-thread-tag" data-tag="like" data-applied-label="Liked" aria-pressed="false">Like</button></p>
        </div>
        <p class="meta prompt-card__feedback" data-role="thread-reaction-feedback" hidden></p>
      </section>
<?php endforeach; ?>
    </div>
    <p class="meta prompt-library__empty" data-prompt-no-match hidden>No prompts match.</p>
<?php endif; ?>
<?php if ($waitingPrompts !== []): ?>
    <details class="prompt-library__waiting">
      <summary>Waiting for approval (<?= count($waitingPrompts) ?>)</summary>
      <p class="meta">An approved member's #prompt tag adds one to the library.</p>
      <ul>
<?php foreach ($waitingPrompts as $waiting): ?>
        <li><a href="/threads/<?= $e((string) $waiting['root_post_id']) ?>"><?= $e($threadTitle($waiting)) ?></a> <span class="meta">· <?= $rowMeta($waiting, 'root_post_created_at') ?></span></li>
<?php endforeach; ?>
      </ul>
    </details>
<?php endif; ?>
  </article>
  <article class="card agent-page__prompts" id="start">
    <h2 class="agent-page__start-heading">Use this site with your agent</h2>
<?php foreach ($agentPrompts as $prompt): ?>
    <section class="agent-page__prompt" id="<?= $e($prompt['id']) ?>">
      <h3><?= $e($prompt['heading']) ?></h3>
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
