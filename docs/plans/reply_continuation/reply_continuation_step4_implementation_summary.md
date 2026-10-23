> **Feature plan:** [Step 1](./reply_continuation_step1_solution_assessment.md) · [Step 2](./reply_continuation_step2_feature_description.md) · [Step 3](./reply_continuation_step3_development_plan.md) · [Step 4](./reply_continuation_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / `v3/reply_continuation` · [Ledger](../effort_estimation_trial.md).

## Execution state

- Step 3 approved; feature branch `feat/reply-continuation`; planning commit `22ea20c19` precedes implementation. Six stages pending. No additional requests.
- Step 4 entry, 2026-10-23: scope v1/R1/P2, baseline `3776264ed2ae9345294d3e93c25221037f6cd6e3`, Codex single exposed rater; **0.95 total reaffirmed**. Reference verification and prior exposure inherited from Steps 1–3. No measurement, independent rating or accepted outcome.
- Physical iPhone Safari/Firefox acceptance and deployment remain pending; no new dependencies or schema changes authorized/proposed.

## Stage 1 - Immediate captured text submission

- Changes: standard inline text replies save a captured operation before clearing, paint a pending card before identity/preparation waits and confirm in place; failure/Retry belong to that card. A new shared asset uses existing signing, card and draft helpers, leaving legacy consumers unchanged.
- Verification: `node tests/browser/reply_continuation.mjs` passes real isolated signed posting with preparation held, immediate card/empty focused editor, no navigation, newer draft isolation, prepare failure/Retry and injected storage failure retaining original text. JS syntax, PHP renderer lint and diff checks pass.
- Notes: Stage 1 only; reload/uncertain-response and image/dedicated/phone work remain in subsequent stages. Planning commit `22ea20c19` confirmed before summary/application changes.

## Stage 2 - Concurrent and reload-safe text recovery

- Changes: independently retained operations, send-order display, per-operation browser locks, reload restoration without automatic submission and canonical-post reconciliation before retry. Exact prepared targets persist before finalization; a definitive server expiry plus confirmed absence permits explicit renewal. Existing pageshow cleanup now preserves recoverable operation cards. Accepted cards yield to an already rendered canonical card.
- Verification: expanded `node tests/browser/reply_continuation.mjs` passes overlapping identical intentional sends with reverse completion, lost accepted response/reload with zero repeat finalization, retained newer draft, two-tab Retry producing exactly one canonical post, storage failure and Stage 1 checks. JS syntax/diff checks pass. Server source confirms write-locked finalization and definitive expiry rejection; no schema/API change.
- Notes: Stage 1 commit `de8ae2bcb`. First reload test exposed legacy pageshow removal of restored cards; fixed and rerun. Two-tab test uses native button activation directly to exercise simultaneous intent without Playwright waiting for a button hidden by the winning completion. Browser locks are required for safe cross-tab delivery; unsupported environments retain content with recovery feedback.

## Stage 3 - Independent image submission and recovery

- Changes: captured attachments detach from the active form immediately, retain raw/prepared bytes in browser IndexedDB and reuse the existing image preparation, signed-reference and multipart finalization contracts. Pending cards show the captured image; a memory copy and unload warning protect against failed durable storage. Image-reference construction is shared with legacy image submission.
- Verification: `node tests/browser/reply_continuation.mjs` passes two overlapping real signed image replies/descriptions, immediate editor/attachment reset, newer draft isolation, lost accepted image response plus reload/reconciliation and exactly one canonical image post. Injected IndexedDB failure exposes keep-page-open recovery and succeeds on Retry after storage restoration. Stage 1/2 text cases remain passing; changed JS syntax and diff checks pass.
- Notes: Stage 2 commit `91f1650fd`. Raw bytes remain recoverable before preparation; exact prepared bytes are persisted before finalization. No external dependency or server schema/API change. Both-route phone/visual checks follow in Stages 4–6.

## Stage 4 - Dedicated and stable desktop continuation

- Changes: both standard reply routes use a bounded, scrollable local reply stack built with the existing safe pending-card component. Dedicated entry preserves parent context and target. Showing the stack compensates page geometry to keep the desktop editor at its writing position; background completion cannot grow the stack into the editor. Long text and images stay within the container.
- Verification: `node tests/browser/reply_continuation.mjs` passes dedicated consecutive real submissions, unchanged URL/parent, empty focused editor and measured desktop editor position within 3px, plus long-content page-overflow and all Stage 1–3 scenarios. JS syntax and diff checks pass.
- Notes: Stage 3 commit `62f54f3dd`. Dedicated restoration correctly includes the browser's earlier accepted replies to the same parent; tests compare against that baseline rather than assuming an empty stack. Physical phone interaction follows next.
