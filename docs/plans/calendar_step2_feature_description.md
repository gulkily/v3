> **Feature plan:** [Step 1](./calendar_step1_solution_assessment.md) · [Step 2](./calendar_step2_feature_description.md) · [Step 3](./calendar_step3_development_plan.md) · [Step 4](./calendar_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/calendar`; [host ledger](./effort_estimation_trial.md#cycle-v3calendar). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 1 Option A approved (Revisions 1 and 2); Step 2 v3 proposed (general calendar, with the couch flow as one use of it); implementation unapproved. Earlier versions: v1 couch-only single nights (0.65), v2 added offline marking and ranges (0.80).

**Cycle estimate: 0.90**

Mockup: [calendar_mockups.html](./calendar_mockups.html) (open locally in a browser).

Roles: a **member** is any approved member posting an event. An **operator** is an active root-approved operator, which on the maintainer's instance is the maintainer. The couch use: the maintainer is being hosted, members with couches post an event on the nights they can host, the maintainer confirms one.

## Problem

Event threads already carry a date, but nothing shows them by day, so members cannot see what is on, offer a night, or tell which nights are settled.

## User Stories

- As a member, I want to see a month at a glance with every event on it so that I know what is happening when.
- As a member, I want to open a day and add an event there with one post so that I do not have to type the date by hand.
- As a member, I want a calendar of just the events with one tag, such as `#couches`, with a link I can send, so that one calendar page is the couch calendar and another is everything else.
- As a host with a couch, I want to offer one or more nights with a note and the place so that the maintainer can pick me without messaging back and forth.
- As the maintainer, I want each offer to arrive as a normal thread under the host's username and to settle it with one reply so that the grid shows where I sleep.
- As a host, I want to see which nights are already offered or confirmed so that I do not offer a night someone else has.

## Core Requirements

- **The calendar is a view over event threads.** `/calendar` shows a month grid; each day lists the root threads whose event span covers it, by subject or first line and author. Default view is this month and next; links move to earlier and later months. No new record family: an event is a thread root with the existing `Event-Date`, optionally `Event-Location`, and a new optional inclusive `Event-End-Date` root header (absent means one day). The end date is an additive post record change.
- **A tag has its own calendar.** `/calendar/<tag>` shows the same grid limited to events carrying that tag (board tag or label, as tag pages count them), with its own day pages at `/calendar/<tag>/<date>`. The link is shareable; the couch calendar is `/calendar/couches`. A path segment that is a date is a day; anything else is a tag.
- **A day page adds an event with one post.** `/calendar/<date>` and `/calendar/<tag>/<date>` list that day's events and show the shared composer with the first day fixed, the last day optional, and, inside a tag's calendar, that tag preset. The couch offer is this: open a day on the couch calendar, write a note, optionally the place and the last night, send.
- **An operator settles an event with one line.** A line `event: confirmed` or `event: declined` in any post of the event's thread, root included, from an active operator, sets the event's state; the latest valid line wins; quoted lines never count; no line means plain. The state covers the whole span; no partial confirmation. The thread page shows the state under the title, as work items do. The maintainer records an arrangement made elsewhere by posting the event with the line in the same post.
- **Day state is derived.** A day with a confirmed event is marked confirmed; with only plain events it is marked open (for the couch use: offered); with none it is empty (available). Declined events are not shown on the grid but stay in their thread. Nothing is stored beyond the posts, so a rebuild cannot disagree.
- **Access follows the instance.** The calendar and its day pages are ordinary pages: on a members-only instance they require an approved viewer like everything else, and on a public instance they are public like the threads they show. Event support must be on; when it is off, the calendar says so to operators with the flag link and offers nothing else.

## Delivery Scope and Completion Boundary

Work type: application change, plus the post record spec addition for `Event-End-Date`, the read-model schema update it needs (one column, version bump, spec, prior-version rebuild test, as `CONTRIBUTING.md` requires), the reference doc for the calendar, concept inventory entries, this cycle's records and study-ledger bookkeeping. No new dependencies or record families; a derived table is acceptable if request-time derivation is too slow. Out of scope: navigation entry and theme icons, notifications, time-of-day ordering within a day, partial confirmation of a span, recurring events, combining several tags in one view, calendar export or sync, enforcing one confirmed event per day at write time, the 60-day horizon as an enforced limit (it is the default view only).

Completion boundary: on the maintainer's instance with event support on, a member opens `/calendar/couches`, moves to a month, opens a day, posts an event (tagged `couches` by the preset) for that day or through a later day with a note and optional place, and every day in the span lists it as open. An operator replies `event: confirmed`; the grid marks the span confirmed. Declining removes it from the grid. The maintainer records an arrangement made elsewhere with one post of their own carrying the line. A failed send keeps the draft, as the composer already does. Release condition: the flow verified with JavaScript on and off, on phone and desktop widths, in the text themes and the three icon themes, with the rebuild from the prior schema version producing the same grid.

Known environment: the maintainer's instance is members-only, and hosts arrive through an API-issued capped invitation link ([invitation entry](../reference/invitation_entry.md#capped-invitations-through-the-api)). A host who opens `/calendar` before approval is sent to the Lobby by the existing gate and can return after approval; success is the grid rendering for them. If a post is refused, the composer shows the reason and keeps the draft, and the host retries from the same day page. Production verification on the maintainer's instance is recorded as done or explicitly not done.

## Shared Component Inventory

- **Event headers and event block** (`Event-Date`, `Event-Location`, `Event-Time` and `Event-Link` parsing in `PostRecordParser`, the event columns on the threads table, `templates/partials/event_block.php`, the composer's Event disclosure): extend with the optional end date in the spec, parser, write path, one threads column, the disclosure's Until field and the block's span display. Everything else reused unchanged; the day page passes the day as the composer's event date, as `/compose/thread` already accepts.
- **Shared composer and thread write path** (`templates/partials/composer.php`, `/compose/thread`, signed prepare/create routes, drafts and Outbox): reuse unchanged; the day page is another placement with the date fixed.
- **Work status directive and thread status line** (`WorkStatusDirective`, `WorkItems`, the status line in `templates/pages/thread.php`): reuse the pattern for `event:` lines with the operator check from `ApprovalPolicy::isActiveOperator`, read from the root as well as replies; a sibling directive class rather than widening the work vocabulary.
- **Members-only gate** (`membersOnlyRequestAllowed`, Lobby redirect): reuse unchanged; no route-level gate of its own (dropped from v2).
- **Thread listing queries and effective tags** (`BoardListingQuery`, tag pages, [effective tags](../concepts/effective_tags.md)): reuse the thread row shape for day entries and the same tag matching as `/tags/<tag>`; a date-range query over the threads table, optionally by tag, is new. The composer's `board_tags` query value presets the tag on a tag calendar's day page.
- **Read-model schema and rebuild** (`ReadModelMetadata::SCHEMA_VERSION`, `docs/specs/read_model_schema_v1.md`, `./v3 rebuild`): extend per the contributing rules for the one new column.
- **Feature flags page**: reuse unchanged for turning event support on.
- **New:** the month grid page with month navigation, the day page, and the day-state derivation. No existing page renders dates as a grid.

## Risks

- **Grid density.** A public instance with many events on one day makes a cell tall or crowded. Validate with a day holding five events in the layout checks; cap entries per cell with a "more" link to the day page if needed.
- **Operator check for the directive.** Work status accepts any approved member; event state must accept only an active operator. Validate with a test that a member's `event: confirmed` is ignored before dependent stages.
- **Overlapping spans.** Two offers covering the same nights, one confirmed and one declined, must render confirmed, not both; validate with overlapping ranges.
- **Format and schema change.** Validate how `PostRecordParser` treats an unexpected `Event-End-Date` before the first record is written, and keep the header optional so existing records are unchanged. The schema bump follows the prior-version rebuild test rule. Older application versions reading a new record are handled the way the parser already handles unknown headers, to be confirmed in Step 3.

## Simple User Flow

1. A member opens `/calendar`, or a tag's calendar such as `/calendar/couches`, and sees this month and next, with each day's events listed and confirmed ones marked.
2. They open a day and see its events and the composer with the date set, and the tag preset on a tag calendar.
3. They write the note, optionally the place and a last day, and send; the thread appears under their username and the days list it as open.
4. An operator opens the thread and replies `event: confirmed` (or `event: declined`).
5. The grid marks the days confirmed (or drops the event).
6. For something arranged elsewhere, the maintainer opens the first day, writes the note with `event: confirmed` on its own line, sets the last day, and sends.

## Success Criteria

- A post from a day page creates a thread with that day's `Event-Date`, and `Event-End-Date` when a last day was given, under the member's username, with JavaScript on and off.
- `/calendar/couches` shows only events tagged `couches`, by board tag or label, and a day page opened from it posts with that tag preset.
- `/calendar` shows empty, open and confirmed days correctly for single days and spans, after confirm and decline replies, after an operator's own confirmed post, after moving to other months, and after `./v3 rebuild` from the prior schema version.
- An `event: confirmed` line from a non-operator or a quoted line changes nothing; an operator's line in the root counts.
- Records without `Event-End-Date` parse and render exactly as before.
- Phone and desktop renders of both pages, across themes, show no overflow or post-load layout shift, including a day with five events.

## Concepts Touched

[Shared composer](../concepts/shared_composer.md) used unchanged (new placement); [work item status](../concepts/work_item_status.md) pattern reused, unchanged; [viewer session](../concepts/viewer_session.md) and [invitation lifecycle](../concepts/invitation_lifecycle.md) used unchanged; [tag browsing](../concepts/tag_browsing.md) used unchanged; [read model rebuild](../concepts/read_model_rebuild.md) changed (one column, schema version). Proposed inventory additions at implementation: *calendar* (grid, day page, derivation, operator directive) and *post event metadata* (the existing event headers, flag and block, plus the end date; currently undocumented).

## Study Checkpoint

2026-11-04T14:30:00Z; scope v1/A, original baseline `f41f656936e740809242a5d8e1c646068afddc28`, R1/P2; frozen hashes verified earlier this session, unchanged. User explicitly approved Step 1. Whole-cycle **0.65** after inspecting the compose route's event prefill, the operator policy, the members-only gate and the work status reader. Claude, single exposed rater; pristine intake missing.

2026-11-04T14:45:00Z revision: roles corrected (the maintainer is hosted; members host); `Event-Location` reused for the place. Model, scope and 0.65 unchanged.

2026-11-04T16:05:00Z revision, scope v2/A: offline marking through the operator's own post carrying the directive, and spans through an additive inclusive `Event-End-Date` header with whole-span approval. Whole-cycle **0.80**.

2026-11-04T17:00:00Z revision, scope v3/A: on the maintainer's request the calendar is general. It shows every event thread by day with month navigation; the couch flow is one use of it with no couch-specific code. Directive renamed `event: confirmed` / `event: declined`; declined events leave the grid; the route-level gate is dropped in favour of the instance's existing rule; the 60-day horizon becomes the default view, not a limit. Whole-cycle **0.85**, revised from 0.80 for month navigation, the date-range listing and denser-cell verification, less the dropped gate.

2026-11-04T17:20:00Z addition to v3, before handoff: the maintainer asked for per-tag calendars with shareable links, such as `/calendar/couches`, whose day pages preset the tag. Added as a requirement; combining tags stays out of scope. Whole-cycle **0.90**. v1 0.65 and v2 0.80 preserved above. Same original baseline/A/R1/P2, single exposed Claude rater; no implementation, measured labor or accepted outcome. Await Approved Step 2.

*— Claude · 2026-11-04 UTC · Model: claude-fable-5-1*

*— Revised by Claude · 2026-11-04 UTC · Model: claude-fable-5-1 (roles corrected; v2 offline marking and spans; v3 general calendar)*
