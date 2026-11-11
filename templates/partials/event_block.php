<?php
$eventDate = (string) ($thread['event_date'] ?? '');
$eventEndDate = (string) ($thread['event_end_date'] ?? '');
$eventTime = (string) ($thread['event_time'] ?? '');
$eventLocation = (string) ($thread['event_location'] ?? '');
$eventLink = (string) ($thread['event_link'] ?? '');
// The poster's own words ("next Friday at 7"), shown beside the dates they filled while Event phrases is on.
$eventWhen = ($eventWhenEnabled ?? false) ? (string) ($thread['event_when'] ?? '') : '';
// Only render a clickable link for http(s) URLs - event_link is author-
// submitted free text with no scheme restriction at write time, so a
// non-http(s) value (e.g. a stray "javascript:" scheme) renders as plain
// escaped text instead of a clickable anchor.
$eventLinkIsHttp = $eventLink !== '' && preg_match('#^https?://#i', $eventLink) === 1;
?>
<?php if ($eventSupportEnabled && $eventDate !== ''): ?>
<p class="event-block" data-event-block>
  <span class="event-block__date">📅 <?= $e($eventDate) ?><?= $eventEndDate !== '' ? ' – ' . $e($eventEndDate) : '' ?><?= $eventTime !== '' ? ' at ' . $e($eventTime) : '' ?></span>
<?php if ($eventWhen !== ''): ?>
  <span class="event-block__when"> · “<?= $e($eventWhen) ?>”</span>
<?php endif; ?>
<?php if ($eventLocation !== ''): ?>
  <span class="event-block__location"> · <?= $e($eventLocation) ?></span>
<?php endif; ?>
<?php if ($eventLinkIsHttp): ?>
  <span class="event-block__link"> · <a href="<?= $e($eventLink) ?>"><?= $e($eventLink) ?></a></span>
<?php elseif ($eventLink !== ''): ?>
  <span class="event-block__link"> · <?= $e($eventLink) ?></span>
<?php endif; ?>
</p>
<?php endif; ?>
