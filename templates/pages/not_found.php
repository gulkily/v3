<section class="stack">
  <article class="card">
    <h1>Not Found</h1>
    <p>There is no page at this address. The link may be mistyped, or what was here has moved.</p>
    <p><a href="/">Back to the board</a></p>
<?php if ($reportUrl !== ''): ?>
    <p class="meta">If a link on this site or elsewhere sent you here, <a href="<?= $e($reportUrl) ?>" data-not-found-report>report the broken link</a> so it gets fixed.</p>
<?php endif; ?>
  </article>
<?php if ($recentThreads !== []): ?>
  <article class="card" data-not-found-recent>
    <h2>Recently active</h2>
    <ul>
<?php foreach ($recentThreads as $thread): ?>
      <li>
        <a href="/threads/<?= $e($thread['root_post_id']) ?>"><?= $e($threadTitle($thread)) ?></a>
        <span class="meta"><?= (int) $thread['reply_count'] ?> <?= (int) $thread['reply_count'] === 1 ? 'reply' : 'replies' ?></span>
      </li>
<?php endforeach; ?>
    </ul>
  </article>
<?php endif; ?>
<?php if ($labels !== []): ?>
  <article class="card" data-not-found-labels>
    <h2>Popular labels</h2>
    <p>
<?php foreach ($labels as $label): ?>
      <a class="chip" href="<?= $e($label['href']) ?>">#<?= $e($label['tag']) ?></a>
<?php endforeach; ?>
    </p>
  </article>
<?php endif; ?>
</section>
