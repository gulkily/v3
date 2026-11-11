> **Feature plan:** [Step 1](./freeform_event_date_step1_solution_assessment.md) · [Step 2](./freeform_event_date_step2_feature_description.md) · [Step 3](./freeform_event_date_step3_development_plan.md) · [Step 4](./freeform_event_date_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/freeform_event_date`; [host ledger](../effort_estimation_trial.md#cycle-v3freeform_event_date). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.80 (Revision 1; was 0.35)

## Original Query

Future request: it would be nice if I can freeform describe a date, as long as we have an LLM API.

(Posted as [a ZenMemes thread](https://zenmemes.com/threads/thread-20261109000307-943b8f85); "Please write step one" in chat.)

## Understood Intent

The composer's Event section asks for Date, Until and Time through separate pickers. The maintainer wants to type something like "next Thursday at 7" or "the weekend after Thanksgiving, evenings" and have those fields filled. "As long as we have an LLM API" makes the feature depend on a configured provider: it appears when one is set and disappears when it is not, as PRINCIPLES.md requires for optional services.

v3 already has a provider layer (`LlmProviderConfig`, Anthropic and OpenAI-compatible providers, a stub for tests) used by post analysis, fast scoring and agent responses. Event fields already have one record format (`Event-Date`, `Event-End-Date`, `Event-Time`). On a calendar day page, Date is fixed and read-only.

Problem: Entering an event means working three pickers, even when the poster can say the date in a few words.

Concepts touched: [shared composer](../../concepts/shared_composer.md) (changed: a new way to fill the Event fields); [post event metadata](../../concepts/post_event_metadata.md) (used unchanged by A, C and D; changed by B); [calendar](../../concepts/calendar.md) (used unchanged: a fixed Date on the day page stays fixed). Candidate new concept: **date phrase**, a poster's own words for when an event happens, read into event fields.

## Shared by every option

- The poster always sees the dates that will be saved before posting. Nothing is saved from a phrase the poster has not seen read back.
- The reading uses today's date and the site's time zone, so "next Thursday" means the same thing to everyone posting on the same day.
- An unclear phrase ("sometime soon") fills nothing and says why. A fixed Date (calendar day page) is never changed.
- The pickers stay. Typing a phrase is a shortcut, not a replacement.

## Option A - "When" box that fills the pickers

**Estimate: 0.35**
Main driver: one small server endpoint, a structured LLM call and composer markup. Uncertainty: low.

The Event section gets a "When" text box and a Fill button. The server sends the phrase, today's date and the time zone to the configured provider and asks for Date, Until and Time in a fixed shape. The pickers fill in place, and the poster checks them and posts as today. Without JavaScript, Fill submits the form and the composer comes back with the fields filled and the draft kept. With no provider configured, the box is not shown. The record format does not change and the phrase is not stored.

- Pro: no record or schema change; posts look exactly as they do now.
- Pro: the existing pickers are the confirmation step.
- Pro: easy to switch off.
- Con: the poster's original words are lost once the fields are filled.
- Con: a draft phrase goes to the provider before posting; Step 2 decides whether that exchange is recorded.

## Option B - read the phrase at post time and keep it

**Estimate: 0.60**
Main driver: a new canonical event header, parser and read-model changes, and a confirm step on submit. Uncertainty: medium.

The poster can submit with only a phrase. The server reads it, shows a confirmation page with the dates, and on confirm writes the usual event headers plus a new `Event-When` header holding the original words. The thread shows both.

- Pro: keeps "sometime the week of the 20th" as written, which pickers cannot express.
- Pro: agents using `prepare_thread` could send a phrase too.
- Con: changes the signed record format and read model; older releases ignore the header.
- Con: an extra page on every event post.

## Option C - built-in phrase reader, no LLM

**Estimate: 0.45**
Main driver: writing and testing an English date-phrase reader in PHP. Uncertainty: medium, in how many phrasings are worth covering.

Same box and Fill button as A, but the server reads common phrases itself ("tomorrow", "Friday 7pm", "Nov 14–16", "next weekend").

- Pro: works with no provider, offline and instantly.
- Pro: same answer every time.
- Con: does not meet the request's premise; free-form phrasing is exactly what a fixed reader misses.
- Con: English only unless extended.

## Option D - A with C as the first pass

**Estimate: 0.55**
Main driver: both paths behind one box. Uncertainty: medium.

The built-in reader handles common phrases; anything it cannot read goes to the LLM when one is configured.

- Pro: fast and free for common phrases, and still useful without a provider.
- Con: two readers that can disagree, and two to test.

## Recommendation

Choose Option A. It answers the request with the provider layer v3 already has, changes no record format, and keeps the pickers as the check before posting. It is a vertical slice on its own: one box, one endpoint, filled fields, no-JS fallback and the provider-off case.

C's reader can be added later in front of A (making it D) if cost or speed becomes a problem. B's kept phrase is a separate cycle if posters want vague dates shown as written.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Revision 1 - combine A, B and C

**Estimate: 0.80**
Main driver: the built-in reader and its tests, plus a new canonical header through parser, read model and display. Uncertainty: medium, in reader coverage and older releases ignoring the new header.

Maintainer direction: "I actually want all three of those!" Read as A, B and C together, which includes D.

- **When box (A):** the Event section gets a "When" box and Fill button. Fill puts dates into the pickers in place; without JavaScript it returns the composer filled with the draft kept.
- **Built-in reader first (C):** common phrases are read on the server with no provider. Anything it cannot read goes to the LLM when one is configured. With no provider, the box still works for common phrases and says when a phrase needs the LLM.
- **Phrase kept (B):** on posting, the phrase is saved in a new `Event-When` header next to the usual event headers, and the event block shows it beside the dates. The pickers already show the dates before posting, so B's separate confirmation page is dropped.

Compatibility: all three fit one flow. The changed part is B: the record format and read model change, and `post_event_metadata` changes rather than being used unchanged. Older releases ignore `Event-When` and show the dates only, as they do with `Event-End-Date`.

Revised recommendation: build the combination as two slices on one branch. Slice 1 is the When box with the built-in reader and LLM fallback (A + C), which changes no records. Slice 2 adds `Event-When`. If Step 3 runs past eight stages, slice 2 becomes a follow-up cycle.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-09T00:04:00Z, `railspike-effort` / `v3/freeform_event_date`, intake and Step 1 handoff. Scope v1: Option A as recommended above. Starting revision `fac52df649aed789757c1ae5eb88306362a9945d`. Reference R1, protocol P2; frozen R1 hashes not re-verified for this checkpoint. Single exposed Claude rater. Pristine intake missing: the composer event fields, calendar concepts and LLM provider config were read before rating. Intake and Step 1 projected total **0.35**. Option estimates 0.35, 0.60, 0.45 and 0.55 are alternatives and do not add up. No approval, implementation or accepted-count change; planning uncommitted.

2026-11-09T00:06:00Z, Step 1 Revision 1. Scope v2: A + B + C combined (When box, built-in reader with LLM fallback, `Event-When` header; B's confirmation page dropped). Projected total 0.35 → **0.80**, reason scope change by maintainer. Same baseline/R1/P2, single exposed Claude rater. Revision is not approval.

## Enhancement Requests

- Let agents send a date phrase to `prepare_thread`. Disposition: follow-up cycle; needs B's record change or a server-side read before signing.
- Freeform input for other date fields (calendar month jump, search by date). Disposition: separate cycle if wanted.
