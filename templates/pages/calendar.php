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
  <article class="card">
    <h1><?= $calendarTag === null ? 'Calendar' : '#' . $e($calendarTag) ?></h1>
<?php if (!$calendarEnabled): ?>
    <p>Events are off on this site. <a href="/tools/feature-flags/#flag-FORUM_EVENT_SUPPORT_ENABLED">Feature Flags</a></p>
<?php else: ?>
    <nav class="calendar-nav meta" aria-label="Months">
      <a href="<?= $e($calendarPrevious) ?>" rel="prev">← Earlier</a>
      <a href="<?= $e($calendarNext) ?>" rel="next">Later →</a>
<?php if ($calendarTag !== null): ?>
      <a href="/calendar">All events</a>
<?php endif; ?>
    </nav>
<?php endif; ?>
  </article>
<?php if ($calendarEnabled): ?>
<?php foreach ($calendarMonths as $month): ?>
  <article class="card calendar-month">
    <div class="calendar-scroll">
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
    </div>
  </article>
<?php endforeach; ?>
<?php endif; ?>
</section>
