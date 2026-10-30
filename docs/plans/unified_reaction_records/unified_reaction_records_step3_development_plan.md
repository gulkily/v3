> **Feature plan:** [Step 1](./unified_reaction_records_step1_solution_assessment.md) · [Step 2](./unified_reaction_records_step2_feature_description.md) · [Step 3](./unified_reaction_records_step3_development_plan.md) · [Step 4](./unified_reaction_records_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/unified_reaction_records; [standing enrollment P2](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
Relationship: separate follow-up to [signed_thread_label_records](../signed_thread_label_records/signed_thread_label_records_step4_implementation_summary.md)
Status: Step 2 approved; Step 3 proposed; implementation not authorized
**Cycle estimate: 0.75**

# Unified Reaction Records Step 3 Development Plan

Concepts touched: *reaction record* (created in Stage 5 as `docs/concepts/reaction_record.md`); thread label and post reaction (changed: described there as the two scopes); [effective tags](../../concepts/effective_tags.md) (used unchanged, parity checked in Stage 5).

## Completion Contract

- Normal entry: existing thread, post/reply and QDB Like/Flag/tag/vote controls; posting with hashtags; offline signed-intent replay; operator scripts.
- Outcome: each saves one record in the new format in its existing directory (`Record-ID`, `Created-At`, `Target-Kind: thread|post`, `Target-ID`, `Operation`, `Tags`, then existing optional fields), signed when a key is available, and counts and displays as before.
- Compatibility: both legacy header sets read as stored; signed legacy bytes verify unchanged; a legacy and a new vote from the same author, target and tag count once; browsers with cached older scripts can still submit legacy-format signed records (accepted byte-exact, not rewritten).
- Recovery: clock-skew fallback, interrupted-write recovery and retries behave as today without double counting.
- External: no deployment in this cycle. Older application versions reject new-format records as unparseable; documented, not fixed.
- Release: focused tests per stage pass; signed-reaction browser checks pass with new bytes; full `php tests/run.php` has no failures beyond a baseline recorded at Step 4 entry.

## Key Risks

- **High risk:** signed byte mismatch between browser and server. Impact: signed votes fail. Validation: Stage 4 shared fixture asserted by both PHP and JS tests before browser checks. Mitigation: one exact layout per form, `Record-ID`/`Created-At` stay the first two lines so the skew-fallback comparison is unchanged.
- **High risk:** double counting across forms. Impact: inflated scores. Validation: Stage 1 inventory of duplicate/ownership checks (all use `loadThreadLabel`/`loadPostReaction`); Stage 3 mixed-form duplicate test. Mitigation: every check reads through the shared parser.
- Scope/directory mismatch accepted somewhere. Impact: a thread record counted as a post reaction. Validation: Stage 1 rejection tests. Mitigation: the check lives in the parser entry points that take a path.
- Cached older browser scripts. Impact: signed votes rejected after deploy. Mitigation: server accepts both exact layouts (Stage 4).

## Stage 1

**Estimate: 0.15**

- Goal: one parser reads the new format and both legacy header sets.
- Dependencies: none.
- Expected changes: shared reaction parser behind `ThreadLabelRecordParser::parse()` and `PostReactionRecordParser::parse()`, returning the existing `ThreadLabelRecord`/`PostReactionRecord` so readers and the read-model schema stay unchanged; legacy-or-new detection; declared scope must match the parser's family; `Source-Post-*` allowed only in thread scope; mixing legacy and new headers rejected. New spec `docs/specs/reaction_record_v1.md`.
- Verification approach: parser tests for new form (both scopes), both legacy forms with existing signed fixtures byte-unchanged, scope mismatch, mixed headers, `Source-Post-*` on post scope; `CanonicalRecordParsersTest` stays green.
- Estimate details: driver: legacy aliasing plus strict rejection; uncertainty: low.
- Risks or open questions:
  - Impact: lenient detection could accept half-migrated records.
  - Early warning / validation: mixed-header rejection test.
  - Mitigation: exactly one target/tag header set per record.
- Canonical components/API contracts touched: `ThreadLabelRecordParser`, `PostReactionRecordParser`, record family spec.

## Stage 2

**Estimate: 0.15**

- Goal: every reader and lifecycle path accepts new-format records before anything writes them.
- Dependencies: Stage 1.
- Expected changes: route raw-header readers through the parser (`scripts/audit_post_signatures.php`, `scripts/delete_record.php` output); confirm full/incremental read model, viewer-tag lookup, signature index, verified attribution, activity detail, high-traffic safety, import planner, archive catalog, `archive_thread.php`, `thread_attributes.php`, label ownership/removal receipts need no change.
- Verification approach: mixed legacy/new fixture repository gives identical scores, tags, flags, moderation and signature status through full rebuild, incremental update, import, archive restore, activity detail and audit; existing suites for these areas pass.
- Estimate details: driver: breadth of consumers, mostly confirmation; uncertainty: a hidden raw reader.
- Risks or open questions:
  - Impact: a consumer silently skips new records.
  - Early warning / validation: mixed-repository parity test covering each consumer.
  - Mitigation: fix any skip in this stage before writers change.
- Canonical components/API contracts touched: read-model builders, import/archive catalog, activity service, operator scripts.

## Stage 3

**Estimate: 0.15**

- Goal: all server-side producers write the new format.
- Dependencies: Stage 2.
- Expected changes: one builder in `LocalWriteService` replacing `buildThreadLabelRecord`/`buildPostReactionRecord` for keyless tags, hashtag labels, offline signed-intent replay and the skew-fallback prepared record; same ID prefixes and paths.
- Verification approach: write API smoke, hashtag label, offline intent and duplicate tests; a legacy and a new vote from the same author/target/tag count once; existing `ReadModelThreadLabelsTest`/`ReadModelPostReactionsTest` pass.
- Estimate details: driver: four producer paths; uncertainty: offline replay fixtures.
- Risks or open questions:
  - Impact: double counting.
  - Early warning / validation: mixed-form duplicate test.
  - Mitigation: duplicate checks already read through the parser.
- Canonical components/API contracts touched: `LocalWriteService` record builders, write API.

## Stage 4

**Estimate: 0.20**

- Goal: browser-signed reactions use the new format; older cached clients keep working.
- Dependencies: Stage 3.
- Expected changes: `thread_reactions.js` builds new-format bytes; server signed-input check accepts the new exact layout or the legacy exact layout for the requested kind; prepared fallback uses the new layout; `signed_reaction_submission_v1.md` updated.
- Verification approach: shared fixture asserted in PHP and JS tests; tampered bytes, wrong scope and wrong signer rejected; legacy-layout signed submission accepted unchanged; localhost Chrome/OpenPGP matrix from the predecessor cycle for thread Like, post Flag, QDB vote, skew fallback and retry, in each theme where controls differ.
- Estimate details: driver: exact-byte agreement and browser matrix; uncertainty: matrix setup time.
- Risks or open questions:
  - Impact: signed votes fail.
  - Early warning / validation: shared fixture before browser runs.
  - Mitigation: keep first two lines fixed; accept both layouts.
- Canonical components/API contracts touched: `thread_reactions.js`, `/api/apply_thread_tag`, `/api/apply_post_tag`, signed submission spec.

## Stage 5

**Estimate: 0.10**

- Goal: documentation, concept entry and release verification.
- Dependencies: Stage 4.
- Expected changes: legacy notes in `thread_label_record_v1.md`/`post_reaction_record_v1.md` pointing to the new spec; cross-version limitation documented; `docs/concepts/reaction_record.md`; study ledger closeout.
- Verification approach: full suite compared with Step 4 entry baseline; HTML, Forte, text API, offline snapshot and effective-tag output unchanged on the mixed fixture; links resolve; `git diff --check`.
- Estimate details: driver: full suite and parity review; uncertainty: baseline failures.
- Risks or open questions:
  - Impact: unrelated baseline failures obscure the release condition.
  - Early warning / validation: record baseline at Step 4 entry.
  - Mitigation: report exact failing tests against baseline; never claim globally green otherwise.
- Canonical components/API contracts touched: format specs, concept inventory, study ledger.

## Execution and study checkpoint

Checkpoint: Step 3 handoff, 2026-10-30 UTC; whole-cycle 0.75 reaffirmed
Scope: v1 / Option A; baseline 60d17174ceef711479a0cd3b20ad56d885a0f012; R1 / P2
Stage work originals S1–S5/v1: 0.15 / 0.15 / 0.15 / 0.20 / 0.10, not additive. Stage 5 owns shared release verification.
Rater: Claude (claude-opus-5-5), single exposed rater; additionally inspected the browser builder, server signed-input check and raw-header readers. New understanding: legacy client layout must stay accepted (small addition, within Step 2 "old clients keep working"); reader breadth smaller than feared. Net unchanged.
Authorization: Approved Step 2 received. No enhancement requests. Awaiting Approved Step 3; no branch, commits or implementation.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*
