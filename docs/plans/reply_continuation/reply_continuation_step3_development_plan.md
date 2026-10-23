> **Feature plan:** [Step 1](./reply_continuation_step1_solution_assessment.md) · [Step 2](./reply_continuation_step2_feature_description.md) · [Step 3](./reply_continuation_step3_development_plan.md) · [Step 4](./reply_continuation_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / `v3/reply_continuation` · [P2 ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2).

## Completion Contract

Standard inline/dedicated signed text/image replies appear before background waits; desktop immediately resumes writing, phone reveals the reply and leaves one-tap re-entry. Consecutive sends retain order, target and identity; per-item failures, uncertain responses and reload recovery preserve content without duplicates or newer-draft interference. Preserve anonymous/no-script, Forte, board and new-thread behavior. Local handoff requires focused browser/application evidence and documented limits; release acceptance additionally requires physical iPhone Safari/Firefox checks. Deployment is separate and F01 stays open until production acceptance. Documentation scope includes feature artifacts, register, UI audit notes and cycle ledger; zero dependency additions or read-model schema changes proposed.

## Key Risks

- **High risk:** lost responses/concurrent sends can duplicate posts or overwrite drafts. Prove stable submission identity and durable recovery before extending the existing background flow; never regenerate an uncertain accepted operation blindly.
- **High risk:** attachment capture/storage and signing identity changes can lose content or sign as the wrong author. Persist captured bytes/identity before discarding the only copy; expose recoverable storage/identity failures.
- **High risk:** phone keyboard/scroll transitions may hide the reply or re-entry. Validate on device as early as the phone slice is runnable; distinguish emulator checks from pending physical acceptance.

## Stage 1

- Goal: one recoverable text reply completes from the standard inline form without navigation.
- Dependencies: Step 3 approval, feature branch and planning commit before implementation.
- Expected changes: capture a stable operation independent of the live form; persist recovery state before clearing; show a safe pending card immediately and confirm it in place.
- Verification approach: hold identity/preparation requests; assert next-paint card/ready editor; inject storage/prepare failure and prove captured text plus newer draft survive.
- Risks or open questions: live-form reads can corrupt snapshots; isolate captured fields/signing identity and verify before dependent stages.
- Canonical components/API contracts touched: browser signing, existing pending cards, draft/Outbox storage, prepare/finalize contracts; no new public signature planned.

## Stage 2

- Goal: consecutive text replies recover independently, including after reload.
- Dependencies: Stage 1 snapshot/persistence checks pass.
- Expected changes: operation-scoped delivery/reconciliation and Retry; preserve prepared identity before finalization, restore unresolved items and suppress duplicate accepted cards; retain local send order.
- Verification approach: two intentional identical sends, reversed completions, lost finalize response, expired preparation, retry/reload and two-tab recovery; assert one post per operation and unchanged active draft.
- Risks or open questions: prove existing finalize idempotency/reconciliation before retrying uncertain writes; if insufficient, stop for scoped contract review rather than inventing a fresh post identity.
- Canonical components/API contracts touched: signing recovery, Outbox storage/sender, prepared-post finalization and canonical post lookup; compatibility tests guard existing clients.

## Stage 3

- Goal: image replies support the same immediate continuation and durable recovery.
- Dependencies: Stage 2 operation/retry invariants pass.
- Expected changes: snapshot prepared attachment bytes/description independently, retain them for retry/reload and release only that operation's upload state; do not lock the next draft's attachment control.
- Verification approach: delayed uploads, two different images, lost response/reload, quota failure and identity change; verify correct signed content/bytes and newer attachment isolation.
- Risks or open questions: durable blob capture is asynchronous; paint pending feedback immediately, retain the original recoverable copy until storage succeeds, and keep unsaved work visibly actionable.
- Canonical components/API contracts touched: image upload/preparation, multipart finalization and existing browser storage; preserve anonymous-image refusal.

## Stage 4

- Goal: dedicated reply entry has its own continuing stack and desktop writing remains stable on both routes.
- Dependencies: text/image recovery stages pass.
- Expected changes: reuse canonical reply-card presentation and captured parent context in the dedicated route; preserve focus, selection, local order and visible new replies through background updates.
- Verification approach: real sends on both routes with long content, nested parents, keyboard submission and delayed completion; measure focus/scroll/overflow and confirm canonical links.
- Risks or open questions: stack placement can displace the editor; validate geometry before accepting the desktop interaction, without adding another navigation destination.
- Canonical components/API contracts touched: reply form, compose-reply page, inline lifecycle, card rendering and compose styles.

## Stage 5

- Goal: phone Send reveals the reply and provides one-tap re-entry.
- Dependencies: Stage 4 both-route continuation works.
- Expected changes: integrate keyboard dismissal, reading position and compact re-entry with the existing phone composing panel; preserve errors, action accessibility and ordinary-flow fallback.
- Verification approach: both routes, repeated send/tap cycles, long replies, rotation and restricted viewport; replay actual iPhone Safari/Firefox keyboard behavior when device access is available and record browser/device evidence or pending manual checklist.
- Risks or open questions: simulated viewports cannot establish native keyboard behavior; avoid claiming device acceptance and retain actionable per-reply Retry/re-entry guidance for user testing.
- Canonical components/API contracts touched: shared phone reply viewport/layout, inline disclosure and compose styles; desktop/Forte remain regression boundaries.

## Stage 6

- Goal: verify end-to-end delivery/recovery and document the handoff.
- Dependencies: prior stages pass; unresolved material failures corrected before closeout.
- Expected changes: update focused assertions formerly requiring navigation, implementation summary, F01 register, UI audit and ledger; record dependency delta and acceptance limits.
- Verification approach: signed text/image normal/failure/uncertain/reload scenarios on both routes; anonymous/no-script, board/new-thread, Forte and Outbox regressions; Chromium/Firefox/WebKit and phone/desktop theme checks including Word97/Chicago/Midnight, loaded long-content overflow and accessibility. Run affected PHP/JS checks; visually inspect representative rendered states.
- Risks or open questions: shared behavior may regress outside enhanced replies; use existing consumer tests and isolate/fix regressions. Physical-device or deployment omissions remain explicit, not successful acceptance.
- Canonical components/API contracts touched: existing reply/image/viewport/Outbox/browser-signing tests and affected application checks; approved documentation only for closeout bookkeeping.

## Execution and study checkpoint

Step 2 explicitly approved; no new requests or scope changes. Six bounded stages, each targeted at about one hour; split an oversized stage before starting it and revisit scope/approvals if the outcome changes. Planning remains uncommitted; after **Approved Step 3**, organize feature plans and make the planning-only commit before summary/application changes, then commit each completed stage with its summary update. Accumulated cycle-ledger/register/audit changes belong to authorized stage/closeout documentation, not the planning-only commit.

2026-10-23T14:19:17Z: **0.95 total reaffirmed**, scope v1/R1/P2, baseline `3776264ed2ae9345294d3e93c25221037f6cd6e3`, Codex single exposed rater. Existing image prepared-record reuse and Outbox contracts inform staging; concurrent identity, durable recovery and actual device evidence remain material validation obligations. R1 reference identities carried forward from verified Steps 1–2; no substitution or new rating anchor. Prior snapshots preserved; no implementation, measurement or accepted outcome. Step 3 subsequently explicitly approved; [Step 4](./reply_continuation_step4_implementation_summary.md) records execution.
