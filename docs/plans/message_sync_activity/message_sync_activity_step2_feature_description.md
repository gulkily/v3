> **Feature plan:** [Step 1](./message_sync_activity_step1_solution_assessment.md) · [Step 2](./message_sync_activity_step2_feature_description.md) · [Step 3](./message_sync_activity_step3_development_plan.md) · [Step 4](./message_sync_activity_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/message_sync_activity — [ledger](../effort_estimation_trial.md#cycle-v3message_sync_activity), standing P2 enrollment
Extended estimates: enabled
Status: Revised Step 2 approved; revised Step 3 proposed; completed Stages 1–2 retained; retirement and cap remain separate

**Cycle estimate: 0.95**

## Problem and user stories

The reported 115-key account eventually recovered its messages but still produces excessive traffic. Fingerprint-order scans delay active devices, while repeated original processing and candidate lookups waste requests and bytes.

- As a member returning on a new approved key, I want both sent and received messages recovered during ordinary visible browsing, without repeatedly reloading either device.
- As a member whose messages are readable, I want background sync to settle quietly.
- As an operator, I want enforced resource limits even when an account has many keys or a client ignores browser limits.

## Core requirements

- Rank same-account destinations by server-observed recent authenticated foreground activity, including sign-in and explicit visible-page entry/return. Activity is private, identity-bound and coalesced; background sync never promotes itself. A newly active device takes priority at the next bounded scheduling turn. Reserve at least one in four destination selections for fair progress among remaining eligible keys; unknown activity has a stable fallback, and reordering must not lose progress.
- Recover both message directions through ordinary Messages/other eligible page entry. Continue known unfinished work in bounded visible turns, yielding between turns; stop at completion or when no new recoverable work remains. Preserve progress across navigation, interruption and reload. An unavailable donor must not cause continuous retries, fabricated completion or permanent suppression.
- Deduplicate candidate lookup, original verification/key extraction and receipts across repeated target work within a bounded run. Reuse valid completion evidence across runs. Distinguish a request to help another key from missing access on this key. New messages, keys, valid donor contributions and membership changes invalidate relevant settled/unavailable state; failed or empty lookup results must not hide newly available recovery. Explicit per-message retry remains available.
- A genuinely idle eligible refresh makes exactly one small combined work check, without transfer lookup/upload, acknowledgment or originals/key lists. No-history accounts make zero dedicated sync requests. Settled state must not trigger a background continuation timer; coalesce competing triggers/tabs and apply server limits across clients. Unfinished bounded discovery is reported honestly, never labeled idle.
- Leave sending unchanged: no new rejection, mandatory reauthentication, outer-signature requirement or signing-key/session-key match check. During recovery, try the recorded submitting key first when it is eligible; if original verification fails or that key is unavailable, continue through other currently approved sender keys in bounded batches. Treat the recorded identity as a hint, not proof of the encrypted signer. A failed batch is unfinished work until the eligible candidates are exhausted; only successful original verification permits an access receipt.
- Preserve browser-only private keys/plaintext, original signature verification, historical ciphertext, existing client/read/retry APIs and messaging disable behavior. Never rank cross-account sender work by recipient activity or reveal recipient read/recovery state. Preserve old-client behavior within the same resource ceilings, with explicit retry responses instead of silent truncation or dropped work.

## Proposed hard limits and success criteria

| Resource | Acceptance ceiling |
| --- | --- |
| Idle work response | 8 KiB uncompressed JSON; no originals or public-key arrays |
| Work discovery per request | 160 message/target decisions and 64 public-key records loaded; no hidden full-account enumeration outside that budget |
| Non-idle sync response | 4 MiB uncompressed JSON, with bounded continuation for larger valid data |
| Browser background turn | 16 HTTP attempts including retries, 10 distinct originals processed, at most 3 concurrent requests; 10-second work window and 15-second cancellation deadline |
| Continued work | At least 1 second yielded between turns; only while visible and useful progress/unfinished discovery remains |
| Server admission | At most 16 history-sync requests per authenticated key and 64 per account per rolling 10 seconds, including legacy clients; excess returns 429 with Retry-After |

These are proposed upper bounds, not targets for routine traffic. Budgets include failed attempts, serialization and candidate pagination; browser deadlines stop admitting work and cancel pending network requests, without claiming to preempt an already-running cryptographic operation. Preserve already accepted large originals and historical public keys through bounded retrieval/verification rather than silently omitting them. Rate limits cover history endpoints, including foreground candidate retrieval; normal message send/list/read APIs remain independent, and temporary recovery throttling retains content/drafts and supports retry. No claimed absolute CPU-time guarantee follows from record limits: validate query, memory and crypto amplification at the measured 115-key scale before implementation choices are accepted.

## Scope, shared components and concepts

Work type: application change with focused service/browser tests, affected API/configuration/operations documentation, concept inventory, diagnostic and cycle/debug/study records. The earlier proposed legacy upgrade-required error is withdrawn from this recommendation; it was not approved and its necessity was not demonstrated. Preserve existing client compatibility as a requirement, with feasibility verified before dependent implementation; do not claim that paged verification alone has already resolved that gate. No key retirement, approval cap, automatic deletion, new dependencies or public activity feed. Existing over-limit accounts remain supported; this cycle is independent of both queued assessments.

Extend the canonical history work/transfers/acknowledgment service and browser coordinator; reuse unread eligibility, authentication/approved-key resolution, messaging availability and per-message recovery in conversation cards and list previews. Use existing foreground navigation/authentication lifecycle as activity evidence rather than adding a new UI or a dedicated activity heartbeat. Backend activity persistence, admission accounting and bounded key retrieval remain Step 3 design choices; any read-model changes must meet CONTRIBUTING's version/rebuild contract.

Concepts touched: [History recovery/sender assistance](../../concepts/private_message_history.md) change scheduling and processing; unread meaning and no-history eligibility remain unchanged. Add a scoped private authenticated-key-activity description during implementation; record resource limits alongside the existing history API contract.

## Risks and early validation

- The submitting key can differ from the encrypted signer through the existing API; this is a constructed compatibility case, not an observed cause of the user’s messages or traffic. Test real signed mismatched-key history and missing/unavailable recorded keys before using the fast path. Keep verification continuation separate from missing decryption access so failure with one verification key does not trigger repeated transfer lookups.

- Activity priority can starve older keys or expose recipient behavior. Before dependent client work, test continuous recent activity, missing timestamps and cross-account response invariance; use the fairness reservation and same-account privacy boundary.
- Cached failures/completion can strand sent history after a donor arrives or membership changes. Validate invalidation, missing donors, interruption and real signature verification before enabling suppression; retain explicit retry and durable progress.
- Limits can merely move expensive work into hidden queries, oversized key lists or a retry storm. First measure the reported request breakdown and worst-case payloads; enforce budgets before expensive work and test direct/legacy clients, 429 handling and concurrent tabs. An incompatible ceiling requires revising this artifact before dependent implementation, not silently relaxing it.

## Normal flow and completion boundary

1. The new approved device visits Messages; foreground activity makes it a recent same-account destination. The old device visits or returns to a visible eligible page and automatically contributes in bounded turns.
2. The new device retrieves and verifies both incoming and outgoing history. Verification tries the recorded key first and continues through bounded alternatives when needed, without asking the sender to resend or sign in again. Interrupted visits resume through normal return; unread failure uses its existing retry and message recovery retains per-message retry.
3. Once useful work settles, subsequent ordinary refreshes produce one small idle check, not the reported request flood.

Known environment: replay the user's local Messages flow with at least 115 approved keys, mixed sent/received originals and partial donors, then measure post-recovery traffic over repeated visible returns and concurrent tabs. Verify active-key priority and fairness separately; reading on the new key alone does not prove every historical key has caught up. Use real signed content and network traces without test-only repeated refresh calls. Compare endpoint counts/bytes before and after; record incomplete donors rather than treating repeated empty lookups as progress. Validate both supported message crypto paths and phone/desktop recovery without adding UI shifts. Local fixture success does not substitute for the user's deployed environment; replay there after authorized deployment, with unresolved production evidence stated explicitly. No merge/push/deployment authorization is implied.

## Study checkpoint and approval

Step 2 handoff: 2026-10-30T11:47:06Z; scope v1/A, baseline 4b14e622f3cca6cb8f041145fd1df954bacc74e4, R1/P2, Codex single exposed rater; prior frozen-source hash verification retained. Projected whole-cycle 0.95 revises preserved Step 1 0.85 for clearer server-enforced admission, bounded key retrieval and invalidation obligations within the selected scope. Includes planning, implementation, recovery verification and documentation; not measured labor or an accepted outcome. No runtime tests performed for this planning step. Await Approved Step 2; documents remain uncommitted.

*— Codex · 2026-10-30 UTC*

## Revision checkpoint — recovery without send restrictions

Date: 2026-10-30 UTC
Authorization: The user explicitly rejected blocking send attempts, agreed to recovery-side fallback, and requested proceeding with the current sync cycle. This authorizes this planning revision; it does not approve the withdrawn legacy compatibility restriction or waive staged approvals.
Scope: v2/A, same cycle and original baseline 4b14e622f3cca6cb8f041145fd1df954bacc74e4. No new send checks, key retirement, cap or dependency. All numeric ceilings and existing privacy, fairness, idle and recovery requirements remain unchanged.
Estimate: Whole-cycle 0.95 reaffirmed; earlier 0.85/0.95 snapshots and original stage estimates retained. R1/P2, extended estimates enabled, prior frozen-reference verification inherited, Codex single exposed rater. The revision makes recovery fallback and non-blocking sends explicit; legacy bounded progress remains the main uncertainty. No measured labor or retrospective completion score.
Evidence: Read the browser send/recovery flow and mailbox acceptance code. Prior regression and crypto evidence retained; no new runtime change or runtime test in this documentation revision. Completed Stages 1–2 remain committed, Stage 3 incomplete, Stages 4–8 not started.
Next: Await Approved Step 2 for this revision, then revise Step 3 around bounded verification continuation and prove the legacy compatibility path before dependent implementation. Original Step 3 is retained as historical approved planning, not approval for an unreviewed compatibility change.

*— Codex · 2026-10-30 UTC*

Approval update: User approved revised Step 2 on 2026-10-30 UTC; proceed to revised Step 3 planning. Earlier checkpoint wording remains historical.
