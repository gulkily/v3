> **Feature plan:** [Step 1](./mitrapclub_events_experience_step1_solution_assessment.md) · [Step 2](./mitrapclub_events_experience_step2_feature_description.md) · [Step 3](./mitrapclub_events_experience_step3_development_plan.md) · [Step 4](./mitrapclub_events_experience_step4_implementation_summary.md)

## Problem

A `mitrapclub` event announcement (date, location, optional link) has no structured fields and renders identically to an ordinary discussion thread — a visitor scanning the board can't tell "there's a show Friday" from "someone started a discussion" without opening the thread and reading the prose.

## User Stories

- As a `mitrapclub` member composing a thread, I want to optionally attach a date, location, and link to it, so the board can present it as an event instead of forcing me to write that information as unstructured prose.
- As a `mitrapclub` visitor scanning the board, I want event threads to visually stand out with their date/location/link shown directly, so I can spot "what's next" without opening every thread.
- As a member of another profile (`zenmemes`/`chouse`/`qdb`), I want no change to how my threads compose or render, whether or not I ever use these new fields.

## Core Requirements

- A thread's root post gains three new optional pieces of structured metadata: event date, event location, and event link (a plain URL, not an RSVP/attendee system).
- These three fields are stored on the canonical post record (the plain-text file format already used for `Board-Tags`/`Subject`), following that same existing convention rather than a new file format.
- The thread composer gains three new optional input fields for these values; leaving them blank produces a thread byte-identical in behavior to today's (no event block renders).
- A thread whose root post has an event date renders a distinct, structured event block (date, location if present, link if present) on the board card and on the thread page — this is the sole trigger; no separate "mark as event" toggle is needed.
- `zenmemes`/`chouse`/`qdb` get no visible or behavioral change: no profile is forced to use these fields, and a thread with no event date renders exactly as today everywhere.

## Delivery Scope

- **Work type:** Application change.

## Completion Boundary

- **Normal entry:** a member composes a new thread on `mitrapclub` and fills in (or leaves blank) the new event fields; a visitor then loads the board or that thread.
- **End-to-end outcome:** a thread with an event date shows a structured event block wherever it's rendered (board card, thread page); a thread without one renders exactly as before this feature, on every profile.
- **Needed recovery:** malformed or partial event input (e.g. a date filled in but garbled) must degrade gracefully — render without the event block, or render only the fields that parsed, never a hard error or a broken page.
- **Release condition:** `./v3 test` passes in full; rendering any existing thread (no event fields) on any profile produces byte-identical output to before this feature.

## Risks

- This is genuinely new read-model/parsing work (new optional post-record fields flowing through to new derived thread fields), not a config change — more involved than the three prior `mitrapclub` cycles. Earliest validation: Step 3 stage sizing: if it doesn't fit the usual day/8-stage guardrail, split date-only support from location/link as separate follow-on stages or features. Mitigation: ship date-only first if needed; location/link are additive on top.
- Date input is free text at the compose layer; an unparseable or ambiguous date must not break thread creation or rendering. Earliest validation: Step 3's explicit malformed-input test case (carried from this Step 2's Completion Boundary). Mitigation: validate/normalize at compose time where practical; always fail soft at render time (missing block, not broken page).
- Scope creep toward Option C from Step 1 (a full dedicated events `Experience` with its own routes/listing page) — this feature only adds fields to the existing generic thread flow. Mitigation: Step 3 holds to Option B's shape; a dedicated events page/route is explicitly out of scope here.
- `board_tags`/`thread_labels` already exist as separate per-thread metadata mechanisms (compose-time tags vs. moderator-set labels); introducing a third, parallel one (event fields) risks confusing overlap. Mitigation: event fields are their own concept (structured values, not a tag/label), and don't replace or interact with `board_tags`/`thread_labels` — confirmed no existing code path needs to change to accommodate them.

## Shared Component Inventory

- `src/ForumRewrite/Canonical/PostRecordParser.php` and `src/ForumRewrite/Write/LocalWriteService.php` — reuse and extend the existing canonical post-record header format (same pattern as `Board-Tags`/`Subject`) to read/write the three new optional header fields; no new file format.
- `templates/partials/thread_compose_form.php` — reuse the existing compose-form partial; add three new optional inputs alongside the existing `board_tags`/`Subject` fields.
- `src/ForumRewrite/ReadModel/ReadModelBuilder.php` (the same derivation pattern already used for `board_tags_json`/`thread_labels_json`) — extend to derive the three new per-thread fields from the root post's canonical record.
- `templates/partials/thread_card.php` and `templates/pages/thread.php` — reuse the existing single board-card/thread-page partials (per Step 1's finding that `boardCard` is a whole-partial, profile-wide swap, not per-thread); add a conditionally-rendered event block inside them, gated on the new derived date field being present — no new partial, no new `boardCard` slot value.

## Simple User Flow

1. A `mitrapclub` member opens the thread composer, writes their post, and optionally fills in date/location/link.
2. They submit; the thread's canonical post record now carries the new optional header fields (or doesn't, if left blank).
3. A visitor loads the board: a thread with an event date shows a structured event block in its card; one without renders exactly as before.
4. The visitor opens the thread: the same event block appears on the thread page, above or alongside the existing content.
5. A member on `zenmemes`/`chouse`/`qdb` composes and reads threads exactly as before — nothing changes for them.

## Success Criteria

- A `mitrapclub` thread with an event date renders a structured, visually distinct event block on both the board card and the thread page.
- A thread without an event date — on any profile — renders byte-identical to before this feature.
- Malformed/partial event input degrades gracefully; no broken page or hard error.
- `./v3 test` passes in full.

Waiting for "Approved Step 2" before drafting Step 3.
