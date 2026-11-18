<?php
// Thread page only: add this one event to a calendar. The .ics file suits any calendar app.
$googleEventUrl = ($eventSupportEnabled && ($calendarFeedEnabled ?? false))
    ? \ForumRewrite\Calendar\CalendarIcs::googleEventUrl($thread)
    : null;
?>
<?php if ($googleEventUrl !== null): ?>
<p class="meta event-calendar-links" data-event-calendar-links><a href="<?= $e($googleEventUrl) ?>" rel="noopener">Add to Google Calendar</a> · <a href="/threads/<?= $e(rawurlencode((string) $thread['root_post_id'])) ?>.ics" download>.ics</a></p>
<?php endif; ?>
