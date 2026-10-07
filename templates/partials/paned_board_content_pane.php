<?php
/**
 * @var array<int, array<string, mixed>> $threads
 * @var array<string, array<int, array{post: array<string, mixed>, children: array}>> $replyTreesByThreadId
 * @var string $selectedThreadId
 */
$selectedThreadId ??= '';
?>
<div class="paned-content-pane" data-paned-board-content-pane>
  <article class="paned-content-post" data-paned-board-content-placeholder<?= $selectedThreadId !== '' ? ' hidden' : '' ?>>
    <div class="paned-content-head">
      <div class="paned-content-subject">No thread selected</div>
    </div>
    <div class="body">Select a thread from the list to preview it here.</div>
  </article>
<?php foreach ($threads as $thread): ?>
<?php $isSelectedThread = $selectedThreadId !== '' && (string) $thread['root_post_id'] === $selectedThreadId; ?>
<?= $partial('partials/paned_board_content_article.php', [
    'thread' => $thread,
    'replyTree' => $replyTreesByThreadId[$thread['root_post_id']] ?? [],
    'isSelectedThread' => $isSelectedThread,
]) ?>
<?php endforeach; ?>
<?= $indent($partial('partials/paned_board_compose_panel.php'), 1) ?>
</div>
