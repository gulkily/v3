<?php
/**
 * Staged: signed posts the site kept but has not published, with why and the post ID to
 * replace them under. Authors see their own; operators see everyone's.
 *
 * @var bool $signedIn
 * @var bool $operator
 * @var bool $discardFailed
 * @var list<array<string,mixed>> $items
 */
?>
<section class="stack staged">
  <article class="card">
    <h1>Staged</h1>
<?php if (!$signedIn): ?>
    <div data-staged-access data-access-state="guest">
      <p data-access-for="guest">Nothing staged.</p>
      <p data-access-for="failed" role="alert" hidden>Couldn’t sign you in. <button type="button" data-action="access-retry">Retry</button></p>
    </div>
<?php elseif ($items === []): ?>
    <p>Nothing staged.</p>
<?php else: ?>
    <p class="meta">Only <?= $operator ? 'authors and operators' : 'you and operators' ?> see these. Send a fixed version with the same post ID to publish it.</p>
<?php endif; ?>
<?php if ($discardFailed): ?>
    <p role="alert">Couldn’t discard. Nothing was removed.</p>
<?php endif; ?>
  </article>
<?php foreach ($items as $item): ?>
  <article class="card staged-item" data-post-id="<?= $e((string) $item['post_id']) ?>">
    <p class="meta">
      <span class="chip chip--pending">Held</span>
<?php if ($item['thread_id'] !== null): ?>
      Reply to <a href="/threads/<?= $e(rawurlencode((string) $item['thread_id'])) ?>"><?= $e((string) ($item['thread_subject'] ?? $item['thread_id'])) ?></a>
<?php else: ?>
      New thread
<?php endif; ?>
      · <?= $shortDate((string) $item['staged_at']) ?>
<?php if ($operator): ?>
      · <?= $e((string) ($item['author_label'] ?? $item['author_identity_id'])) ?>
<?php endif; ?>
    </p>
<?php if (($item['subject'] ?? '') !== ''): ?>
    <h2><?= $e((string) $item['subject']) ?></h2>
<?php endif; ?>
    <p><?= $e(mb_strimwidth(trim((string) $item['body']), 0, 240, '…', 'UTF-8')) ?></p>
    <p role="note"><strong><?= $e((string) $item['reason']) ?></strong>
<?php foreach ($item['chars'] as $char): ?>
      <code><?= $e((string) $char['codepoint']) ?></code> at <?= $e((string) ($item['field'] !== '' ? $item['field'] . ' ' : '')) ?>position <?= $e((string) $char['index']) ?>.
<?php endforeach; ?>
    </p>
    <p class="meta staged-item__id"><code><?= $e((string) $item['post_id']) ?></code></p>
    <form method="post" action="/staged/" class="button-row button-row-natural">
      <input type="hidden" name="post_id" value="<?= $e((string) $item['post_id']) ?>">
      <button type="submit">Discard</button>
    </form>
  </article>
<?php endforeach; ?>
</section>
<?php if (!$signedIn): ?>
<?= $partial('partials/account_state_script.php') ?>
<script>
  // Decided before paint: a browser holding a key signs in (heading only meanwhile); a guest sees the empty page.
  (function () {
    var root = document.querySelector('[data-staged-access]');
    var state = window.ForumAccountState.accountState(root);
    if (state !== 'guest') {
      root.dataset.accessState = 'checking';
      root.querySelector('[data-access-for="guest"]').hidden = true;
    }
  })();
</script>
<?php endif; ?>
