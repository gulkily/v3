<?php
/**
 * Month grids over event threads. Every viewer gets the same markup.
 *
 * @var bool $calendarEnabled
 * @var ?string $calendarTag
 * @var string $calendarBase
 */
$weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
?>
<section class="stack calendar">
<?php if ($calendarTag === null): ?>
  <h1 class="visually-hidden">Calendar</h1>
<?php endif; ?>
<?php if (!$calendarEnabled || $calendarTag !== null): ?>
  <article class="card">
<?php if ($calendarTag !== null): ?>
    <h1>#<?= $e($calendarTag) ?></h1>
<?php endif; ?>
<?php if (!$calendarEnabled): ?>
    <p>Events are off on this site. <a href="/tools/feature-flags/#flag-FORUM_EVENT_SUPPORT_ENABLED">Feature Flags</a></p>
<?php endif; ?>
  </article>
<?php endif; ?>
<?php if ($calendarEnabled): ?>
<?php foreach ($calendarMonths as $monthIndex => $month): ?>
  <article class="card calendar-month">
<?php if ($monthIndex === array_key_first($calendarMonths)): ?>
    <nav class="calendar-nav meta" aria-label="Months">
      <a href="<?= $e($calendarPrevious) ?>" rel="prev">← Earlier</a>
      <a href="<?= $e($calendarBase) ?>" data-calendar-today hidden>Today</a>
      <a href="<?= $e($calendarNext) ?>" rel="next">Later →</a>
<?php if ($calendarTag !== null): ?>
      <a href="/calendar">All events</a>
<?php endif; ?>
      <a href="#" role="button" class="calendar-titles-toggle" data-calendar-titles aria-pressed="false" hidden>Show titles</a>
    </nav>
<?php endif; ?>
    <?= \ForumRewrite\View\ZoomTable::open('calendar', 0, $month['label'], 'text') ?>
      <table class="calendar-grid">
        <caption><?= $e($month['label']) ?></caption>
        <thead>
          <tr><?php foreach ($weekdays as $weekday): ?><th scope="col"><?= $e($weekday) ?></th><?php endforeach; ?></tr>
        </thead>
        <tbody>
<?php foreach ($month['weeks'] as $week): ?>
          <tr>
<?php foreach ($week as $cell): ?>
<?php if ($cell === null): ?>
            <td class="calendar-day calendar-day--outside"></td>
<?php else: ?>
<?php $shown = array_slice($cell['entries'], 0, $calendarEntriesPerCell); $more = count($cell['entries']) - count($shown); ?>
            <td class="calendar-day calendar-day--<?= $e($cell['mark']) ?>" data-calendar-day="<?= $e($cell['date']) ?>" data-calendar-mark="<?= $e($cell['mark']) ?>">
              <a class="calendar-day__number" href="<?= $e($calendarBase . '/' . $cell['date']) ?>" aria-label="<?= $e($cell['date'] . ($cell['mark'] === 'empty' ? '' : ', ' . $cell['mark'] . ', ' . count($cell['entries']) . (count($cell['entries']) === 1 ? ' event' : ' events'))) ?>"><?= (int) $cell['day'] ?><?php if ($cell['entries'] !== []): ?><span class="calendar-day__count" aria-hidden="true"><?= count($cell['entries']) ?></span><?php endif; ?></a>
<?php if ($shown !== []): ?>
              <ul class="calendar-day__entries">
<?php foreach ($shown as $entry): ?>
                <li class="calendar-entry<?= $entry['event_state'] === 'confirmed' ? ' calendar-entry--confirmed' : '' ?>"><a href="/threads/<?= $e($entry['root_post_id']) ?>"><?= $entry['event_state'] === 'confirmed' ? '<span class="calendar-entry__state" aria-label="confirmed">✓</span> ' : '' ?><?= $e($threadTitle($entry)) ?></a></li>
<?php endforeach; ?>
              </ul>
<?php endif; ?>
<?php if ($more > 0): ?>
              <a class="calendar-day__more meta" href="<?= $e($calendarBase . '/' . $cell['date']) ?>">+<?= $more ?> more</a>
<?php endif; ?>
            </td>
<?php endif; ?>
<?php endforeach; ?>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table>
    <?= \ForumRewrite\View\ZoomTable::close() ?>
  </article>
<?php endforeach; ?>
<?php endif; ?>
</section>
<?php if ($calendarEnabled): ?>
<script>
  // A phone shows counts by default; the viewer can switch to tiny titles, remembered in this browser.
  (function () {
    var section = document.querySelector('.calendar');
    var button = document.querySelector('[data-calendar-titles]');
    var key = 'calendar-titles';
    var show = function (on) {
      section.classList.toggle('calendar--titles', on);
      button.setAttribute('aria-pressed', on ? 'true' : 'false');
      button.textContent = on ? 'Hide titles' : 'Show titles';
    };
    try { show(localStorage.getItem(key) === 'on'); } catch (e) { show(false); }
    button.hidden = false;
    button.addEventListener('click', function (event) {
      event.preventDefault();
      var on = !section.classList.contains('calendar--titles');
      show(on);
      try { if (on) { localStorage.setItem(key, 'on'); } else { localStorage.removeItem(key); } } catch (e) {}
    });
  })();
  // Before paint, in the viewer's own time zone: mark today, or offer Today when its month is not shown.
  (function () {
    var now = new Date();
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var today = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
    var cell = document.querySelector('[data-calendar-day="' + today + '"]');
    if (cell) {
      cell.classList.add('calendar-day--today');
      cell.querySelector('.calendar-day__number').setAttribute('aria-current', 'date');
      return;
    }
    var link = document.querySelector('[data-calendar-today]');
    link.setAttribute('href', link.getAttribute('href') + '?month=' + today.slice(0, 7));
    link.hidden = false;
  })();
</script>
<?php endif; ?>
