<section class="stack thread-list">
<?php foreach ($group['threads'] as $thread): ?>
<?= $indent($partial('partials/thread_card.php', [
    'thread' => $thread,
    'showLabels' => true,
]), 1) ?>
<?php endforeach; ?>
</section>
