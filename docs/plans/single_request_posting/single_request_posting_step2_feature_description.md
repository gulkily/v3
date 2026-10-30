> **Feature plan:** [Step 1](./single_request_posting_step1_solution_assessment.md) · [Step 2](./single_request_posting_step2_feature_description.md) · [Step 3](./single_request_posting_step3_development_plan.md) · [Step 4](./single_request_posting_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/single_request_posting; [ledger](../effort_estimation_trial.md#cycle-v3single_request_posting), standing P2 enrollment
Extended estimates: enabled
Status: Revised v2/B requirements proposed; prior v1/A Step 2 approval retained historically
**Cycle estimate: 1.05**

## Problem and user stories

An agent with a fresh key currently needs separate registration and prepare/finalize exchanges before its signed post can be accepted.

- As an agent, I want to submit my locally signed post and public key in one request so first use needs no preliminary registration or preparation.
- As an author, I want to resend an uncertain submission safely so a lost response cannot duplicate my post or identity.

## Core requirements

- Provide an additive single-request API for ordinary signed threads and replies, with an executable project-owned example. Accept locally constructed canonical post text and its detached signature, with a public key and separately signed dedicated bootstrap post for first use; no prepare token, challenge or prior identity lookup is required on open instances. A new key requires the public key, bootstrap post and bootstrap signature; a known key may omit all three. Both records are signed locally and submitted together, with no server prepare exchange. Discovery and readback are optional separate requests, not prerequisites to a correctly formed submission.
- Clients may always include the public key. Identify it by fingerprint: create missing key/identity records once; reuse existing records without another copy, name reset, key update or approval change. Verify supplied keys match the declared signing identity. Keep the dedicated internal bootstrap post as the identity/approval anchor; the ordinary thread or reply never becomes that anchor. Known identities reuse their existing bootstrap. If bootstrap material is supplied for a known identity, validate its structure, signature and fingerprint binding but do not store another bootstrap or change the existing anchor. Concurrent first submissions using one key produce one identity, one bootstrap and each distinct valid ordinary post once; the first accepted registration wins.
- Validate both records' exact signed bytes and signatures, common identity binding, distinct IDs, bootstrap-only structure, allowed authored fields, text policy, timestamps and ordinary reply relationships before changing canonical state. A bootstrap payload may express registration only, never approval or another privileged operation. Accept only the ordinary-post header contract, not arbitrary parser-supported metadata. Derive paths on the server; reject conflicts and noncanonical input with actionable errors rather than rewriting signed content. Explicitly document local construction, time-skew bounds and accepted canonicalization; protocol constants will be pinned in Step 3 before implementation. Preserve existing authorship, site access and approval rules; a public key is not approval or a login session.
- New public key, identity record, dedicated bootstrap post/signature and ordinary post/signature succeed together in one canonical commit, or none are published. The identity record points to the dedicated bootstrap; no additional signature over the derived identity record is introduced. Exact retries resolve to the original accepted post/result without another canonical commit, including after a response is lost or the original timestamp ages out. Match the ordinary post's signed content and signer, not incidental public-key armor or signature encoding. Resending first-use bootstrap material cannot create a second bootstrap; retry of the committed ordinary post remains valid after bootstrap time limits expire. Known identities may submit a retry without bootstrap material. Conflicting content under the same post ID fails. Removed posts cannot be resurrected by replay. Failed derived-state updates remain recoverable from the accepted canonical record; concurrent retries and interrupted writes cannot overwrite unrelated records or register orphan identities.
- Existing prepare/finalize, registration, unsigned and browser flows remain supported. Return structured machine-readable success/errors with post/identity references and clear recovery for bad signatures, unsupported fields/text, clock mismatch, collisions, busy or unknown outcome and access denial. Keep identity separate from membership; new unapproved identities cannot bypass a members-only gate. On such instances the existing registration/approval/authentication flow remains available. Document the one-request path and its limits across all affected current guidance.

## Delivery scope and completion boundary

Work type: application change. Deliver API acceptance, shared validation/transaction/recovery integration, an executable standalone example and focused verification/documentation. Reuse the existing Key: value post, public-key and identity formats and detached signature storage; machine API envelopes may use JSON. No external components or copied third-party client code; dependency inventory is zero. Existing data and signed bytes remain readable without migration. No read-model schema change is intended; any discovered schema dependency must follow CONTRIBUTING and be included in the reviewed Step 3 plan.

Excluded: browser migration, votes, renames, images, event/task/QDB-specific posting workflows, automatic approval/session establishment, frm.py implementation, unrelated documentation cleanup and history purge. Ordinary thread/reply support must still obey the selected site's posting policy. The paused frm.py plan will later be revised toward this endpoint with old-instance fallback; this cycle only records the handoff.

Known environment: an agent in a fresh local workspace posting to an open ZenMemes instance. Prove the path with local GPG and an isolated representative server: one submission HTTP request contains the public key, signed dedicated bootstrap and signed ordinary post, followed by optional readback. Reject invalid input with enough information to correct and re-sign; recover a lost response by resending the same signed post or checking its ID. Local evidence does not establish deployment on zenmemes.com. Release requires post-deployment live discovery/read checks; live posting requires a separately authorized test destination. No deployment or production write is authorized here.

## Risks and early validation

- **Atomicity and replay:** partial identity writes or duplicates would violate the outcome. Inspect current write/recovery and removal semantics before Step 3; plan rollback/reconciliation and fault/concurrency tests around one canonical commit, without adding a new retraction subsystem.
- **Client-controlled records:** accepting privileged headers, paths or altered signed bytes could change trust. Before Step 3, enumerate the ordinary thread/reply allowlist and exact validation rules against existing authoring behavior; reuse authoring checks, not parsing alone.
- **Bootstrap bundle:** inconsistent signatures, IDs or key ownership could register the wrong identity; concurrent first use could duplicate anchors. Before Step 3, specify the dedicated bootstrap contract against existing registration and validate binding, first-registration-wins and retry ordering. Retain current approval anchors and verify that deleting an ordinary first post leaves the identity/bootstrap intact through rebuild/export/import.
- **Scope and compatibility:** docs can incorrectly imply all browser or members-only actions now take one request. Audit references and current API behavior, explicitly separate the additive agent path and retain old examples as legacy-compatible flows.

## Shared component inventory

Reuse/extend LocalWriteService validation and canonical transaction handling, OpenPGP inspection/verification, post/identity parsers and path resolution. Add one API entry point with Application access/routing and ApiRouteCatalog wiring. Reuse identity/profile indexing, canonical readback, recovery, export/import and rebuild consumers; verify any assumptions about bootstrap anchors. Existing profile/user/pending-approval views, approval history and signed approval endpoints continue to use dedicated bootstrap anchors. Reuse their current conventions; add regressions for combined registration/publication and later ordinary-post deletion. These surfaces require regression coverage, not UI redesign. Extend LlmsTxtRenderer and agent examples/reference; keep existing browser signing and publisher CLIs unchanged.

## Simple user flow and acceptance

1. Locally generate or select a key; construct and sign the ordinary thread/reply. For first use, also construct and sign a separate dedicated bootstrap post. Keep stable distinct IDs for retries.
2. Submit both records/signatures and public key in one request; server verifies and atomically accepts the ordinary post and any missing identity/bootstrap. Known identities can omit bootstrap material.
3. Receive identifiers and read back the unchanged signed content. For uncertain outcome, resend the same record; for invalid input, correct and re-sign without losing the draft.

Acceptance: fresh-key thread and fresh-key reply each succeed with exactly one submission request and one canonical commit; repeat posting with the same public key preserves one identity and its current name/approval. Known-key/bootstrap omission works; including them again or racing distinct bootstrap proposals never replaces the winning anchor. Deleting the first ordinary post leaves the dedicated bootstrap and existing identity/approval intact. Exact/concurrent/aged retries create no duplicates; conflicts, invalid signatures on either record, mismatched signers, invalid bootstrap fields, invalid parent binding and removed-ID replay create no canonical records. Fault tests demonstrate atomic recovery and derived-state rebuild. Existing prepared, unsigned, approval and members-only behavior remains intact. Documentation/examples match the actual protocol and disclose release limits.

## Documentation inventory

Step 3 must assign exact changes and verification owners for this inventory and repeat the assumption search before closeout. Existing historical claims stay historical; do not rewrite approved earlier decisions as if they described this feature.

| Source | Required disposition |
| --- | --- |
| docs/reference/agent_api.md | Primary bundled one-request flow, separate bootstrap/post signatures, exact fields/IDs/time rules, known-key/bootstrap reuse, retries, errors and existing prepare fallback. |
| src/ForumRewrite/Http/LlmsTxtRenderer.php | Generated llms.txt discovery and agent guidance; distinguish browser/legacy flows and current instance access. |
| src/ForumRewrite/Http/ApiRouteCatalog.php | New route, access, method and response contract in generated API index. |
| docs/specs/canonical_post_record_v1.md | Clarify separate ordinary-post and dedicated bootstrap subsets, allowed fields/tags and exact signed-byte requirements without changing old records. |
| docs/specs/identity_bootstrap_record_v1.md | Retained dedicated bootstrap anchor, locally signed bootstrap submission, known-fingerprint reuse and continuity when an ordinary post is removed. |
| docs/specs/public_key_storage_v1.md | Existing-key reuse, no implicit key updates or duplicate storage. |
| docs/reference/authentication_modes.md; docs/architecture/public_architecture_and_trust.md | Additive signed registration/posting versus session and approval; keep browser-flow statements accurate. |
| docs/examples/agent_single_request_post.sh (new); docs/examples/agent_api_worked_example.sh | Runnable one-request example; retain and label the existing prepare-flow example, with cross-links. |
| README.md | Link new agent entry/example and qualify affected registration/posting claims only. |
| docs/plans/agent_api_onboarding_checklist.md | Track this protocol and retry gap closure only when verified. |
| docs/plans/ui_first_post_identity/ui_first_post_identity_step1_solution_assessment.md and its Step 4 summary | Append G2 relationship/status notes; posts gain a new capability, browser/vote work remains pending. |
| docs/plans/agent_posting_step3_development_plan.md | Append API handoff and future client replan requirement; preserve pause and original estimates/approvals. |
| This cycle's Step 1–4, debug log, docs/plans/README.md and effort_estimation_trial.md | Normal planning/index/verification/study records; index updates at prescribed organization step. |

Review-only search targets: historical agent_api_docs, identity bootstrap recovery/verification and profile_registration_cost plans, plus other matches for mandatory prepare or separate registration. Add narrowly necessary current-guidance corrections to Step 3; retain historical narratives and unrelated cleanup. Navigation-only corrections for moving this cycle's artifacts are allowed. todo.txt remains untouched.

## Approval and study checkpoint

Request dispositions: known public keys may always be supplied and are reused (user clarification); implement this protocol before resuming frm.py (recommended order); catalog affected docs in Steps 2/3 (user request). The later user request selects Option B: retain a separately signed bootstrap post in the same HTTP request. This changes the anchor decision but does not expand to G2 votes/browser work.

Checkpoint: 2026-10-29T20:11:53Z; Step 1 explicitly approved, scope v1/A clarified, baseline b3a5b1621e9df25bac6e023c26aca66537852dfe, R1/P2, Codex single exposed rater. Whole-cycle 0.85 reaffirmed; frozen-reference verification inherited from this session, missing pristine intake preserved. No runtime tests, implementation, commits, measured labor or accepted outcome. Await Approved Step 2 before development planning. frm.py stays paused.

*— Codex · 2026-10-29 UTC*

## Revised scope and approval checkpoint

Date: 2026-10-29T22:37:54Z
Scope: v2/B — separate signed bootstrap and ordinary post in one request
Approval history: The user approved Step 2 v1/A and Step 3 investigation began, but no Step 3 artifact or implementation was produced. The user then rejected the real-post-anchor choice and requested this revision. Prior v1/A requirements and 0.85 estimate are preserved in Step 1's original option/history and the earlier checkpoint above; current requirements replace that choice with the dedicated bootstrap bundle.

Whole-cycle estimate: 1.05 (original Option B estimate reaffirmed; scope change from v1/A 0.85). This exceeds the comfortable-cycle reference: retain one cohesive one-request deliverable provisionally; splitting out registration would add an intermediate multi-request API and would not itself deliver the requested outcome. Review stage sizing before approval of implementation; rescope if necessary. Same baseline/R1/P2/single exposed Codex rater and missing pristine intake. No independent rating, measured labor, runtime tests, implementation or accepted outcome.

Next gate: Approved Step 2 for these revised requirements, then resume Step 3 planning. frm.py remains paused. The revision request authorizes these planning edits, not implementation.

*— Revised by Codex · 2026-10-29 UTC*

Approval update: 2026-10-29T22:59:48Z — user approved revised Step 2 v2/B. Separate signed bootstrap and ordinary post remain the selected contract; Step 3 planning authorized, implementation still gated.

*— Codex · 2026-10-29 UTC*
