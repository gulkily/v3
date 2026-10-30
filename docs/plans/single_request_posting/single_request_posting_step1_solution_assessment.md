> **Feature plan:** [Step 1](./single_request_posting_step1_solution_assessment.md) · [Step 2](./single_request_posting_step2_feature_description.md) · [Step 3](./single_request_posting_step3_development_plan.md) · [Step 4](./single_request_posting_step4_implementation_summary.md)

Debug mode: enabled (project marker present)
Effort study: railspike-effort / v3/single_request_posting; [ledger](../effort_estimation_trial.md#cycle-v3single_request_posting), standing P2 enrollment
Extended estimates: enabled
Status: Revised to Option B by user direction; revised Step 2 awaiting approval
**Cycle estimate: 1.05**

## Original Query

let's pause and write a plan for making it possible

## Understood Intent

Pause [frm.py planning](../agent_posting_step3_development_plan.md). Make an agent's signed thread or reply, including a previously unknown public key and identity, publishable in one HTTP request without a prepare token or prior registration. Local key generation/signing is not a network round-trip; optional discovery/readback and recovery requests do not count as the initial submission. This is a staged feature assessment, not Railspike one-shot execution authorization.

Problem: Current first-use signed posting needs four sequential requests; registered identities still need two.

## Option A — The signed first post establishes the identity (superseded)

**Estimate: 0.85**

Add an API entry point accepting a complete locally constructed canonical post, detached signature and optional public key. For a new key, verify the signed post and derive the existing identity/public-key records using that post as the bootstrap anchor; commit the identity and post together. Existing identities use the same endpoint without registration.

- Benefits: one request and one post signature; existing identity format already references the post/thread that introduced the key; no extra bootstrap post is needed.
- Costs: client must construct valid IDs, timestamps and canonical text. Server must strictly validate authored fields and identity binding before any write, with no silent changes to signed bytes.
- Driver/uncertainty: shared validation, atomic registration/publication, replay recovery and rebuild/export compatibility. Verify ordinary-post bootstrap anchors across profile/approval consumers before implementation.

## Option B — Submit separately signed bootstrap and post together

**Estimate: 1.05**

Accept a client-built bootstrap record and signature alongside the signed post and public key, then register and publish them in one transaction and HTTP request.

- Benefits: retains a distinct signed bootstrap object and more closely resembles today's registration evidence.
- Costs: two signatures and additional records for the same user action; more validation and recovery combinations. Above the comfortable-cycle yardstick, so split discussion is required if selected.
- Driver/uncertainty: consistency across the two signed objects, transaction recovery and existing bootstrap consumers; no trust benefit assumed without identifying a concrete requirement.

## Original recommendation and boundaries (superseded by revision below)

Choose A as a complete agent-facing slice, demonstrated by an executable project-owned example from a clean local keyring through signed thread/reply submission and readback. Keep existing prepare/finalize and unsigned endpoints compatible. Document the new endpoint for any client; frm.py integration waits for resumption and replanning of its paused cycle. No browser migration, votes, renames, attachments or special typed posts in this slice.

Required design constraints for Step 2:

- New identity plus post is one locked transaction and one canonical commit; invalid post/signature cannot leave a registered identity behind. Preserve signed bytes and current text rules. Names never prove key ownership; no implicit approval or session authentication, and members-only access remains enforced.
- Client-generated IDs, timestamp bounds, allowed headers, parent/thread binding and public-key fingerprint binding must be explicitly specified. A parser accepting a record is insufficient authorization to publish privileged/import-only metadata.
- Exact duplicate submission returns the existing result without duplicate content or commits; the same ID with different signed content conflicts. Check committed retry identity before applying new-submission age limits. Lost responses, concurrent first posts for one key, partial write failures and derived-state recovery need acceptance evidence.
- Retry must not recreate removed posts or reset an existing identity's name/approval. Use current removal/record semantics without reviving canceled retraction diagnostics or expanding history-purge work.
- Client drafts/configuration and canonical records stay readable Key: value text. JSON may transport machine API fields. No external dependencies, supplied third-party client code, migration or new canonical format is proposed; verify whether schema changes are actually needed before planning them.

Relationship: [G2](../ui_first_post_identity/ui_first_post_identity_step1_solution_assessment.md) proposed two requests for first post/reply/rename and one for votes. This cycle proposes the stronger one-request post capability only; it does not silently approve or replace all G2 browser/vote work. The original agent-posting request remains paused and unfulfilled, not counted complete.

## Study checkpoint and approval

Checkpoint: 2026-10-29T19:57:39Z, Step 1 handoff; scope v1/A; baseline b3a5b1621e9df25bac6e023c26aca66537852dfe; R1/P2; Codex single exposed rater. Inherited frozen references were verified in this session. Prior inspection includes the current posting flow and G2 plans; pristine intake is missing, not reconstructed. Whole-cycle projection 0.85; alternative B 1.05, not additive or hours. G2's inherited 0.60 concerned a different scope and is not this cycle's estimate. No implementation, tests, commits, measured labor or accepted outcome. Study bookkeeping is within this cycle's documentation scope; retain linkage to agent_posting and G2 without counting their outcomes as delivered here.

Await Approved Step 1 to define requirements. Preserve all paused frm.py approvals and estimates; do not resume it or implement this API on the strength of this planning request.

*— Codex · 2026-10-29 UTC*

Approval update: 2026-10-29T20:11:53Z — user approved Step 1 Option A. Clarifications carried to Step 2: always-supplied known keys are reused, protocol first while frm.py stays paused, and documentation inventory required. No implementation authorization.

*— Codex · 2026-10-29 UTC*

## Revision — separate bootstrap retained

Date: 2026-10-29T22:37:54Z
Scope: v2/B
Direction: User objected to the actual first post becoming the bootstrap and explicitly requested revising toward Option B. This supersedes the real-post-anchor choice above. Step 2 for v1/A was approved before this revision; that approval does not cover the changed requirements.

Keep a dedicated internal bootstrap post and its detached signature, separate from the ordinary signed thread/reply. For a new identity the client sends both signed records and the public key in one HTTP request. The server validates the bundle and commits public key, identity record, bootstrap post/signature and actual post/signature together. The identity record remains anchored to the dedicated bootstrap. This is two local signatures, not two network requests.

Known identities reuse their existing bootstrap; public keys may always be included. Bootstrap material may be omitted for a known identity. If supplied for an already registered key (including a concurrent first-use race or retry), validate its binding/signature but do not create, replace or repoint a bootstrap. The actual post's committed identity/content remains the retry key; extra bootstrap material cannot cause another registration.

Recommendation: Option B as one end-to-end cycle; keep browser migration, votes, renames and frm.py integration separate. Projection 1.05 is above the comfortable-cycle reference. The split alternative is a preliminary client-authored registration cycle followed by combined submission; that would temporarily preserve two requests and add an intermediate API. Recommend retaining the cohesive combined-submission scope, with a sizing review at Step 3 and a split if it cannot fit eight bounded stages. This is a scope discussion, not implementation approval.

Study revision: v2/B whole-cycle 1.05, replacing preserved v1/A 0.85 due to the explicit scope change; original Option B estimate 1.05 reaffirmed. Same baseline/R1/P2/single exposed rater; missing pristine intake retained. No implementation or accepted outcome. [Revised Step 2](./single_request_posting_step2_feature_description.md) is the current requirements artifact; no repeated Step 1 approval is needed for the direction explicitly requested by the user.

*— Revised by Codex · 2026-10-29 UTC*
