> **Feature plan:** [Step 1](./username_rename_collision_step1_solution_assessment.md) · [Step 2](./username_rename_collision_step2_feature_description.md) · [Step 3](./username_rename_collision_step3_development_plan.md) · [Step 4](./username_rename_collision_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, cycle `v3/username_rename_collision`; standing P2 enrollment; [ledger](../effort_estimation_trial.md#cycle-v3username_rename_collision).

## Completion Contract

- **Entry:** with `FORUM_USERNAME_CHANGES_ENABLED` on, Account → Choose username on any key, and Account on a holder or operator key.
- **Outcome:**
  - A free name (including `guest`) saves at once.
  - A held name becomes a pending join request, and the requester's name is unchanged.
  - When a holder or operator accepts, the grant and the rename are saved in one commit.
- **Recovery:**
  - Decline, cancel or 7-day expiry leaves the name unchanged, and the requester can ask again.
  - Any failure before the commit writes nothing.
  - A grant that's no longer valid, such as an expired request or a holder who was replaced, is refused with a clear message.
- **External verification:** a two-browser run on this WSL setup (`guest` → join `ilya`), on phone and desktop widths, in the default and icon themes.
- **Release:** focused and full suites pass or have documented baseline failures; the flag still ships off; no read-model schema change.

## Key Risks

- **High risk:** a direct endpoint call bypasses the held-name check.
  - Early validation: Stage 1 tests call finalize directly.
  - Mitigation: enforce in finalize under the write lock, not only in prepare or the UI.
- **High risk:** the new grant record family changes approval state, or breaks a rebuild or incremental update.
  - Early validation: Stage 3 rebuild-equivalence and incremental tests.
  - Mitigation: grants are audit-only; the read model skips them.
- A held request outlives what it depended on, for example the holder renamed away or the requester renamed elsewhere.
  - Validation and mitigation: accept re-checks name, holder authority, request expiry and that the requester's name hasn't changed since the request, all in the same lock.

## Stage 1
- Goal: a rename can never be saved directly into a held name.
- Dependencies: none.
- Expected changes:
  - `isUsernameHeld(token, exceptIdentity): bool` (any other profile, approved or pending; `guest` is never held).
  - prepare reports `held: true`; finalize refuses a direct save of a held name.
- Verification approach: unit tests for free, held-approved, held-pending-only, own-name, `guest` and direct-finalize cases. Existing `WriteApiSmokeTest` still passes.
- Risks or open questions:
  - Impact: the free-name behaviour must not change.
  - Early warning / validation: existing rename tests unchanged.
  - Mitigation: one shared helper used by both entry points.
- Canonical components/API contracts touched: `LocalWriteService` prepare/finalize profile name; `ProfileRepository::byUsernameToken`.

## Stage 2
- Goal: finalizing into a held name stores a signed join request instead of refusing.
- Dependencies: Stage 1.
- Expected changes:
  - The prepared-record store gains a `join-request` kind with a 7-day expiry, one per key (a new request replaces the old).
  - finalize returns `status: pending`.
  - New `POST /api/cancel_profile_name_request` and `GET /api/profile_name_request`; route catalog updated.
- Verification approach: tests for store, replace, cancel, expiry and the signature still verified before storing.
- Risks or open questions:
  - Impact: expired files pile up.
  - Early warning / validation: an expiry test.
  - Mitigation: prune expired requests whenever requests are listed.
- Canonical components/API contracts touched: prepared-post store; `ApiRouteCatalog`; `Application` route allowlist.

## Stage 3
- Goal: introduce the signed grant record family as audit-only.
- Dependencies: none (can run in parallel with Stage 2).
- Expected changes:
  - `records/profile-name-grants/` with a parser (grantor identity, grantee identity, username, request record ID, `Created-At`) and a detached signature.
  - Path validation allows the family; full and incremental read-model updates ignore it; activity labels the path.
- Verification approach: parser tests; full rebuild before and after adding a grant gives identical approval and profile state; an incremental update over a grant commit succeeds.
- Risks or open questions: see Key Risks (grant family).
- Canonical components/API contracts touched: `SourcePathValidator`, `ReadModelBuilder`, `IncrementalReadModelUpdater`, `ActivityService` path labels.

## Stage 4
- Goal: a holder or operator can accept or decline; accepting saves the grant and the rename together.
- Dependencies: Stages 2 and 3.
- Expected changes:
  - `GET /api/profile_name_join_requests` lists requests a viewer may answer: approved holders of the name, operators for all.
  - `prepare_`/`finalize_profile_name_grant` re-check validity and write grant plus rename in one commit.
  - `decline_profile_name_request`.
  - Authority reuses `ApprovalPolicy` holder and operator rules; a name held only by pending profiles can be accepted by an operator only.
- Verification approach: tests for holder accept, operator accept, non-holder refusal, pending-only name, stale request, holder renamed away, and that a declined or used grant can't be replayed.
- Risks or open questions: see Key Risks (stale requests).
- Canonical components/API contracts touched: `ApprovalPolicy` (extracted holder/operator check), `LocalWriteService` commit path, `WritePostAndIdentityApiController`.

## Stage 5
- Goal: the requester's side in the UI.
- Dependencies: Stage 2.
- Expected changes: after signing a held name, Choose username shows the pending request with Cancel; it shows the expiry only when it's within a day.
- Verification approach: phone and desktop in the initial, pending, cancelled and expired states, in the default and icon themes; copy follows `CONTRIBUTING.md`.
- Risks or open questions: none beyond the copy review.
- Canonical components/API contracts touched: `templates/pages/account_key.php`, `browser_signing.js` rename handler.

## Stage 6
- Goal: the holder's side in the UI.
- Dependencies: Stages 4 and 5.
- Expected changes: Account shows a join-requests list only when it isn't empty, with Accept (which signs) and Decline, reusing the existing signing flow.
- Verification approach: empty (hidden), one and several requests, accept and decline outcomes, a failure message; phone and desktop; themes; long-name overflow.
- Risks or open questions: none beyond the copy review.
- Canonical components/API contracts touched: `account_key.php`, `browser_signing.js` signing helpers.

## Stage 7
- Goal: documentation.
- Dependencies: Stages 1–6.
- Expected changes: `profile_read_contract_v1.md` (Composite User: join rule), `invitation_entry.md` (choosing a username), and the follow-up note in `username_change_report.md`.
- Verification approach: link check; the docs match the behaviour seen in the tests.
- Risks or open questions: none.
- Canonical components/API contracts touched: docs only.

## Stage 8
- Goal: replay the Known user scenario and close out.
- Dependencies: Stages 1–7.
- Expected changes: none to code, apart from fixes found during the replay.
- Verification approach:
  - Two browsers on WSL: key A is `ilya`, key B starts as `guest`; B requests `ilya`, A accepts, and `/user/ilya` lists both.
  - Repeat the run with decline, cancel and forced expiry.
  - Check that a free rename is unchanged.
  - `./v3 rebuild` produces the same approval state; full suite.
- Risks or open questions: none.
- Canonical components/API contracts touched: none.

## Execution and study checkpoint

2026-10-23 UTC; scope v3 (Step 2 approved, including the 7-day expiry and operator-only acceptance for names with only pending holders); start `8a2d59c1`; Claude (Opus 5.5), single exposed rater; R1/P2. Step 3 handoff: **0.70**, revised up from 0.65. This is new understanding, not a scope change: the grant family needs path validation and the incremental updater to ignore it, and accepting needs a stale-state re-check. The plan uses the 8-stage limit. Earlier snapshots are kept. Not measured effort.
