<?php
/**
 * One calendar day: its events, then the shared composer with the day fixed and, on a
 * tag calendar, the tag preset. Every viewer gets the same markup.
 *
 * @var bool $calendarEnabled
 * @var ?string $calendarTag
 * @var string $calendarBase
 * @var string $calendarDate
 */
?>
<section class="stack calendar-day-page compose-page" data-compose-root data-unicode-authored-text="<?= ($unicodeAuthoredTextEnabled ?? false) ? '1' : '0' ?>" data-emoji-authored-text="<?= ($emojiAuthoredTextEnabled ?? false) ? '1' : '0' ?>">
  <article class="card">
    <h1><?= $e($calendarDate) ?><?= $calendarTag !== null ? ' #' . $e($calendarTag) : '' ?></h1>
<?php if (!$calendarEnabled): ?>
    <p>Events are off on this site. <a href="/tools/feature-flags/#flag-FORUM_EVENT_SUPPORT_ENABLED">Feature Flags</a></p>
<?php else: ?>
    <p class="meta"><a href="<?= $e($calendarMonthLink) ?>">← Calendar</a></p>
<?php if ($calendarDay['entries'] !== []): ?>
    <ul class="calendar-day-entries">
<?php foreach ($calendarDay['entries'] as $entry): ?>
<?php
$details = [];
if ((string) ($entry['event_end_date'] ?? '') !== '') {
    $details[] = $e($entry['event_date'] . ' – ' . $entry['event_end_date']);
}
foreach (['event_time', 'event_location'] as $field) {
    if ((string) ($entry[$field] ?? '') !== '') {
        $details[] = $e($entry[$field]);
    }
}
if ($entry['event_state'] !== null) {
    $details[] = $e(ucfirst((string) $entry['event_state']));
}
?>
      <li data-calendar-entry="<?= $e($entry['root_post_id']) ?>"><a href="/threads/<?= $e($entry['root_post_id']) ?>"><?= $e($threadTitle($entry)) ?></a> <span class="meta"><?= $author($entry) ?><?= $details !== [] ? ' · ' . implode(' · ', $details) : '' ?></span></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php endif; ?>
  </article>
<?php if ($calendarEnabled): ?>
  <article class="card">
<?= $indent($partial('partials/thread_compose_form.php', [
    'compact' => false,
    'composerEventDateFixed' => true,
    'composeDraftScope' => 'calendar:' . ($calendarTag ?? '') . ':' . $calendarDate,
]), 2) ?>
    <p class="meta" data-role="compose-identity-status" hidden></p>
  </article>
<?php endif; ?>
</section>
