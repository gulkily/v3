> **Feature plan:** [Step 1](./reply_composer_focus_layout_step1_solution_assessment.md) · [Step 2](./reply_composer_focus_layout_step2_feature_description.md) · [Step 3](./reply_composer_focus_layout_step3_development_plan.md) · [Step 4](./reply_composer_focus_layout_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract

From ordinary thread Reply or dedicated reply entry, submit exactly one reply or retain the draft with visible correction/retry. Preserve one editor, genuine collapse/expansion, stable first paint, draft/selection and all submission/secondary actions. Reserve separate phone editor, feedback and Post space, with usable fallback when space is inadequate. Preserve desktop, accessibility/keyboard, no-script, board and Forte. Automated checks permit handoff; actual iPhone Safari/Firefox acceptance remains pending user testing, without an upfront device gate. No schema change, merge, push or deployment.

## Key Risks

- **High risk:** Keyboard geometry can obscure text/errors. Stage 2 explicitly tests separation and short viewports; inadequate space requires ordinary-flow fallback before acceptance.
- **High risk:** Focus/layout changes can lose collapse behavior, selection or submission state. Stage 1 checks transitions/first paint; Stage 3 checks failure/retry and duplicate activation.
- Shared consumers may regress. Limit new activation to standard replies and check board/desktop/Forte/no-script. Desktop engines cannot establish physical keyboard behavior.

## Stage 1 — One editor and preserved inline interaction

- Goal: Remove the focus handoff while retaining compact/expanded behavior; ≤1 hour.
- Dependencies: Approved Step 3; new branch, organized plans/index and planning commit before implementation.
- Expected changes: Reuse the reply textarea, remove placeholder/disclosure arrow, preserve Reply links/fallback URLs, and extend shared collapse lifecycle and initial styling. Drafts/attachments/errors stay expanded.
- Verification approach: Assert editor identity, direct focus without competing scrolling, actual height expansion/empty collapse, draft/selection and menu access. Hold scripts to compare first-paint/initialized geometry; check restored drafts, no-script entry and board regression.
- Risks or open questions: Initialization/restoration can shift layout; validate both early and resolve state-transition failures before Stage 2.
- Canonical components/API contracts touched: Thread view, `reply_form.php`, shared inline lifecycle/styles, Reply links and signing's inline recognition; reuse submission contracts.

## Stage 2 — Separate phone writing, feedback and actions

- Goal: Keep Post reachable without overlap in both reply flows; ≤1 hour.
- Dependencies: Stage 1 verified, summarized and committed.
- Expected changes: Replace independent floating placement with reserved composing space; retain the original form/controls, bound editor/menu space and restore ordinary flow on exit or fallback. Account for validation, status and image recovery.
- Verification approach: Replay the overlap at multiple page positions; assert editor/error/action rectangles do not intersect. Exercise short/tall viewports, scroll, rotation, zoom/fallback, reopening, long drafts/selection and menus in Chromium/WebKit/Firefox across Light/Word97/Chicago/Midnight.
- Risks or open questions: Landscape/zoom may leave inadequate space; validate early and require usable fallback preserving draft/selection/focus. Unresolved overlap blocks acceptance.
- Canonical components/API contracts touched: Shared viewport lifecycle/styles, thread/dedicated reply wiring, canonical feedback/normalization/image/menu surfaces; no new endpoint.

## Stage 3 — Recovery, regressions and device-test handoff

- Goal: Prove real submission/recovery and prepare user testing; ≤1 hour.
- Dependencies: Stage 2 verified, summarized and committed.
- Expected changes: Extend isolated real-route checks, correct in-scope failures, update implementation/feedback/UI audit notes and provide an iPhone replay checklist.
- Verification approach: Both entries: first focus, validation correction, network failure, pending repeated activation, retry and one persisted reply after reload. Cover anonymous/attachments/Outbox, keyboard/menu focus, overflow and initial/empty/expanded/pending/error/success states. Run affected inline-link, viewport, action-layout, layout-stability, disclosure, signing and image checks plus syntax/diff checks; confirm desktop/no-script/board/Forte.
- Risks or open questions: Physical keyboard/chrome remain unverified. Hand off Safari/Firefox first-tap, long-draft/selection, scroll, rotation, zoom, reopening and recovery tests; record device/browser versions and results as pending user acceptance.
- Canonical components/API contracts touched: Delivered reply UI and existing signing/submission/attachment contracts, implementation summary, feedback and UI audit records.

## Execution state

Step 2 approved; board lessons retained without new scope. Keep planning/debug artifacts uncommitted until Approved Step 3 and the planning-commit gate. Create Step 4 afterward. Each stage requires checks, summary update and its own commit before the next stage; preserve prior-cycle records.
