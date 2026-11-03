> **Feature plan:** [Step 1](key_retirement_step1_solution_assessment.md) · [Step 2](key_retirement_step2_feature_description.md) · [Step 3](key_retirement_step3_development_plan.md) · [Step 4](key_retirement_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/key_retirement — [ledger](../effort_estimation_trial.md#cycle-v3key_retirement), standing P2 enrollment
Extended estimates: enabled
Status: Step 1 v2/A approved on 2026-11-01; Step 2 proposed; no implementation approval

**Cycle estimate: 1.00**

## Original Query

“Yes, it should sync in recent activity order, ideally. That is definitely a lot of keys though; we should do something about that too, or one could easily DoS the server.”
“I agree. Do you think we should also add a configurable limit on the number of keys per user?”
“Sounds like we should write three separate Step 1 assessments.”

## Understood intent and problem

Provide deliberate retirement of obsolete account keys so devices can be replaced and key counts reduced without destroying historical signatures or encrypted access. Retirement here means withdrawing future account access, not deleting public keys, signed content or approval history.

## Option A — Signed retirement by another account key or a root-approved operator

**Estimate: 1.00**

Let a member use another active authorized key of the same account, or let an active root-approved operator retire a key on any account, through a signed, reviewable action. Keep public verification material and historical evidence; stop retired keys from authenticating, acting as members or receiving new message access, including through existing sessions.

- Benefits: Handles obsolete and lost devices, including operator-assisted replacement when the member cannot use an existing key; supplies the capacity-release path needed by the cap.
- Costs: Adds explicit operator authority and cross-account verification to the existing authorization, session invalidation and historical transfer/signature obligations. Same username alone is not authority; retained root seed evidence must not let a retired operator act.

## Option B — Self-retirement only

**Estimate: 0.65**

Require the retiring key itself to sign its retirement, retaining the same historical preservation and future-access restrictions.

- Benefits: Narrower authority and a simpler initial lifecycle.
- Costs: Cannot retire a lost device whose private key is unavailable; therefore cannot reliably unblock replacement at the cap. Lost-device management would remain separate work.

## Recommendation and acceptance boundary

Choose A. An authenticated member using another active same-account key, or an authenticated root-approved operator using an active key, identifies the exact target, reviews the access consequence and signs the retirement. Verify that new and already-open target sessions cannot perform protected actions. The signed record binds the target and authorizing identity; a label or matching username alone is insufficient.

Operator authority follows the existing root-approved operator classification, with current active eligibility checked separately. Retirement is independent of `FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL`: ordinary members cannot retire another account's keys with either flag value. Recheck authority and target state at acceptance, including a prepared request whose operator key has since retired. Preserve valid historical retirement authority during replay instead of requiring the operator to remain active forever.

Both paths preserve at least one active approved key for the target account, including concurrent requests; operators cannot bypass this protection. When the sole remaining key is lost, the operator first approves a replacement through the existing approval flow, then retires the lost key. This permits assisted replacement, not disabling an account by retiring its final key or recovering plaintext without retained encrypted access. A future cap must explicitly accommodate this replacement sequence; no cap override is introduced here.

*— Codex · 2026-11-01 UTC (Option A and recommendation revision)*

Retain original signed bytes and public keys for verification. Preserve previously valid historical messages/transfers without treating retirement as retroactive evidence that old signatures were invalid; explicitly handle compromised-key revocation as a separate policy, not a synonym. Already delivered plaintext/session keys cannot be recalled. Current recovery rechecks donor approval, so avoiding accidental loss of retained access is an early design gate. Verify replay/rebuild, export/restore, interrupted submission and repeated retirement, including no double capacity release. Old approvals or stale sessions must not silently reactivate a retired key; define an explicit reactivation policy before Step 2 approval.

Use canonical signed plain-text records and the existing prepare/review/submit pattern, with proportional rendered phone/desktop and relevant-theme checks. No automatic expiry, inactivity-based revocation, deletion of private messages, broad account deletion, or new dependencies. Prefer existing models; if derived schema changes are needed, follow version/rebuild requirements.

Dependencies: Can ship without the [cap](../active_key_limit_step1_solution_assessment.md); the cap relies on this safe replacement path. [Sync correction](../message_sync_activity/message_sync_activity_step1_solution_assessment.md) ships independently. Retirement never silently cleans up the existing 115-key account.

Concepts touched: Key retirement and active-key eligibility are proposed new inventory entries. [Signed approval review/acceptance](../../concepts/approval_acceptance.md) reused as patterns, with approval evidence preserved. [History recovery/sender assistance](../../concepts/private_message_history.md) eligibility changes while historical verification remains available. Public signature verification remains distinct from current authorization.

## Study snapshots and approval

Scope: v1/A; baseline 4b14e622f3cca6cb8f041145fd1df954bacc74e4; R1/P2; Codex single exposed rater. All three frozen R1 source hashes verified. Prior discussion, related implementation/diagnostics and initial source inspection visible; pristine pre-inspection intake unavailable, not reconstructed.
Intake: 2026-10-30T11:27:22Z; 0.95, exposed estimate.
Step 1 handoff: 2026-10-30 UTC; 0.95 reaffirmed for the recommended complete slice, including recovery, verification and documentation. Option estimates are whole cycles, not additive, time estimates or measured labor. Include this cycle's concept/docs/debug/ledger upkeep; no runtime checks performed for this assessment. Prior diagnostic runs are evidence, not new acceptance. Maintainer acceptance pending; planning remains uncommitted. Await Approved Step 1 for this named cycle before Step 2.

*— Codex · 2026-10-30 UTC*


## Current-code review — 2026-11-01

Reviewed revision: f82bdc3e9b05d35e320cafa357b80d22fbbb3d6d (main).
Disposition: Option A remains relevant; no key-retirement lifecycle was found in the reviewed authorization/rebuild paths. Bounded message recovery is now merged, so it is an existing integration constraint, not pending prerequisite work. This review does not approve Step 1 or implementation.

- Account authority: [ApprovalPolicy](../../../src/ForumRewrite/Security/ApprovalPolicy.php) classifies account keys using canonical username plus approved identity state. Specify how signed, accepted membership establishes retirement authority; a matching user-supplied name alone remains insufficient. This is a design decision, not an existing retirement authorization API.
- Historical evidence: [approval replay](../../../src/ForumRewrite/ReadModel/ReadModelBuilder.php) derives a grant graph from seeds, signed approvals and invitations. Retiring a key must not erase its historical grants or accidentally unapprove people it previously approved. Define reactivation and concurrent reciprocal retirement before implementation; serialization must preserve at least one usable account key.
- Message recovery: [the service](../../../src/ForumRewrite/Messaging/PrivateMessageHistorySyncService.php) currently shares approved-key lookup across destinations, original-signer fallback, donor transfers and receipt validation. Separate active authorization from retained historical verification/transfer eligibility. Merely clearing approval can exclude old signers and donors. Retirement must update [revision invalidation](../../../src/ForumRewrite/Messaging/PrivateMessageHistoryRevision.php), reject stale work for retired destinations, and retain bounded continuation and old-client compatibility.
- Authentication: [sign-in](../../../src/ForumRewrite/Http/AuthApiController.php) verifies a known key without requiring approval; [session rotation](../../../src/ForumRewrite/Http/SessionRotation.php) forwards old session IDs for 30 seconds. Distinguish retirement from ordinary unapproved identity status, and check both new authentication and authorization on existing/forwarded sessions. Deleting one session is not sufficient.
- Signed actions: [single-request identity resolution](../../../src/ForumRewrite/Write/SignedPostIdentity.php) can verify known keys independently of browser sessions. Include direct signed-action paths in the eligibility review, retaining historical signature validity and basic participation. Retirement removes account privileges; it must not silently turn cryptographically valid old signatures into invalid signatures.

Verification: Source and baseline-difference inspection only; no runtime, browser, deployment or full-suite checks performed. Corrected the moved sync-assessment link. Dependency delta: zero. Unresolved policy choices already belong to the original authority/recovery scope; Option A remains the recommendation, with Option B still unable to retire a lost key.

Study revision checkpoint: railspike-effort / v3/key_retirement; 2026-11-01 UTC; scope v1/A clarified, original baseline 4b14e622f3cca6cb8f041145fd1df954bacc74e4 retained; R1/P2; Codex single exposed rater. Exact three frozen R1 hashes reverified. Whole-cycle 0.95 and alternative B 0.65 reaffirmed: current integrations clarify the already-included authorization, historical-access and verification work rather than adding a separate outcome. Original intake/handoff preserved; no measured labor, independent rating or accepted-count increment. Await Approved Step 1 before Step 2.

*— Codex · 2026-11-01 UTC*


## Operator retirement expansion — 2026-11-01

User direction: “i feel like operator retirement would be good?” followed by “please expand it” after the same-account/operator proposal, independent approval-flag policy and last-active-key protection were described.
Scope: v2/A adds signed cross-account retirement by an active root-approved operator. Same-account retirement remains; ordinary member cross-account retirement, final-key disabling and new feature flags remain excluded. Historical v1/A and its 0.95 estimate above are preserved. The current recommendation incorporates this expansion; Step 1 is still unapproved.
Acceptance additions: Operator retirement succeeds across accounts with either approval-flag value; unrelated ordinary members fail with either value; retired operators and stale prepared authority fail; signed retirement survives rebuild/export/restore without erasing past approvals or message access. Last-active-key and concurrent-retirement protection apply equally to members and operators.
Study revision checkpoint: railspike-effort / v3/key_retirement; 2026-11-01 UTC; scope v2/A; original baseline 4b14e622f3cca6cb8f041145fd1df954bacc74e4; R1/P2; Codex single exposed rater. Whole-cycle projection revised from 0.95 to 1.00 for added operator authority, replacement and cross-account acceptance coverage. Option B remains 0.65, unchanged self-retirement scope. Exact frozen references verified in this session; no measured labor, independent rating or accepted-count increment.
Verification: Planning text and whitespace checked; no application changes or runtime tests. Dependency delta zero. Await Approved Step 1 before Step 2.

*— Codex · 2026-11-01 UTC*


Approval record: 2026-11-01 UTC — user said “approved step 1” for expanded Option A (v2/A). Continue with the Step 2 feature description; earlier pending-approval notes are historical. No implementation approval.

*— Codex · 2026-11-01 UTC*
