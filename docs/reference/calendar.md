# Calendar

The calendar shows event threads by day. An event is an ordinary thread whose root
carries an event date; the calendar adds no record family of its own. It needs
event support (`FORUM_EVENT_SUPPORT_ENABLED`); while that is off, the calendar pages
say so and link to Feature Flags.

## Pages

| Page | Shows |
| --- | --- |
| `/calendar` | Two months of all event threads, starting with this month (UTC). `?month=YYYY-MM` starts elsewhere; Earlier and Later move one month. |
| `/calendar/<tag>` | The same grid, limited to events with that tag as a board tag or a label, as `/tags/<tag>` counts them. The link can be shared; `/calendar/couches` is the couch calendar. |
| `/calendar/<date>`, `/calendar/<tag>/<date>` | That day's events and a composer for adding one. |

A path segment shaped like `YYYY-MM-DD` is always a day; anything else is a tag.

On a wide screen each day lists up to three events by title and author, then
`+N more`. On a phone each day shows only how many events it has; the day page has
the titles. A day with no events is empty, a day with any unsettled event is open,
and a day with a confirmed event is marked confirmed. Declined events do not appear
on the calendar but stay in their thread.

The calendar has no navigation entry; reach it by its link.

## Adding an event

Open a day and post. The composer is the one used everywhere else, with the day
already set as the event's date and, on a tag calendar, that tag already set. Fill in
the last day under **Until** for a multi-day event; leave it empty for one day. Time,
Place and Link are optional. A post that is refused keeps its draft and shows the
reason, as on `/compose/thread`.

With Event phrases on (`FORUM_EVENT_WHEN_ENABLED`), **When** takes the event's
timing in words: type `Friday 7pm`, `Nov 14-16` or `this weekend` and press
**Fill** (or Enter). Date, Until and Time fill in place, read with your own date;
check them before posting. On a day page the date stays fixed and Fill sets only
Until and Time. Phrases the built-in reader does not know go to the site's LLM for
approved members when one is configured. The words are kept with the event and
shown after its dates ([date phrase](../concepts/date_phrase.md)).

Events can also be posted from `/compose/thread` (open **Event**) or through the
agent API ([events](agent_api.md#events)).

## Confirming or declining

A root-approved operator settles an event by writing a line on its own in any post
of the event's thread, the root included:

```
event: confirmed
```

or `event: declined`. The latest such line from a current operator decides; quoted
lines (`> event: confirmed`) never count, and lines from anyone else are ordinary
text. The state covers the whole span; there is no partial confirmation. The thread
page shows `Event: confirmed` or `Event: declined` under the title. To record
something arranged elsewhere, an operator can post the event with the line in the
same post.

The state is worked out from the posts each time a page is read, so a rebuild or a
retracted post changes it without anything else to update. A line counts only while
its post is not retracted, its attribution is verified, and its author is still an
approved, unretired operator.

## Access

Calendar pages are ordinary pages. On a members-only instance they need an approved
viewer like every other page: a signed-in member who is not yet approved is sent to
the Lobby and can come back once approved, and a signed-out visitor gets the usual
sign-in or Lobby page ([access modes](authentication_modes.md)). On a public
instance they are public, like the threads they show. Every viewer receives the same
markup.

## Records and storage

`Event-End-Date` is an optional root header described in the
[post record spec](../specs/canonical_post_record_v1.md#event-headers); a release
that predates it reads such an event as one day. The read model stores it in
`threads.event_end_date` ([schema 49](../specs/read_model_schema_v1.md#version-49--event-end-date)).
`Event-When` holds the poster's words; it is stored in `threads.event_when`
([schema 54](../specs/read_model_schema_v1.md#version-54--event-phrase)).
