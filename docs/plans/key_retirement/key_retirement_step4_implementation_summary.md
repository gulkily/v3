> **Feature plan:** [Step 1](key_retirement_step1_solution_assessment.md) · [Step 2](key_retirement_step2_feature_description.md) · [Step 3](key_retirement_step3_development_plan.md) · [Step 4](key_retirement_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/key_retirement — [ledger](../effort_estimation_trial.md#cycle-v3key_retirement)
Extended estimates: enabled
Status: Step 3 v3/A approved; Stages 1–7 complete; Stage 8 verification in progress

**Cycle estimate: 1.00**

## Entry and approval

2026-11-01 UTC: User said “approved step 3.” Feature branch feature/key-retirement; planning commit 15373adbb precedes this summary and implementation. Scope v2/A, original baseline 4b14e622f3cca6cb8f041145fd1df954bacc74e4; R1/P2; Codex single exposed rater. Step 3 whole-cycle 1.00 reaffirmed at entry, historical 0.95 retained. Frozen references verified this session. No measured labor or acceptance increment.

Original stage-work estimates from the approved plan: S1 authority/history gate 0.15; S2 records/replay 0.16; S3 active enforcement 0.17; S4 message recovery 0.20; S5 signed API 0.15; S6 shared journeys 0.16; S7 restore 0.15; S8 release evidence 0.12. Values are independent, non-additive judgments, not elapsed hours. Retrospectives pending; no stage complete. Preserve unrelated drafts and ledger history; no merge/deployment authorization implied.

## Stage 1 - Authority and historical eligibility boundaries

**Original estimate: 0.15**

- Changes: Added a real-signature characterization showing that an ordinary approved key can rename into another approved account's current name while preserving its original, different approval context. [Findings](stage1_authority_findings.txt) record source paths, consequences and the concrete policy decision.
- Verification: Approval acceptance/history synchronization/session baseline 33/33 passed; new signed rename/approval/rebuild characterization and existing approval-history test 2/2 passed. Whitespace checked. No retirement writer or runtime change.
- Notes: Same-account authority cannot reuse current username classification; legacy grants can lack accepted account context. Current-name key counts also cannot prove that a replacement belongs to the target account. Stage 1 remains incomplete pending the policy decision; no dependent stage has begun. Actual fixture roots/signing homes were cleaned up.
- Estimate details: Original 0.15 retained; retrospective unavailable because the gate is unresolved. Whole-cycle 1.00 remains the approved v2/A projection, not a completion assessment; revised projection pending the authority/legacy-coverage decision. No measured labor.

*— Codex · 2026-11-01 UTC*


## Required Step 2 decision

The approved Stage 1 gate found a concrete gap in accepted account authority. [Proposed clarification](key_retirement_step2_feature_description.md#proposed-authority-clarification--stage-1-gate) narrows member self-service to proven accepted membership and routes missing/conflicting legacy evidence to operator assistance; the broader alternative is a separately scoped durable account-association flow. Neither is silently assumed approved. Preserve existing Step 1–3 approvals as v2/A history; new scope/coverage requires review before continuing.

*— Codex · 2026-11-01 UTC*


## User direction and rename warning — 2026-11-01

Direction: “let's do that for now, we'll just keep the rename feature disabled on public instances for now”; followed by “please add a warning to the feature flag for rename.” This supersedes pursuing the broader account-association redesign now. Keep the prior reproduction and proposals as history. The flag already defaults off; instance/environment overrides and historical/imported renames must not be assumed absent. No public-instance configuration was changed or verified remotely.

Immediate authorized change: Add the warning to the existing always-visible Username changes flag description: renamed keys retain approval and can appear under another account's username; turning the flag off does not undo saved names or approvals. This is a separately requested copy change, not completion of Stage 1 or implementation of retirement. Continue reconciliation of the approved retirement plan around the selected disabled-rename boundary; do not treat the warning alone as an authorization check.

*— Codex · 2026-11-01 UTC*


## Planning reconciliation — 2026-11-01

User asked whether Step 2 had been revised; only appended direction existed then. Main Step 2 and Step 3 requirements are now reconciled with the disabled-rename approach. User explicitly approved Step 2 v3/A. Revised Step 3 awaits approval before dependent implementation. Member actor/target must be unrenamed approved keys sharing unchanged bootstrap names with the rename flag off; operators retain their path; replacement counting excludes verified historical/imported/renamed-back keys. Keep all earlier characterization and scope history. No retirement runtime changes.

*— Codex · 2026-11-01 UTC*


## Revised entry and Stage 1 completion — 2026-11-01

Approval: User said “approved step 3” for v3/A. Revised planning commit 3aab4e975 precedes resumed implementation. Whole-cycle 1.00 reaffirmed; original estimates/history preserved, S1 revised whole-stage 0.20 retained; same original baseline/R1/P2 and exposed single rater.

**Stage 1 retrospective stage work: 0.20**

- Changes: Resolved authority via the explicitly approved flag-off/unchanged-bootstrap/no-verified-rename boundary, with independent operator authority and unrenamed replacement protection. [Record/acceptance contract](../../specs/key_retirement_record_v1.md) defines exact bytes, trusted acceptance/replay, retained private transfers and serialization. No broad account-association migration. Protected-path inventory remains the approved plan's auth, sessions, direct/prepared writes, approvals/invitations, operators and messaging consumers; each is verified in its implementing stage.
- Verification: Existing 35 focused gate/baseline checks from 0976ab96a establish current primitives and the rename failure case; source inspection confirms retained approval receipts, private transfer ownership, normal write serialization and signature verification. Contract paths and whitespace checked. Future retirement behavior is not claimed tested before implementation.
- Notes: Stage 1 policy gate resolved by user's revised approval. Existing trusted private transfers are retained rather than assigned invented historical dates. S2–S8 acceptance remains mandatory before enabling the writer. No dependencies, runtime retirement route, production change or measured labor.

*— Codex · 2026-11-01 UTC*

## Stage 2 - Retained signed records and replay

**Original estimate: 0.16**
**Retrospective stage work: 0.16**

- Changes: Added strict bounded signed retirement/acceptance parsing and exact-byte/hash verification; incomplete evidence fails explicitly. Full and incremental derived updates preserve the historical approval graph before marking the retired profile inactive, retaining `was_approved` and retirement provenance. Schema 42 adds bootstrap/verified-name-history and retirement fields. Canonical archive classification and recoverable transaction paths include the new record families. Profile lookups include the new metadata; existing consumers continue to select their response fields.
- Verification: 10 focused checks passed: real-signature retirement/rebuild/incremental replay after the original actor retires; mismatched receipt/missing-signature rejection; recoverable signed transaction failures; approval acceptance; actual isolated `./v3 rebuild` and normal startup from schema 41 before profile lookup. Earlier focused approved-key resolver checks 3/3 passed. Initial replay test supplied the existing updater constructor arguments in the wrong order; corrected the fixture and reran successfully.
- Notes: No public retirement writer yet. Effective access integration is Stage 3 and message history separation Stage 4; do not deploy this intermediate stage. Original stage estimate unchanged; no dependency changes, production verification or measured labor.

*— Codex · 2026-11-01 UTC*

## Stage 3 - Active eligibility enforcement

**Original estimate: 0.17**
**Retrospective stage work: 0.17**

- Changes: Canonical retirement checks reject new sign-in, prepared/direct signed actions, registration, seed/reapproval and invitation use. Existing/forwarded sessions resolve through canonical profile inactivity even when the projection is stale. Current operator privileges are separated from retained historical operator evidence; workflow acceptance and sign-in share retirement's publication lock. Historical signature recovery remains available; public readers and keyless participation retain their existing paths.
- Verification: 11 focused replay/policy/session/direct-identity checks passed, then 15 replay/workflow/access checks passed. Extended real-key replay regression simulates stale active profiles and exercises sign-in/status, direct/prepared posting, registration, seed, both approval roles and invitation refusal. Existing forwarding-chain tests and account-access journeys pass. Whitespace checked. Broader role/flag and browser end-to-end checks remain assigned to Stages 5–8; no public mutation UI yet.
- Notes: Active checks intentionally do not alter historical operator classification or signature verification. Original estimate unchanged; no measured labor or deployment claim.

*— Codex · 2026-11-01 UTC*

## Stage 4 - Retained message history and active destinations

**Original estimate: 0.20**
**Retrospective stage work: 0.20**

- Changes: Original-signature and trusted retained-donor verification include previously approved retired keys; current actors, recipient discovery and recovery destinations remain active-only. Canonical guards cover stale projections. New sends, uploads and acknowledgments serialize with retirement through PublicationLock. Existing membership revision invalidation and bounded verification continuations remain in use; no timestamps invented for retained transfers.
- Verification: 37 existing resolver/mailbox/history checks passed, including 115-key budgets, quiet scheduling, private activity, revocation and concurrent private-store writes. New retained-donor regression passes for confirmed and unread transfers, original-signature key discovery, rejected retired uploader/destination and preserved originals. Real 115-key crypto recovery passes with distinct retired submitting and signing keys in both modern (19 bounded pages, no candidate lookup) and unchanged legacy (16 pages) coordinators. Fixture schema/insert updated for the new fields. Whitespace checked.
- Notes: Production services receive the repository root; isolated in-memory protocol fixtures retain optional construction. Ciphertext already accessible to a key cannot be revoked. Full retirement-writer concurrency and browser journeys follow once Stage 5 exposes acceptance; no private recovery metadata enters canonical records. Original estimate unchanged; no measured labor.

*— Codex · 2026-11-01 UTC*

## Stage 5 - Signed retirement API and retry recovery

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes: Reused existing prepared state, signature verifier, canonical write/publication locks, recoverable signed transaction and approval artifact invalidation. Dedicated policy enforces distinct active actor/target, disabled-rename member boundary, independent operator authority and an unchanged active replacement. Prepare and finalize rebuild under lock; reviewed context and exact bytes are bound. Public no-store status and identical accepted retries distinguish accepted retirement from incomplete refresh.
- Verification: 13 focused checks passed. New real-signature acceptance test covers both cross-account approval settings, both rename settings, wrong signer, changed review, renamed-back exclusion, operator assistance, last-key/reciprocal protection, HTTP status, and injected post-commit refresh failure followed by same-signature recovery. Existing transaction tests cover interrupted writers, Git rejection, staged conflicts and symlinks; approval acceptance and API route inventory pass. Whitespace checked.
- Notes: Canonical transaction/prepare contracts were extended instead of introducing another journal or signing protocol. No retirement UI yet; no new dependencies or deployment. Original estimate unchanged; no measured labor.

*— Codex · 2026-11-01 UTC*

## Stage 6 - Shared retirement journeys and retained status

**Original estimate: 0.16**
**Retrospective stage work: 0.16**

- Changes: Existing profile access partial hosts the permanent review/sign/submit/retry action; existing browser signing owns private-key use. A small action-specific partial is needed because retirement has an immutable target/replacement review and uncertain-outcome retry, unlike the existing approval form. Public eligible-actor hints drive visibility before the control is parsed, independent of server viewer identity. Account, normal/Forte profiles, account history, API status and pending queues distinguish retired keys. Historical authorship remains approved for public presentation without granting current access; public account histories retain their posts and grants.
- Verification: 14 focused profile/history/access/write checks passed. `node tests/browser/key_retirement.mjs` passed the ordinary Account journey and operator replacement-first journey with real signatures, six viewer roles, first-frame visibility, keyboard review, cancellation, a lost accepted response followed by exact retry, retired Account recovery, retained history/pending exclusion, and no-JavaScript reading. Review layout checked in all 16 themes at 390/1280 pixels; phone and desktop Word97 screenshots visually inspected. No new navigation destination; existing icons unchanged. Whitespace checked.
- Notes: Browser-local Remove/Undo is unchanged. No secrets enter retirement requests. Full private-store restore and final integration remain. Original estimate unchanged; no dependency change, physical-device test or production claim.

*— Codex · 2026-11-01 UTC*

## Stage 7 - Canonical and private recovery

**Original estimate: 0.15**
**Revised whole-stage estimate: 0.20**
**Retrospective stage work: 0.20**

- Changes: Administrative archive restore accepts current `repository/` export layout, validates complete retirement bundles before copying, and refuses conflicts. Public content merge explicitly excludes retirement authority alongside approval authority. Ordinary message readers now request historical verification keys separately from active encryption keys; integration exposed that their former shared lookup still rejected retired original signers.
- Verification: 28 focused import/resolver/mailbox checks passed, followed by the extended malformed-archive check. Actual current export → `./v3 import-repository` → database deletion → `./v3 rebuild` preserves exact record/signature/receipt bytes, rejects retired sign-in and retains earlier beneficiaries after the accepting operator retires. Missing-signature archive fails before changing destination HEAD. `node tests/browser/private_message_history_sync_browser.mjs --retirement` passes real-key 31-message recovery, retired donors before unread-transfer retrieval, original signatures, draft preservation/reload, forwarding from restored devices, coordinated private-store restore, sync outage with normal send, and unchanged ciphertext/no private-key logging. Reader/list/crypto protocol checks pass.
- Findings resolved: Initial CLI test found the older root-layout restriction and content-planner missing authority classification. Initial browser run reached retained transfers but marked retired original signatures invalid in the ordinary reader; separate verification lookup fixed it. The restored-auth fixture was corrected to obtain its challenge through the actual session endpoint. Failed attempts remain in local test history; they are not baseline failures.
- Notes: UTC date is now 2026-11-02; preceding Stage 3–6 notes used the local Nov 1 date with a UTC label; commit timestamps are authoritative. Revised stage estimate reflects newly observed integration work within approved scope, recorded at completion rather than inventing an earlier checkpoint. Independent whole-cycle projection revised from retained 1.00 to 1.05 for this discovery; stage values are not summed. No measured labor, production or physical-device claim.

*— Codex · 2026-11-02 UTC*
