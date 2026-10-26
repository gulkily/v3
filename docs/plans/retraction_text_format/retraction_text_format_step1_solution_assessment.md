> **Feature plan:** Step 1 · [Step 2](retraction_text_format_step2_feature_description.md) · [Step 3](retraction_text_format_step3_development_plan.md) · [Step 4](retraction_text_format_step4_implementation_summary.md)

Debug mode: enabled.
Effort study: `railspike-effort` / `v3/retraction_text_format`; [ledger](../effort_estimation_trial.md#cycle-v3retraction_text_format). Standing P2 enrollment.
Extended estimates: enabled. Approval: Step 1 Option B approved; Step 2 drafted.

**Cycle estimate: 0.60**

## Original Query

We are trying to write a platform with human-readable formats, where JSON is far inferior to the Key: format we use elsewhere. This guidance needs to be strongly recorded somewhere for this project, without being overly verbose. We also need to rewrite this retraction record format. And we need to compile a checklist of other places JSON is used in user-facing formats where it also needs to be rewritten.

## Scope clarification

“When rewriting, we can ignore the previous retractions, we don't have any wide deployments yet.” Adopted: new retractions only; no old-record migration or backward-compatibility work. This changes scope, not step approval. Concise guidance is recorded directly in [CONTRIBUTING](../../../CONTRIBUTING.md#human-readable-formats) and `AGENTS.md`; [the audit checklist](../human_readable_formats_checklist.md) tracks other families separately.

## Problem

Retraction acceptance serializes human-readable signed evidence into escaped JSON, contrary to the platform's intended record format.

## Option A — One readable acceptance record

**Estimate: 0.45**

Replace the JSON acceptance with one `Key: value` text record, using readable body sections for the exact signed request and armored signature. Keep acceptance metadata visibly distinct from signed evidence.

- Preserves the current single-record deletion model and makes source inspection readable.
- Driver: format/readers, signing and deletion/import/cleanup verification. Uncertainty: unambiguous body boundaries without changing signed bytes.

## Option B — Separate signed request and acceptance receipt (selected)

**Estimate: 0.60**

Keep a header-based signed request and detached signature, with a separate header-based acceptance receipt for structure and local attribution, committed together when the post is deleted.

- Closely matches existing detached-signature conventions; each file has one role.
- More files to inspect and more atomic-write/retry/recovery cases. Driver: coordinated lifecycle and reader changes; uncertainty: extra recovery complexity.

## Recommendation and verification boundary

The user selected B. Commit the signed text request, detached signature and separate text acceptance receipt together with post deletion. Deliver a complete slice: new author/operator retraction through readable source, surviving replies, retry/rebuild, archive non-resurrection and delayed cleanup. Define the canonical contract under `docs/specs/`; verify exact signed bytes, malformed input, local acceptance versus untrusted imports, failed-commit recovery and actual browser/API/source behavior. Ignore previous retractions as requested; retain rollback/retry for newly written records. No new dependencies, deployment, history purge or other-family conversions. Step 1 Option B approved; no application implementation yet.

## Study snapshots

Intake, 2026-10-25 UTC: scope v1, baseline `2a07313a738af9e0a0f15796e8155d301d949ed5`, R1/P2, Codex single exposed rater. **0.70 projected total** for the retraction text migration, compatibility, normal-flow verification and documentation; concise policy and a source-backed audit accompany the cycle, but other format conversions are follow-ups. Prior-turn retraction code/history and project-spec inspection disclosed; not pristine intake. Exact three frozen R1 hashes verified against the ledger. Reference comparisons and prior scores visible; no independent rating or measured labor. Related predecessor: [retraction simplification](../retraction_simplification/retraction_simplification_step4_implementation_summary.md); its history and acceptance remain separate.

Step 1 handoff, 2026-10-25 UTC: scope v2 excludes historical retraction compatibility at the user's request; **0.45 projected total**, revised from v1 **0.70** for that scope reduction. Option A/v1 **0.45**, B/v1 **0.60**, each a whole-cycle judgment including planning, current policy/audit work, implementation, verification and documentation, not additive or measured time. Same baseline/R1/P2 and exposed rater; source audit and consumer inspection now included. Snapshots indexed in the ledger; no accepted outcome or implementation approval. Debug enabled, extended estimates enabled.

*— Codex · 2026-10-25 UTC*

## Option selection — 2026-10-25 UTC

Request: “Choose Option B.” Adopted as scope v3: separate signed request, detached signature and acceptance receipt, with coordinated commit/retry/recovery. No previous-retraction migration or compatibility. Step 1 approval remains pending under the workflow; no Step 2 or implementation started.

Study revision: **0.60 projected total**, adopting the previously recorded B/v1 estimate rather than revising that option. The v2 **0.45** recommendation and v1 **0.70** intake remain historical. Same stable cycle, baseline, R1/P2 and single exposed Codex rater; three frozen hashes reverified, no additional application inspection or measured labor. Debug and extended estimates remain enabled.

*— Revised by Codex · 2026-10-25 UTC*

Approval recorded, 2026-10-25 UTC: user explicitly sent “Approved Step 1.” Option B/scope v3 is approved for Step 2 requirements; prior pending statements above describe historical checkpoints. Audit prioritization was requested and completed separately in the existing checklist; no other format conversion added.

*— Revised by Codex · 2026-10-25 UTC*
