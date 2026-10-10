> **Feature plan:** [Step 1](./event_time_step1_solution_assessment.md) · [Step 2](./event_time_step2_feature_description.md) · [Step 3](./event_time_step3_development_plan.md) · [Step 4](./event_time_step4_implementation_summary.md)

## Original Query

Please add event time and duration to the event framework. If you think this fits within the context of one theme, please add a date/time selector for the event form. Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md. [Before approving Step 1, the user narrowed scope: "Let's leave duration out of it for now."]

## Understood Intent

- Scope is now event **time** only; duration is explicitly out for this feature and may come later as its own slice.
- "If you think this fits within the context of one theme" asks the assistant to judge whether the richer date/time picker should be `mitrapclub`-only or general. Checked before writing this: the existing event framework (`event_date`/`event_location`/`event_link`) is **not** theme-scoped today — it was deliberately made available to any site profile in `event_feature_gating` (`docs/plans/event_feature_gating/event_feature_gating_step1_solution_assessment.md`), gated only by the site-mutable `FORUM_EVENT_SUPPORT_ENABLED` flag, and the single compose-form partial (`templates/partials/thread_compose_form.php`) that renders the event fields has no profile-specific branch anywhere in it. Judgment: no, it does not fit within one theme — it should extend the same way the rest of the framework already works, for every profile with event support enabled, not forked for `mitrapclub` alone.
- Checked the current code before writing this: `event_date` is validated and stored as a strict `YYYY-MM-DD` string (no time component) in both the canonical `Event-Date:` header and the read-model `threads.event_date` column; the compose form's date field is a plain `type="text"` input with a `YYYY-MM-DD` placeholder, not a native picker.

## Problem Statement

A posted event captures only a date, location, and link — never a start time — and the compose form's date field is a bare text box with no native picker.

## Solution Options

- **Option A: One new additive field (`event_time`), general scope, native pickers for every profile.** Add `event_time` alongside the existing three event fields, following the same header/column/validator/template pattern already established for `event_date`/`event_location`/`event_link`; swap the compose form's event-date input to native `type="date"` and add a native `type="time"` input for `event_time`, in the one shared `thread_compose_form.php` partial, so every profile with event support enabled gets the improvement.
  - Pros: consistent with the framework's existing general-purpose architecture; purely additive — no change to the already-shipped `Event-Date` header/column, no data migration, no fork of the shared compose form; native pickers are a real UX improvement wherever events are used, not just for one profile.
  - Cons: a shared-template change, so a markup regression would affect every profile's compose flow, not just `mitrapclub` (bounded by the existing smoke-test coverage of that partial).
- **Option B: Same new field, but native pickers only for `mitrapclub`.** Identical data model to Option A, but gate the native `type="date"`/`type="time"` markup behind a presentation-slot check so only `mitrapclub` gets the richer widgets; other profiles keep today's plain text inputs.
  - Pros: literally answers "fits within one theme" by making the picker itself theme-scoped.
  - Cons: forks the compose-form partial for the first time ever — a structural change larger than the payoff justifies, since a native date/time input is strictly better for any profile using events, not a `mitrapclub`-specific aesthetic choice; inconsistent with Cycle 4's deliberate decision to keep the framework general.
- **Option C: Replace `event_date` with a combined `event_datetime` field.** Merge date and time into one ISO-8601 field with a native `type="datetime-local"` input.
  - Pros: one field/one picker is a slightly simpler mental model than separate date+time.
  - Cons: breaking change to the already-shipped `Event-Date` canonical header and read-model column that every existing event thread already uses in date-only form — needs a migration or dual-format parsing path, neither of which exists anywhere in this codebase today; conflicts with the process guardrail to avoid schema changes/lean on existing models where feasible.

## Recommendation

**Option A.** It directly resolves the "one theme" question: no — the framework is already general-purpose, so the new field and the native pickers should ship the same way, for every profile, not forked for `mitrapclub`. It's also the smallest change: one new nullable column/header, zero changes to the already-shipped date/location/link fields, and no data migration for threads that already have an event date but no time.

**Vertical-slice viability:** Yes. Entry is any member composing a thread on a profile with event support enabled; outcome is an event thread that can optionally carry a start time, entered through native browser date/time controls, validated the same way the existing event fields are, and rendered in the event block alongside date/location/link. Recovery is trivial: the new field is optional, so omitting it changes nothing, and every already-posted event thread (time absent) continues to render exactly as it does today.

Waiting for "Approved Step 1" before drafting Step 2.
