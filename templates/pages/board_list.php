<section class="stack board-list">
  <div class="board-toolbar">
    <nav class="board-filter" aria-label="Show">
<?php foreach ($viewOptions as $option): ?>
      <a class="nav-link<?= $option['is_active'] ? ' is-active' : '' ?>" href="<?= $e($option['href']) ?>" data-nav-key="<?= $e($option['key']) ?>"<?= $option['is_active'] ? ' aria-current="page"' : '' ?>><?php if ($option['key'] === 'liked'): ?><span class="like-mark" aria-hidden="true"></span><?php endif; ?><?= $e($option['label']) ?></a>
<?php endforeach; ?>
    </nav>
    <details class="board-sort" data-role="board-sort">
      <summary class="nav-link"><span class="visually-hidden">Sort by </span><?= $e($sortLabel) ?></summary>
      <div class="board-sort__menu">
<?php foreach ($sortOptions as $option): ?>
        <a class="nav-link<?= $option['is_active'] ? ' is-active' : '' ?>" href="<?= $e($option['href']) ?>" data-nav-key="<?= $e($option['key']) ?>"<?= $option['is_active'] ? ' aria-current="true"' : '' ?>><?= $e($option['label']) ?></a>
<?php endforeach; ?>
      </div>
    </details>
    <a class="nav-link board-tags-link" href="/tags/">Tags</a>
  </div>
  <article class="card inline-reply-composer compact-thread-compose" data-compose-root data-pending-thread-position="after" data-unicode-authored-text="<?= $unicodeAuthoredTextEnabled ? '1' : '0' ?>" data-emoji-authored-text="<?= $emojiAuthoredTextEnabled ? '1' : '0' ?>">
    <div class="inline-reply-expanded" data-inline-thread-compose data-inline-thread-expanded="0">
        <p class="meta inline-reply-identity-status" data-role="compose-identity-status" hidden></p>
<?= $indent($partial('partials/thread_compose_form.php', [
    'boardTags' => 'general',
    'subject' => '',
    'body' => '',
    'notice' => null,
    'error' => null,
    'compact' => true,
    'bodyPlaceholder' => $composerPrompt,
]), 4) ?>
    </div>
  </article>
<?php if ($paginationError !== null): ?>
  <p class="board-list__error" role="alert"><?= $e($paginationError) ?></p>
<?php elseif ($pinnedThreads === [] && $threads === []): ?>
  <div class="board-empty">
<?php if ($view === 'liked'): ?>
    <h2>No liked threads yet</h2>
    <p>Threads you like will show up here.</p>
    <a class="nav-link" href="/threads/?view=all&amp;sort=<?= $e(rawurlencode($sort)) ?>">Show all threads</a>
<?php else: ?>
    <h2>No threads yet</h2>
<?php endif; ?>
  </div>
<?php endif; ?>
<?php if ($pinnedThreads !== []): ?>
  <h2 class="section-label section-label--pinned">Pinned</h2>
  <div class="thread-rows pinned-list">
<?php foreach ($pinnedThreads as $thread): ?>
<?= $indent($partial('partials/thread_row.php', ['thread' => $thread]), 2) ?>
<?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if ($threads !== []): ?>
<?php if ($pinnedThreads !== []): ?>
  <h2 class="section-label">Latest</h2>
<?php endif; ?>
  <div class="thread-rows">
<?php foreach ($threads as $thread): ?>
<?= $indent($partial('partials/thread_row.php', ['thread' => $thread]), 2) ?>
<?php endforeach; ?>
  </div>
<?php endif; ?>
<?= $indent($partial('partials/page_navigation.php', ['pagination' => $pagination, 'paginationLabel' => 'Board pages', 'previousLabel' => 'Newer threads', 'nextLabel' => 'Show older threads']), 1) ?>
</section>
<?php if (!empty($listingQuery)): ?>
<footer class="listing-query-footer">
<?= $partial('partials/query_action.php', $listingQuery) ?>
</footer>
<?php endif; ?>
