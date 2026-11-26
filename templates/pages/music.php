<?php
/**
 * Music: tracks people shared, the threads tagged #music, newest first (the
 * same list as /tags/music). Same HTML for every viewer; works without script.
 *
 * @var list<array<string, mixed>> $threads
 */
?>
<section class="stack board-list music-page">
  <article class="card music-share">
<?= $indent($partial('partials/thread_compose_form.php', [
    'compact' => false,
    'returnTo' => '/music/',
    'composeDraftScope' => 'music',
    'bodyPlaceholder' => 'Paste a link to a track',
]), 2) ?>
    <p class="meta" data-role="compose-identity-status" hidden></p>
  </article>
<?php if ($paginationError !== null): ?>
  <p class="board-list__error" role="alert"><?= $e($paginationError) ?></p>
<?php elseif ($threads === []): ?>
  <div class="board-empty">
    <h2>No tracks<?= $pagination['previous_href'] !== null ? ' on this page' : ' yet' ?></h2>
  </div>
<?php else: ?>
  <div class="thread-rows music-tracks">
<?php foreach ($threads as $thread): ?>
<?= $indent($partial('partials/thread_row.php', [
    'thread' => $thread,
    'rowPlayer' => $thread['music_link'] === null ? '' : $br($thread['music_link']),
    'rowPlayerLink' => $thread['music_link'],
]), 2) ?>
<?php endforeach; ?>
  </div>
<?php endif; ?>
<?= $indent($partial('partials/page_navigation.php', ['pagination' => $pagination, 'paginationLabel' => 'Music pages', 'previousLabel' => 'Newer tracks', 'nextLabel' => 'Older tracks']), 1) ?>
</section>
