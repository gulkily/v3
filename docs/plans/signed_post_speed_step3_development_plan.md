> **Feature plan:** [Step 1](./signed_post_speed_step1_solution_assessment.md) · [Step 2](./signed_post_speed_step2_feature_description.md) · [Step 3](./signed_post_speed_step3_development_plan.md) · [Step 4](./signed_post_speed_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./signed_post_speed_debug_log.md))

Effort study: `railspike-effort` / `v3/signed_post_speed`; [host ledger](./effort_estimation_trial.md#cycle-v3signed_post_speed). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 2 approved on 2026-11-10; Step 3 drafted; implementation unapproved.

**Cycle estimate: 0.25** (reaffirmed)

## Completion Contract

- Normal entry: an agent runs `frm.py` against zenmemes.com or qdb.us, which sends `/api/create_signed_post`.
- End-to-end outcome: the post is accepted in about a second and appears right away; the read model equals a fresh full rebuild; a resent bundle with a current read model runs no refresh.
- Required recovery: when the read model is behind or a targeted update fails, the post still succeeds through today's full rebuild, and one log line says which update failed and why.
- Deployment/external verification: "claude" posts a test thread and a reply on zenmemes.com through `frm.py`, timed.
- Release condition: local equality, replay, fallback and timing tests, then the zenmemes.com post.

## Key Risks

- **High risk:** a new identity's bootstrap post and first post arrive in one commit and get counted twice. Validate at the start of Stage 2 with equality tests for new-identity threads and replies, before changing the route. Mitigate: adjust the order, or route that case to the fallback.

## Stage 1 - Fallbacks to a full rebuild log why

**Estimate: 0.04**

- Goal: every targeted update that falls back to a full rebuild leaves one log line naming the write, the update and the reason.
- Dependencies: none.
- Expected changes: one private helper in `LocalWriteService` that writes `Read model: <write> fell back to a full rebuild (<update>: <reason>)` with `error_log()`; called from the not-current branch and the catch branch of each `synchronize*DerivedState()` method and the retraction fallback.
- Verification approach: a test points `error_log` at a temporary file, forces a fallback (stale marker; a throwing update) and finds exactly one line with the update named; smoke set.
- Estimate details: driver: number of fallback branches; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `LocalWriteService` fallback branches only.

## Stage 2 - Signed posts update in place

**Estimate: 0.15**

- Goal: a signed post updates the read model for its identity and post only, with the full rebuild as fallback.
- Dependencies: Stage 1.
- Expected changes: in `createSignedPost()`, after the commit: for a new identity, `synchronizeIdentityDerivedState()` with the bundle's identity path; then `synchronizePostDerivedState()` with the label and title flags from the files written. A replay skips the refresh when `canIncrementallyUpdateReadModel()` is true and the post is already in the read model. Page clearing and the response stay as they are.
- Verification approach: new tests in `WriteApiSmokeTest` compare with a fresh full rebuild (`ReadModelSnapshot`) for a thread and a reply from a known identity and from a new identity, a post with labels in its body and a reply with a title directive; a replay runs no rebuild; a stale read model falls back and logs. Existing signed-post tests.
- Estimate details: driver: the new-identity equality cases; uncertainty: medium.
- Risks or open questions:
  - Impact: a miscount would show wrong totals until the next full rebuild.
  - Early warning / validation: the equality tests run before the route changes.
  - Mitigation: fix the order or send that case to the fallback.
- Canonical components/API contracts touched: `LocalWriteService::createSignedPost()`, `synchronizePostDerivedState()`, `synchronizeIdentityDerivedState()`.

## Stage 3 - Timing, docs and the live check

**Estimate: 0.06**

- Goal: measured speed, updated docs, and the zenmemes.com post.
- Dependencies: Stage 2.
- Expected changes: a *signed post bundle* concept entry; [rebuild triggers checklist](./rebuild_triggers_checklist.md) ticks items 1 and 3.
- Verification approach: time a signed post on the 30,000-quote local copy (target under 1 second, against about 7 seconds today). After deploy, "claude" posts a thread and a reply on zenmemes.com through `frm.py` and records the times and that both appear. Smoke set plus changed tests.
- Estimate details: driver: host round trip; uncertainty: low.
- Risks or open questions: if the host time misses about a second, report it before closeout.
- Canonical components/API contracts touched: concept entry and checklist only.

## Concepts Touched

[Derived read-model rebuild](../concepts/read_model_rebuild.md) used (fallback, Stage 1 logging); *signed post bundle* proposed, added in Stage 3.

## Study Checkpoint

2026-11-10T06:59:58Z; scope v1/A, starting revision `60ff7fd`, R1/P2; R1 hashes not re-verified. User explicitly approved Step 2. Whole-cycle **0.25** reaffirmed. Stage work 0.04/0.15/0.06, non-additive. Claude, single exposed rater. Await Approved Step 3.

*— Claude · 2026-11-10 UTC · Model: claude-opus-5-5*
