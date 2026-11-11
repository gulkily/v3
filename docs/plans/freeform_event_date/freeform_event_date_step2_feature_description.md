> **Feature plan:** [Step 1](./freeform_event_date_step1_solution_assessment.md) · [Step 2](./freeform_event_date_step2_feature_description.md) · [Step 3](./freeform_event_date_step3_development_plan.md) · [Step 4](./freeform_event_date_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/freeform_event_date`; [host ledger](../effort_estimation_trial.md#cycle-v3freeform_event_date). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.85** (Step 1 0.35, Step 1 Revision 1 0.80)

## Problem

Entering an event means working three pickers, even when the poster can say the date in a few words. Words like "the week of the 20th" cannot be expressed in pickers at all, so they are lost.

Concepts touched: [shared composer](../../concepts/shared_composer.md) (changed: the Event section gains a When box); [post event metadata](../../concepts/post_event_metadata.md) (changed: a new `Event-When` header carries the poster's words); [calendar](../../concepts/calendar.md) (used unchanged: a fixed Date on the day page stays fixed). New inventory entry: **date phrase**, the poster's own words for when an event happens, read into event fields and kept beside them.

## User stories

- As a poster, I want to type "next Thursday at 7" and have Date and Time filled, so that I do not work three pickers.
- As a poster, I want to see the dates before I post, so that a wrong reading never gets saved.
- As a poster, I want my words shown with the event, so that "sometime the week of the 20th" keeps its looseness.
- As an operator, I want the feature to work without an LLM provider and to use one when configured, so that it costs nothing by default.

## Core requirements

- A new site flag, `FORUM_EVENT_WHEN_ENABLED` ("Event phrases"), in the Authoring group, defaults to off and requires `FORUM_EVENT_SUPPORT_ENABLED`. Off means the composer, records and pages behave exactly as today.
- The Event section shows a **When** box and a **Fill** button. Fill fills Date, Until and Time in place, and never changes a fixed Date. With no JavaScript, Fill submits the form and the composer returns filled, with the draft kept.
- The built-in reader handles common phrases first. A phrase it cannot read goes to the LLM only when a provider is configured and the poster is a signed-in approved member. Otherwise the composer says it could not read the phrase and leaves the fields as they were.
- "Today" for the reading is the poster's own date and time zone, as on the calendar. Without JavaScript, the server uses the UTC date and says so beside the filled fields.
- On posting, a non-empty When box is saved as `Event-When` on the thread root, through both the plain form and `prepare_thread`. The event block shows it beside the dates. A post with `Event-When` and no `Event-Date` is rejected, so every event still lands on the calendar.

## Delivery scope

- Work type: application change.
- In scope: the flag and its registry and dependency checks; the When box, Fill path and no-JS fallback; the built-in reader; the LLM fallback through the existing provider layer; the `Event-When` header in the record spec, parser, write path, `prepare_thread`, read model and event block; tests; concept, spec and reference docs. Ledger bookkeeping for this cycle.
- Out of scope: agents sending only a phrase to `prepare_thread` and having the server read it; phrases for the calendar month jump or search; languages other than English in the built-in reader. These remain Enhancement Requests.

## Completion boundary

- Normal entry: the Event section of the thread composer, including the calendar day page.
- End-to-end outcome: a poster types "Friday 7pm", presses Fill, sees Date and Time filled, posts, and the thread shows the dates with "Friday 7pm" beside them. The calendar lists it on that Friday.
- Recovery: an unreadable phrase leaves the fields unchanged with a short reason, and the poster can edit the phrase or use the pickers. A provider error or timeout gives the same result, never a guessed date. A rejected post keeps the phrase and fields.
- Release condition: smoke and changed tests pass, including flag registry checks and a read-model rebuild from the previous schema version. The full journey is checked by hand with JavaScript on and off, and with and without a provider.

## Risks

- **Wrong reading saved.** Impact: an event lands on the wrong day. Earliest validation: a fixed set of phrases with expected dates, run against both readers. Mitigation: Fill only fills the pickers, and posting saves what the pickers show.
- **Provider cost and draft privacy.** Fill can be pressed often, and it sends draft text before posting. Impact: cost and leaked drafts. Earliest validation: review how agent responses limit and record calls. Mitigation: LLM fallback only for approved members, a short phrase limit, and no recording of Fill calls in the LLM exchange store.
- **Older releases.** They ignore `Event-When` and show only the dates. Impact: the phrase disappears on mirrors running older code. Earliest validation: parse a record with the header on the previous release. Mitigation: accepted, as with `Event-End-Date`; noted in the spec.
- **Scope size.** Two readers plus a record change may exceed eight Step 3 stages. Impact: an oversized cycle. Earliest validation: the Step 3 stage count. Mitigation: move `Event-When` to a follow-up cycle on the same branch, as Step 1 Revision 1 allows.

## Shared component inventory

- `templates/partials/composer.php` Event disclosure: extend with the When box and Fill. Keep the `composerEventDateFixed` read-only rule.
- `ComposeAndAccountKeyController`: extend its event query fields for the no-JS Fill round trip.
- `src/ForumRewrite/Llm/StructuredChatProvider.php` with the Anthropic, OpenAI-compatible and stub providers, configured by `LlmProviderConfig`: reuse unchanged for the fallback. The stub provider serves tests.
- `PostWorkflowService` and `PostWorkflowApiController` agent-reply path: inspect for how calls are gated and limited; reuse its pattern for the Fill endpoint.
- `PostRecordParser` event headers and `LocalWriteService` event writes: extend with `Event-When`, following the `Event-End-Date` pattern.
- `prepare_thread` in `WritePostAndIdentityApiController`: extend so browser-signed events can carry the phrase.
- `templates/partials/event_block.php`: extend to show the phrase.
- `FeatureFlagRegistry`: extend with the flag, after `FORUM_EVENT_SUPPORT_ENABLED` in the Authoring group.
- Calendar "today" logic from `calendar_today`: reuse the same viewer-clock rule.
- New: the built-in reader. Nothing in v3 reads date phrases, and PRINCIPLES.md rules out a third-party library.

## Simple user flow

1. A member opens New post and expands Event.
2. They type "next Thursday at 7" in When and press Fill.
3. Date shows next Thursday and Time shows 19:00.
4. They post. The thread shows the date, time and "next Thursday at 7".
5. The calendar lists the thread on that Thursday.

## Success criteria

- Every phrase in the agreed test set fills the expected Date, Until and Time, from the built-in reader or the stub LLM.
- An unreadable phrase, or a provider failure, changes no field.
- A fixed Date is never changed by Fill.
- A posted thread with a phrase shows it in the event block and keeps it after a read-model rebuild.
- Fill works with JavaScript off, keeping the draft.
- With the flag off, the composer and every event page render as before this cycle.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-10T17:20:00Z, `railspike-effort` / `v3/freeform_event_date`, Step 2 handoff. Step 1 Revision 1 (A + B + C) explicitly approved. Scope v2 plus a site flag. Starting revision `b50af91108484f84e007f0b9e857b867dc8fdbbd`; reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Projected total 0.80 → **0.85**, from new understanding: browser-signed events go through `prepare_thread`, the LLM fallback needs member gating, and the feature needs its own flag like other features. No implementation, measured labor or accepted outcome; planning uncommitted.

## Enhancement Requests

Carried from Step 1, not approved, backlog only:

- Let agents send only a phrase to `prepare_thread` and have the server read it. Disposition: follow-up cycle.
- Freeform input for other date fields (calendar month jump, search by date). Disposition: separate cycle if wanted.
