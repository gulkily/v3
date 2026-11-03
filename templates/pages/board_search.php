<section class="stack board-list board-search">
  <form class="board-toolbar" action="/search" method="get" role="search">
    <input type="search" class="board-toolbar__search" name="q" value="<?= $e($term) ?>" placeholder="Search threads" aria-label="Search threads" autocomplete="off" spellcheck="false">
    <button type="submit">Search</button>
  </form>
<?php if ($paginationError !== null): ?>
  <p class="board-list__error" role="alert"><?= $e($paginationError) ?></p>
<?php elseif ($term !== '' && $threads === []): ?>
  <div class="board-empty">
    <h2>No matching threads</h2>
  </div>
<?php endif; ?>
<?php if ($threads !== []): ?>
  <div class="thread-rows">
<?php foreach ($threads as $thread): ?>
<?= $indent($partial('partials/thread_row.php', ['thread' => $thread]), 2) ?>
<?php endforeach; ?>
  </div>
<?php endif; ?>
<?= $indent($partial('partials/page_navigation.php', ['pagination' => $pagination, 'paginationLabel' => 'Search result pages', 'previousLabel' => 'Newer threads', 'nextLabel' => 'Older threads']), 1) ?>
</section>
