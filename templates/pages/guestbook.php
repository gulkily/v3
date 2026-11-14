<?php
/**
 * Guestbook: the guestbook thread's top-level replies as a paper book, two
 * facing pages per spread. See GuestbookPageController.
 *
 * @var string $guestbookThreadId
 * @var list<array{number:int, entries:list<array<string, mixed>>}> $spread
 * @var bool $isLastSpread
 * @var ?string $earlierHref
 * @var ?string $laterHref
 * @var ?string $latestHref
 */
$entryDate = static function (string $value): string {
    try {
        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('j F Y');
    } catch (\Exception) {
        return $value;
    }
};
?>
<section class="guestbook" aria-label="Guestbook">
  <div class="guestbook__book">
<?php foreach ($spread as $side => $bookPage): ?>
    <div class="guestbook__page guestbook__page--<?= $side === 0 ? 'left' : 'right' ?>" id="page-<?= (int) $bookPage['number'] ?>">
      <header class="guestbook__page-head">
<?php if ($side === 0): ?>
        <h1 class="guestbook__title">Guestbook</h1>
<?php endif; ?>
        <span class="guestbook__page-number">page <?= (int) $bookPage['number'] ?></span>
      </header>
<?php foreach ($bookPage['entries'] as $entry): ?>
      <article class="guestbook__entry" id="post-<?= $e($entry['post_id']) ?>">
        <time class="guestbook__date" datetime="<?= $e($entry['created_at']) ?>"><?= $e($entryDate((string) $entry['created_at'])) ?></time>
        <div class="guestbook__note body<?= $isMarkdownPost($entry) ? ' post-markdown' : '' ?>"><?= $renderPostBody($entry) ?></div>
        <p class="guestbook__signature">— <?= $author($entry) ?></p>
      </article>
<?php endforeach; ?>
    </div>
<?php endforeach; ?>
  </div>
  <nav class="guestbook__turns" aria-label="Pages">
<?php if ($earlierHref !== null): ?>
    <a class="guestbook__turn" rel="prev" href="<?= $e($earlierHref) ?>">← Earlier pages</a>
<?php endif; ?>
<?php if ($laterHref !== null): ?>
    <a class="guestbook__turn" rel="next" href="<?= $e($laterHref) ?>">Later pages →</a>
<?php endif; ?>
<?php if ($latestHref !== null): ?>
    <a class="guestbook__turn" href="<?= $e($latestHref) ?>">Latest page</a>
<?php endif; ?>
  </nav>
</section>
