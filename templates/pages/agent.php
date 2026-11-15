<?php
// Prompts: the library of shared #prompt threads, then the site's own prompts.
// Sort and category are plain links (?sort=new, ?tag=coding); search filters
// the cards already on the page and appears only with script.
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
      <section class="prompt-card" id="prompt-<?= $e($threadId) ?>" data-prompt-card data-prompt-text="<?= $e($search) ?>">
        <h2 class="prompt-card__title"><a href="/threads/<?= $e($threadId) ?>"><?= $e($title) ?></a></h2>
        <p class="meta prompt-card__meta"><?= $rowMeta($shared, 'root_post_created_at') ?><?php if ($likes > 0): ?> · <?= $e($plural($likes, 'like', 'likes')) ?><?php endif; ?> · <a href="/threads/<?= $e($threadId) ?>"><?= $replies > 0 ? $e($plural($replies, 'reply', 'replies')) : 'Discuss' ?></a></p>
<?= $partial('partials/agent_prompt.php', ['prompt' => [
    'id' => 'shared-' . $threadId,
    'label' => $title,
    'label_hidden' => true,
    'variant' => 'library',
    'text' => (string) $shared['body'],
]]) ?>
<?php if ($shared['prompt_tags'] !== []): ?>
        <p class="prompt-card__tags"><?php foreach ($shared['prompt_tags'] as $category): ?><a class="chip" href="<?= $e($promptHref($promptSort, (string) $category)) ?>">#<?= $e($category) ?></a> <?php endforeach; ?></p>
<?php endif; ?>
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
      });
    }
  })(document.currentScript.previousElementSibling);
</script>
