> **Feature plan:** [Step 1](./mitrapclub_events_experience_step1_solution_assessment.md) · [Step 2](./mitrapclub_events_experience_step2_feature_description.md) · [Step 3](./mitrapclub_events_experience_step3_development_plan.md) · [Step 4](./mitrapclub_events_experience_step4_implementation_summary.md)

## Original Query

Great job, I committed. Please continue with the next task.

## Understood Intent

- You picked **Cycle 4 ("Events experience")** from `docs/plans/mitrapclub_theme_and_features_checklist.md` as the next cycle.
- Context: the original `mitrapclub_website` feature's Step 1 explicitly deferred a dedicated events experience (its "Option B") in favor of treating events as ordinary tagged/pinned threads (its "Option C"), with the note "only build a qdb-style dedicated events experience later if that turns out not to be enough." The checklist now flags this as worth revisiting: event posts (date/location/RSVP link) still render as plain threads, indistinguishable from an ordinary discussion thread.
- Architecture check before framing options: `boardCard` is a profile-wide presentation slot (`thread` vs. `quote`, a whole-partial swap in `BoardPageController`) — it picks one card layout for every thread on the board, not per-thread. There is currently no per-thread "this one is an event" distinction anywhere in the rendering. Separately, this app's canonical data model already derives structured fields (`thread_labels_json`, `board_tags_json`) from parsed canonical text records (see `ReadModelBuilder::indexThreadLabels()`), rather than from relational schema columns — any new structured event data most naturally follows that same text-in/derived-field-out pattern.

## Problem Statement

A `mitrapclub` event announcement (date, location, optional RSVP/ticket link) renders identically to an ordinary discussion thread, with no structured presentation and no visual distinction between post types.

## Solution Options

- **Option A: Status quo, documented as sufficient.** Keep using the existing generic `pinned` thread label and board tags to surface an event thread; rely on the author writing date/location into the post body as plain prose. No new code.
  - Pros: zero cost; this is exactly what the original feature's Step 1 already chose (Option C) and it works today.
  - Cons: doesn't actually resolve the checklist's complaint — still indistinguishable from any other thread, no structured fields, nothing for `thread_card.php` to render specially.
- **Option B: Lightweight structured event fields on ordinary threads (recommended).** Define a small, parseable event-metadata convention inside a thread's canonical post text (mirroring how `thread_labels_json`/`board_tags_json` are already derived from parsed canonical records); extend the read model to derive optional `eventDate`/`eventLocation`/`eventLink` fields per thread when present; `thread_card.php` renders a distinct event-styled block (date/location/link) when a thread carries them, otherwise renders exactly as today. No new `Experience`, no new routes, no new `boardCard` slot value — stays inside the existing generic `forum` experience that `mitrapclub`/`zenmemes`/`chouse` already share.
  - Pros: genuinely resolves "no structured presentation" and "no visual distinction between post types"; additive and reuses the codebase's existing text-to-derived-field pattern instead of inventing a new one; `zenmemes`/`chouse`/`qdb` are structurally unaffected unless they opt in.
  - Cons: real read-model/parsing work (more than a config change); needs a concrete, unambiguous syntax decision for how an author marks a post as an event (Step 2/3 territory); "RSVP link" here means a plain URL field, not attendee tracking or RSVP state — scope stays at display, not a new capability.
- **Option C: Full dedicated events experience (qdb-style).** Build a new `Experience` (own routes, compose form with explicit date/location/RSVP fields, and a dedicated events listing), the same shape as `QdbExperience`/`QdbPresentation`.
  - Pros: most capable — a real "what's next" events page, not just decorated threads; room for future RSVP/attendee features.
  - Cons: materially more code (new experience class, routes, policy, its own presentation slots) — the same cost/speculation trade-off the original Step 1 already weighed and rejected; still speculative before Option B is tried.

## Recommendation

**Option B.** It directly fixes the checklist's actual complaint (no structured fields, no visual distinction) without repeating Option C's full-experience cost, and it extends a pattern this codebase already trusts (deriving structured fields from parsed canonical text) rather than introducing a new one. Option A is the honest "no-op" fallback if Step 2/3 scoping finds even this too invasive. Option C stays available later if Option B proves insufficient, exactly as the original Step 1 anticipated.

**Vertical-slice viability:** Yes. Entry is an author composing a thread on `mitrapclub` using the event convention; outcome is that thread rendering with a distinct, structured event block (date/location/link) on the board and in the thread view; recovery is graceful (a malformed or missing event marker just renders as an ordinary thread, never an error); no regression to `zenmemes`/`chouse`/`qdb`.

Waiting for "Approved Step 1" before drafting Step 2.
