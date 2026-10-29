<section class="stack board-list">
<?= $indent($partial('partials/board_toolbar.php', [
    'viewOptions' => $viewOptions,
    'sortOptions' => $sortOptions,
    'sortLabel' => $sortLabel,
]), 1) ?>
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
