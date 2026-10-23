> **Feature plan:** [Step 1](./reply_continuation_step1_solution_assessment.md) · [Step 2](./reply_continuation_step2_feature_description.md) · [Step 3](./reply_continuation_step3_development_plan.md) · [Step 4](./reply_continuation_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / `v3/reply_continuation` · [P2 ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2).

## Problem and user stories

Reply navigation interrupts consecutive writing; waiting for preparation before showing a pending reply also delays feedback.

- As a desktop writer, I want Send to display my reply and free the focused editor immediately so that I can keep writing while publication finishes.
- As a phone writer, I want to see my sent reply with the keyboard dismissed and start another with one tap so that I can alternate reading and writing in limited space.
- As a writer, I want each failed reply to retain its content and offer Retry so that recovery never overwrites my next draft or duplicates a post.

## Core requirements

- After local validation accepts Send, show a pending reply in the next paint and reset only the captured submission's editor content before any identity/preparation/signing/upload/network wait. Desktop keeps focus and permits another send while earlier replies remain pending. Invalid input stays editable with actionable feedback. Snapshot each reply's body, attachment, identity mode, thread and parent; preserve that target for the next reply unless the user explicitly chooses another.
- Phone Send dismisses the keyboard, reveals the sent reply and leaves a compact, keyboard-accessible reply field visible; one tap resumes writing. Desktop shows the pending reply without a reload or focus/scroll change that interrupts writing. Dedicated reply entry provides the same sent-reply stack and continuation in its existing context. No new destination or navigation control is required.
- Distinguish pending, confirmed and failed replies accessibly with minimal text. Confirm each item in place with its canonical link; completion order must not reorder the local send sequence, duplicate cards, steal focus or move the newer draft. Multiple intentional sends, including identical text, remain distinct; repeated activation of a captured send must not create duplicates.
- Keep failure and Retry beside the affected reply, retaining its captured content/attachment independently of the active draft. Lost responses require reconciliation before resending; never claim success without confirmation. Navigation/reload must retain recoverable pending/failed work and offer an explicit way to reconcile/retry it without automatic duplicate creation. Unavailable recovery storage must expose an actionable warning before clearing the only recoverable copy.
- Enhanced continuation covers standard JavaScript signed text and supported image replies. Existing anonymous posting and no-script submission keep their current navigation and validation/recovery behavior; anonymous-image restrictions remain visible. Preserve author identity, attachment validation, draft/Outbox behavior and safe content rendering. Forte, new threads and private messages retain their existing flows.

## Delivery scope and completion boundary

**Application change:** standard thread inline and dedicated reply entry, their shared signing/submission, draft/recovery and reply-card presentation, focused tests and feature documents; update the F01 register, applicable UI audit notes and cycle ledger. No new external dependency, read-model schema change or deployment is proposed. Normal flow starts with writing a reply and ends with multiple confirmed replies plus an untouched newer draft; release requires per-reply failure/uncertain-response and navigation recovery, supported desktop acceptance and physical phone acceptance. Local automated handoff and production/F01 closure must be reported separately when device or deployment evidence is pending.

## Risks and earliest validation

- **Lost/duplicate posts:** overlapping operations and uncertain responses can corrupt recovery; validate independent snapshots, reconciliation and reload recovery before visual polish, preserving newer drafts throughout.
- **Phone visibility:** keyboard animation and small viewports can hide the sent reply or re-entry field; validate real iPhone Safari/Firefox early, including long replies, rotation and repeated send/tap cycles. Automated desktop-engine checks supplement device evidence.
- **Shared consumers:** identity, images and existing navigation may regress; exercise signed text/image recovery and anonymous/no-script, Forte and board regressions before release. Capture only the submitted attachment so subsequent writes remain independent.

## Shared component inventory

Extend the canonical reply form used by thread inline, dedicated and Forte surfaces, with enhanced behavior limited to standard replies. Reuse existing inline lifecycle, phone layout/actions, reply cards, signing/prepare/finalize APIs, canonical post links, image transport and draft/Outbox recovery. Existing thread/reply read views remain canonical confirmation sources; pending/failed items belong to the submitting browser and must not masquerade as public records. Board/new-thread composers and Forte are regression consumers, not redesign targets.

## Flow and success criteria

1. Write and Send; with network requests held, the next paint shows the pending reply and a ready desktop editor or phone reading view with one-tap re-entry.
2. Write/send again before completion; complete requests in reverse order, fail one and retry it. Assert stable send order, exactly one canonical post per send, correct parent/identity/attachment and unchanged newer draft/focus.
3. Lose a response, navigate/reload and recover; verify retained content, actionable reconciliation/retry and no duplicates. Check local validation, anonymous/no-script compatibility, long content, overflow and affected themes at phone/desktop sizes; physical phone checks verify actual keyboard dismissal and one-tap reopening. Record evidence and remaining limits in the implementation summary.

## Approval and study checkpoint

Step 1 Option A and Step 2 explicitly approved; no changed-scope requests. Step 3 was subsequently explicitly approved; see [implementation evidence](./reply_continuation_step4_implementation_summary.md). Scope v1 clarified: enhanced signed standard replies, compatible existing anonymous/no-script paths. 2026-10-23T10:22:45Z, R1/P2, baseline `3776264ed2ae9345294d3e93c25221037f6cd6e3`, Codex single exposed rater: **0.95 total reaffirmed**, including prior investigation, planning, concurrent publication/recovery, device/theme verification and documentation. Exact R1 report/diff and frozen feedback hashes reverified; prior snapshots retained. No implementation, measured effort or accepted outcome; planning and ledger remain uncommitted.
