> **Feature plan:** [Step 1](./reply_continuation_step1_solution_assessment.md) · [Step 2](./reply_continuation_step2_feature_description.md) · [Step 3](./reply_continuation_step3_development_plan.md) · [Step 4](./reply_continuation_step4_implementation_summary.md)

Debug mode: enabled (project marker).

Effort study: railspike-effort / `v3/reply_continuation` · [P2 ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2).

## Original Query

“Let's look into PF-20261021-F01, please.”

“So on desktop, I want a smooth transition, chat-like, from writing and submitting a reply to writing a new reply. There should be no delay from when I hit send on the first reply to where it appears in the stack of replies and I'm ready to write the next one, with all the other stuff happening in the background. On phone, I'm not sure, and we should talk about it, due to the limited screen space.”

“Probably consecutively, but I want to see the previous one. I would be open to replying being just one tap away.”

“This sounds good to me. Let's do it.”

## Problem

Successful replies replace the document, interrupting consecutive writing; existing optimistic display starts only after preparation and does not provide this continuation ([F01 investigation](../production_feedback_register.md#pf-20261021-f01)).

## Option A — Immediate continuation, phone returns to reading

Send immediately places a pending reply in the existing stack and frees the composer. Desktop retains writing focus; phone dismisses the keyboard, reveals the sent reply and keeps a compact reply field visible, one tap from writing again. Background completion updates the same reply.

- Pros: matches the agreed desktop and phone flows; successive sends need not wait for earlier ones.
- Cons: phone keyboard movement needs device testing; overlapping sends require independent recovery and stable reply ordering.

## Option B — Immediate continuation, retain phone keyboard

Use the same immediate pending replies and background completion, keeping the phone editor focused and showing the latest sent reply above it.

- Pros: fastest consecutive writing on both screen sizes.
- Cons: less phone reading space; conflicts with the selected return-to-reading behavior.

## Recommendation and boundary

Choose **A**, one releasable standard-reply slice covering inline and dedicated reply entry. Preserve thread/parent targeting across successive sends; do not silently change the next reply's recipient. Make the accepted reply visible without desktop scrolling that disrupts the next draft. Pending replies must be distinguishable from confirmed posts. Each failure retains its own content and Retry beside that reply, without replacing the newer draft; retries and uncertain responses must not duplicate posts. Reconcile background completions in send order without reloading the writing session. Preserve pending work through ordinary navigation/reload with an explicit recovery path. Attachments, anonymous submission and no-script fallback need explicit compatibility requirements in Step 2; no Forte redesign, new thread behavior, new dependency or schema change is proposed.

Verify consecutive sends, delayed/out-of-order completion, failures/retry, uncertain responses and newer-draft isolation on desktop and phone; verify real phone keyboard dismissal/re-entry and visibility. Reuse existing signing, pending-card, draft and canonical-post machinery where suitable. The earlier [reply layout cycle](../reply_composer_focus_layout/reply_composer_focus_layout_step4_implementation_summary.md) remains separate; its physical iPhone acceptance is not inherited as complete.

## Request disposition and approval

Desktop immediate continuation and phone sent-reply visibility/one-tap re-entry are incorporated as scope v1. User subsequently explicitly approved Step 1 Option A; [Step 2](./reply_continuation_step2_feature_description.md) is prepared for review. Steps 2–3 were subsequently explicitly approved; implementation evidence is in [Step 4](./reply_continuation_step4_implementation_summary.md).

## Effort snapshots

Intake and Step 1 handoff, 2026-10-23T10:18:13Z: **0.95 projected total**, scope v1, R1/P2, Codex single exposed rater, baseline `3776264ed2ae9345294d3e93c25221037f6cd6e3`. Intake collected after prior investigation of submission code/tests and related plans; no pristine prospective estimate is claimed. Total includes planning, concurrent background submission/reconciliation, per-reply recovery, desktop/phone verification and documentation; near the provisional 1.0 recovery reference and broader than 0.60 composer decluttering. Step 1 reaffirms 0.95. Above-one discoveries should prompt a split discussion without dropping recovery. R1 report/diff hashes verified against ledger; exact frozen feedback hash verified from `4f6cfe2b436fae8d939b1349fe724a3879ee0a0e`. Inherited F01 0.15 remains historical. No independent rating, measured effort or accepted outcome; planning and ledger remain uncommitted.
