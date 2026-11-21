<?php
// Prompts: the library of shared #prompt threads.
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
  <p id="start"><a href="/agent/start/">Use with your agent</a></p>
</section>
<?= $partial('partials/agent_prompt_script.php') ?>
