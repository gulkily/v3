> **Feature plan:** [Step 1](./freeform_event_date_step1_solution_assessment.md) · [Step 2](./freeform_event_date_step2_feature_description.md) · [Step 3](./freeform_event_date_step3_development_plan.md) · [Step 4](./freeform_event_date_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/freeform_event_date`; [host ledger](../effort_estimation_trial.md#cycle-v3freeform_event_date). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.90** (Step 1 0.35, Step 1 Revision 1 0.80, Step 2 0.85)

Concepts touched: [shared composer](../../concepts/shared_composer.md) (changed in Stage 8: the When box); [post event metadata](../../concepts/post_event_metadata.md) (changed in Stage 8: `Event-When`); [calendar](../../concepts/calendar.md) (used unchanged; checked in Stage 8). New inventory entry **date phrase**, written in Stage 8.

## Completion Contract

- Normal entry: the Event section of the thread composer, on New post and on a calendar day page.
- End-to-end outcome: a poster types "Friday 7pm", presses Fill, sees Date and Time filled, and posts. The thread shows the dates with "Friday 7pm" beside them, and the calendar lists it on that Friday.
- Required recovery: an unreadable phrase or a provider failure changes no field and shows a short reason. A rejected post keeps the phrase and fields. A fixed Date never changes.
- Deployment and external checks: none beyond a normal deploy. The flag is off by default, so nothing changes until an operator turns it on. The LLM fallback is checked with the stub provider; a real provider is checked by hand only if one is configured locally.
- Release condition: smoke and changed tests pass, including flag registry checks and a read-model rebuild from schema 53. The journey is walked by hand with JavaScript on and off, and with and without a provider.

## Key Risks

- **High risk:** **Wrong reading saved.** Impact: an event lands on the wrong day. Early validation: the phrase test set in Stage 2, run against the built-in reader and the stub LLM. Mitigation: Fill only fills the pickers, and posting saves what the pickers show.
- **Provider cost and draft privacy.** Impact: repeated Fill presses cost money and send drafts out. Early validation: Stage 4 confirms that no existing call limiter exists to reuse. Mitigation: LLM only for approved members, a 120-character phrase limit, one call per Fill, and no exchange recording.
- **High risk:** **Rebuild loses phrases.** Impact: phrases vanish after a schema bump. Early validation: Stage 6 rebuilds from schema 53 with phrase records present. Mitigation: the phrase comes from the signed record, so a rebuild restores it.
- **Stage limit.** Impact: the plan sits at eight stages. Early validation: Stages 1 to 4 finish within their estimates. Mitigation: if they run over, Stages 5 to 7 move to a follow-up cycle on the same branch, as Step 1 Revision 1 allows.

## Stage 1

**Estimate: 0.10**

- Goal: a working When box for basic phrases without JavaScript.
- Dependencies: none.
- Expected changes: add `FORUM_EVENT_WHEN_ENABLED` ("Event phrases"), default off, after event support in the Authoring group, requiring event support. Add the When box and Fill button to the Event disclosure. Fill submits the form; the composer returns with fields filled and the draft kept. Add a new reader class, `EventPhraseReader`, with one entry point: read(phrase, today) returns Date, Until and Time, or a reason. This stage reads "today", "tomorrow", weekday names and explicit dates such as "Nov 14".
- Verification approach: unit tests for the reader; composer page tests for flag off (no box) and flag on (filled round trip, draft kept); flag registry tests.
- Estimate details: driver: a new form action through the compose controller; uncertainty: low.
- Risks or open questions:
  - Impact: a Fill submit could be taken as a post. Early warning: the compose test posts nothing on Fill. Mitigation: Fill uses its own button name, checked before any write.
- Canonical components/API contracts touched: `FeatureFlagRegistry` (extend), `templates/partials/composer.php` (extend, keeping `composerEventDateFixed`), `ComposeAndAccountKeyController` (extend). New: `EventPhraseReader`, because nothing in v3 reads date phrases.

## Stage 2

**Estimate: 0.12**

- Goal: the built-in reader covers the common phrases in an agreed test set.
- Dependencies: Stage 1.
- Expected changes: add times ("7pm", "19:00", "at noon"), "next" and "this" with weekdays, spans ("Nov 14–16", "this weekend", "Friday to Sunday"), and "in 3 days". Unreadable phrases return a reason. A phrase test set with fixed "today" values lives in the tests as the shared expectation for both readers.
- Verification approach: the phrase test set passes; edge cases at month and year ends and a Sunday "today".
- Estimate details: driver: breadth of phrasing; uncertainty: medium.
- Risks or open questions: the test set is the agreed contract for "common". The maintainer can add phrases during review.
- Canonical components/API contracts touched: `EventPhraseReader` (extend).

## Stage 3

**Estimate: 0.10**

- Goal: Fill works in place with JavaScript, using the poster's own date.
- Dependencies: Stage 2.
- Expected changes: a small read endpoint takes phrase, local date and time zone, and returns fields or a reason. A script fills the pickers in place, leaves a fixed Date alone and shows the reason when nothing is read. Without JavaScript, the Stage 1 path stays and uses the UTC date, with a note saying so.
- Verification approach: endpoint tests; a browser test in `tests/browser/composers.mjs` for in-place fill, a fixed Date on the day page, and an unreadable phrase.
- Estimate details: driver: endpoint plus script; uncertainty: low.
- Risks or open questions: the endpoint must not write. It answers GET only and stores nothing.
- Canonical components/API contracts touched: `ApiRouteCatalog` (extend); the viewer-clock rule from `calendar_today` (reuse).

## Stage 4

**Estimate: 0.12**

- Goal: phrases the built-in reader cannot read go to the LLM when allowed.
- Dependencies: Stage 3.
- Expected changes: when the reader returns a reason, a provider is configured, and the viewer is a signed-in approved member, send one structured call with phrase, today and time zone. The answer must give valid dates or say it is unclear. Validate the answer as the parser would. Phrases over 120 characters are refused. Provider errors and timeouts give the "could not read" result. Fill calls are not recorded in the LLM exchange store.
- Verification approach: tests with the stub provider for success, unclear, malformed answer, error, timeout, a keyless viewer and no provider. The phrase test set's LLM-only cases pass with the stub.
- Estimate details: driver: answer validation and gating; uncertainty: medium.
- Risks or open questions:
  - Impact: a malformed answer could fill a bad date. Early warning: malformed-answer tests. Mitigation: the same date checks as `PostRecordParser`.
- Canonical components/API contracts touched: `StructuredChatProvider`, `LlmProviderConfig`, stub provider (reuse unchanged); `ApprovedUserKeyResolver` (reuse) for gating.

## Stage 5

**Estimate: 0.10**

- Goal: a posted phrase is saved in the signed record.
- Dependencies: Stage 1.
- Expected changes: add `Event-When` to the canonical record spec and `PostRecordParser`, root only, plain text up to 120 characters, requiring `Event-Date`. `LocalWriteService` writes it from a non-empty When box. A post with a phrase and no Date is rejected with the phrase and fields kept.
- Verification approach: parser tests in the style of `EventEndDateParserTest`; write tests; the rejection keeps the draft.
- Estimate details: driver: following the `Event-End-Date` pattern; uncertainty: low.
- Risks or open questions: older releases ignore the header; recorded in the spec, as for `Event-End-Date`.
- Canonical components/API contracts touched: `docs/specs/canonical_post_record_v1.md`, `PostRecordParser`, `LocalWriteService` (extend).

## Stage 6

**Estimate: 0.10**

- Goal: the thread and event block show the phrase.
- Dependencies: Stage 5.
- Expected changes: add a nullable `threads.event_when` column in schema 54, filled by the full builder and the incremental updater. `templates/partials/event_block.php` shows the phrase beside the dates when the flag is on.
- Verification approach: schema and rebuild tests from 53; `ReadModelSchemaTest`; a thread page test with and without a phrase and with the flag off.
- Estimate details: driver: schema bump and both builders; uncertainty: low.
- Risks or open questions: see Key Risks on rebuild.
- Canonical components/API contracts touched: `ReadModelSchema`, `ReadModelBuilder`, `IncrementalReadModelUpdater`, `ThreadRepository`, `docs/specs/read_model_schema_v1.md`, `event_block.php` (extend).

## Stage 7

**Estimate: 0.08**

- Goal: browser-signed events carry the phrase.
- Dependencies: Stage 5.
- Expected changes: `prepare_thread` accepts the phrase and puts `Event-When` in the prepared record. The browser signing path sends it from the When box.
- Verification approach: `WriteApiSmokeTest` and `AgentPostingApiTest` cases with a phrase; a signed post with a phrase shows it on the thread.
- Estimate details: driver: one more field through two paths; uncertainty: low.
- Risks or open questions: none beyond Stage 5.
- Canonical components/API contracts touched: `WritePostAndIdentityApiController` `prepare_thread`, `public/assets/browser_signing.js` (extend).

## Stage 8

**Estimate: 0.08**

- Goal: docs and the full journey checked.
- Dependencies: Stages 1 to 7.
- Expected changes: update the shared composer and post event metadata concepts; add the date phrase concept; update `docs/reference/calendar.md` and the flag description. Record the ledger line. Final diff review.
- Verification approach: smoke and changed tests. Walk the journey by hand with JavaScript on and off, with and without the stub provider, on New post and a calendar day page, in three themes on phone and desktop.
- Estimate details: driver: manual walk; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: concept inventory, reference docs, `effort_estimation_trial.md`.

## Study checkpoint

2026-11-11T01:30:00Z, `railspike-effort` / `v3/freeform_event_date`, Step 3 handoff. Step 2 explicitly approved. Scope v2 plus flag, unchanged. Starting revision `e01a6fe20a8e3e858cfb9bd378f07805046cdfec`; reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Whole-cycle 0.85 → **0.90**, from new understanding: a schema bump to 54 with rebuild tests, and no existing call limiter to reuse. Stage-work originals 0.10/0.12/0.10/0.12/0.10/0.10/0.08/0.08, non-additive. No implementation, measured labor or accepted outcome; planning uncommitted.

## Enhancement Requests

Carried from Step 2, not approved, backlog only: agents sending only a phrase to `prepare_thread`; phrases for the calendar month jump or search.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
