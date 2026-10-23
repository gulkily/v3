> **Feature plan:** [Step 1](./username_rename_collision_step1_solution_assessment.md) · [Step 2](./username_rename_collision_step2_feature_description.md) · [Step 3](./username_rename_collision_step3_development_plan.md) · [Step 4](./username_rename_collision_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, cycle `v3/username_rename_collision`; standing P2 enrollment; [ledger](../effort_estimation_trial.md#cycle-v3username_rename_collision). Related: [username_change_gate](../username_change_gate/).

## Original Query

I agree with your suggestions, please write step 1. Assess the complexity of each option.

## Understood Intent

Close the gap where an approved member's signed `set-name` rename joins another composite user without that user's or an operator's approval. That gap gives the renamer `/user/<name>` membership, new private messages sent to that name, and "same username" authority to approve more keys. Compare the options and their complexity before `FORUM_USERNAME_CHANGES_ENABLED` is turned on anywhere. Evidence: [username change report](../../research/username_change_report.md#effect-on-composite-users). `LocalWriteService::prepareProfileName` checks only the name's format. Approval follows `identity_id`. `ApprovalPolicy` is enforced only when an approval is written; read-model approval replay ignores usernames and record order.

## Problem

A rename bypasses the rule that an additional key under an existing username needs approval from that username or an operator.

## Option A — Refuse renames into an occupied name

Prepare and finalize (under the existing write lock) refuse a name whose token another profile already holds. This matches how `ApprovalPolicy` is enforced today: at write time only.

- Pros: closes the bypass with no new record type, schema change or approval-derivation change. Clear error, nothing to undo.
- Cons: one member's keys can't move together. Once the first key renames to `bob`, the second can't follow. Squatting a vacated name is still possible.
- Complexity: **small, ~0.25** (close to numeric permalinks). Two write-path checks, error copy in Choose username, focused and browser tests, spec note.

## Option B — A, plus a "follow your group" exception

Same as A, but allow joining the name when every profile holding it previously held the renamer's current name. This lets one member's keys move to a new name one at a time.

- Pros: keeps multi-key members together, with no new records or schema.
- Cons: the rule depends on rename history and is harder to explain. Any approved member of the old group can still follow, which is the same trust as the existing same-username rule.
- Complexity: **modest, ~0.40**. A plus a rename-history lookup and edge-case tests (chains, a holder who left, mixed approved/pending holders).

## Option C — Joining an occupied name makes the profile pending

The rename always succeeds. Under a name that others already hold, the profile counts as unapproved until a holder of that name or an operator approves it again through the existing approval UI.

- Pros: uses the familiar approval flow and naturally supports multi-key members. Works for records that arrive by sync.
- Cons: approval derivation must become dependent on username and on record order (rename vs approval time). Read-model schema change and rebuild. Members silently lose posting rights until someone approves them.
- Complexity: **large, ~1.10, split discussion**. Changes canonical approval semantics, rebuild, incremental updates, pending-user surfaces and recovery.

## Option D — A, plus a join request the name's holder accepts

Same refusal as A for a direct rename into an occupied name. Instead, the renaming key signs its rename as usual and the server holds it as a pending join request. An approved holder of that name (or an operator) sees the request and signs a grant. The server then saves the grant and the held rename together, with no further step for the requester. Unanswered requests expire; the requester can cancel and the holder can decline. An operator may also grant ahead of a request. This covers merging your own keys, for example a phone that started as `guest` joining your existing `ilya` key, and keeps the same trust rule as approving an extra key.

- Pros: supports multi-key members with no key copying between devices. The trust model is unchanged, enforced at write time like approvals, with no approval-derivation or schema change.
- Cons: a new signed grant record, a pending-request store with expiry, and a place for holders to see and answer requests. Grants are scoped to one key and one name and are single-use.
- Complexity: **modest, ~0.65**. A plus grant record and parser, held-request storage and expiry, holder request list with accept/decline, combined save, and two-key browser tests including expiry and cancel.

## Recommendation

**Option D** (which includes A's refusal) as one vertical slice. Entry: Account → Choose username; the name's holder answers the request from their Account page. Outcome: renaming to a free name works as today (including `guest` → own name); renaming into an occupied name becomes a join request and completes when a holder accepts. Recovery: until it's accepted, nothing changes; requests expire, can be cancelled or declined, and the requester keeps their current name. This blocks enabling the flag and fits comfortably in one cycle. B is superseded by D. C would need a separate decision about approval semantics.

Assumption for Step 2: `guest` is shared by design, so renaming into `guest` is exempt from the occupied-name check.

Out of scope (separate follow-ups): private-message history staying under the old name token, and conversation metadata visible to whoever later takes a vacated name.

## Study snapshot

2026-10-23 UTC; scope v1; start `8a2d59c177303aed7a9f5b01852518992584fee1`; Claude (Opus 5.5), single exposed rater; R1/P2. R1 report and diff review were verified by SHA-256 at `/home/wsl/railspike-dev/docs/research/effort_estimation/`. The ledger's relative links point one directory short of that path; they were not changed. Exact feedback was restored from `4f6cfe2b` and its hash verified.

- Intake: **0.25**, recorded after inspecting the rename, approval and messaging code, so it is not a pristine prospective intake.
- Step 1 handoff (v1): **0.25 reaffirmed** for recommended Option A. Option scores above are comparative projections for the same scale. Not measured effort; no outcome.
- Step 1 revision (v2, 2026-10-23): **0.50** for recommended Option D (grant first). This is a scope change, not new understanding: the user's main use case is merging their own keys under one name, which A refuses. The v1 value is kept above.
- Step 1 revision (v3, 2026-10-23): **0.65** for Option D with request first. Scope change at the user's request: the rename is held as a join request and completes when the holder accepts, adding a pending-request store, expiry and a holder request list. The v1/v2 values are kept above.

## Enhancement Requests

- **Request:** support moving from `guest` to your own name, including joining your existing key under that name. **Disposition:** in scope; Option D added and recommended (v2). `guest` stays shared by design. **Approval:** pending Step 1 approval. **Outcome:** v2.
- **Request:** let the rename come before the grant. **Disposition:** in scope; Option D changed to request first, operator grant ahead of time kept (v3). **Approval:** pending Step 1 approval. **Outcome:** v3.
