> **Feature plan:** [Step 1](./composer_anchor_exit_step1_solution_assessment.md) · [Step 2](./composer_anchor_exit_step2_feature_description.md) · [Step 3](./composer_anchor_exit_step3_development_plan.md) · [Step 4](./composer_anchor_exit_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/composer_anchor_exit · [Host ledger](./effort_estimation_trial.md) · Standing enrollment P2

## Original Query

The compose and reply textareas still don't maintain their top coordinates within their container when expanding. Below the textarea, there is similarly an extra little bit of space. Also, the reply form seems impossible to exit out of, while the compose thread one is, so ... let's write a Step 1 for all this.

The new thread page's compose form is under-polished also, there's a lot of extra space above the create thread button, and below the textarea, and there should not be a "body" label NEXT to the body textarea, and the textarea should have a better intro text like the others.

## Understood Intent

Polish the board's inline thread composer, standard inline reply and dedicated New Thread page as one usable writing flow; include the dedicated reply page where shared phone behavior applies. Preserve textarea position **relative to its container**, genuine expansion/collapse, keyboard-reachable Post, draft/selection, attachments, warnings and retry. Exiting editing must not require posting, clearing a draft or losing attachment/error state. This is a new cycle after [reply composer focus/layout](./reply_composer_focus_layout/reply_composer_focus_layout_step4_implementation_summary.md), not approval to implement or reuse its merged branch.

## Problem and evidence

Composer expansion changes spacing and, for phone replies, takes over the visible viewport without an explicit exit; the dedicated New Thread form retains redundant labeling and empty space. Source inspection confirms changing collapsed/expanded insets, focus-dependent panel restoration, a visible Body label/generic Body prompt, and deliberately reserved hidden upload space. These explain plausible causes; exact excess gaps and physical iPhone behavior have not been reproduced in this cycle.

## Option A — Consistent inline expansion and page access

Keep composers in the page with consistent editor insets, grow downward, and leave page content reachable for defocusing; retain keyboard-aware action space without taking over the whole reply area. Remove empty New Thread spacing and its visible Body label, keeping an accessible name and a useful prompt such as “What do you want to discuss?”.

- Pros: Thread/reply entry and exit behave consistently; preserves the user's place and reduces special UI.
- Cons: Keyboard reachability still needs explicit validation; ordinary flow alone cannot guarantee Post stays visible.

## Option B — Stable inline form plus an explicit phone editing mode

Apply the same spacing/prompt cleanup, but retain a bounded phone reply panel with a visible, accessible Done/exit action that restores page position and focus without discarding the draft.

- Pros: Keeps the existing separation of editor/actions; makes escape reliable, including with a draft or open menu.
- Cons: Adds a control and a distinct phone mode; requires reliable entry/exit, scroll restoration and keyboard handling.

## Recommendation and viable slice

Prefer **A**, conditional on preserving keyboard-open Post reachability and non-overlap; do not silently drop those requirements to remove the panel. If those outcomes cannot coexist, revisit B before implementing a different interaction. Validate editor-to-container top offset and intentional bottom gaps, not just editor identity or initial paint. Remove blank reserved space without reintroducing asynchronous Post movement or hiding meaningful feedback.

From board New Thread, ordinary Reply or dedicated New Thread/reply entry, write, leave editing, resume the retained draft and submit exactly once; correction/retry stays usable. Empty inline forms collapse; leaving phone editing is distinct from discarding or hiding a nonempty draft. Preserve secondary actions, identity/anonymous posting, no-script, desktop, accessibility and shared Forte/QDB behavior. Check empty/loaded/expanded/error/attachment states, first paint, menus, rotation and keyboard reopening across affected themes. User iPhone testing follows automated verification; no upfront device gate, migration or deployment.

## Request record

Initial request: anchored expansion, tighter spacing and a usable reply exit. Additional request before assessment handoff: include dedicated New Thread spacing, remove visible Body and improve its prompt. Both are included in scope v1; no earlier estimate is invented. Step 1 assessment only; implementation awaits approvals.

The user subsequently requested committing and pushing these planning artifacts before the normal Step 3 commit gate. This authorizes plan persistence only; Step 1 review and later implementation approvals remain outstanding.

## Effort snapshots

- Intake — 2026-10-21T20:11:15.391805+00:00; study `railspike-effort`, cycle `v3/composer_anchor_exit`, parent `v3/reply_composer_focus_layout`; scope v1 from `f460330f6112aba33af368f62f71aa813a514cd4`, R1/P2. Codex, single exposed rater; original cycle, prior scores and current lifecycle/style/template source inspection were visible before this snapshot. **0.50 total projected scope**, including investigation, planning, fixes, verification and documentation: broader than a spacing-only fix, below R1 composer decluttering (0.60) with existing shared infrastructure. This is not a pristine pre-inspection estimate or remaining effort.
- Step 1 handoff — 2026-10-21T20:11:15.391805+00:00; **0.50 reaffirmed**, same scope/reference/rater/exposure and starting revision. Outcome includes exit/resume and one submission/retry across the named surfaces; no device infrastructure assumed. Approval context: user requested Step 1, no implementation approval. Active-work measurements and independent ratings unknown; maintainer fit/acceptance pending.
- Frozen R1 verified: report SHA-256 `d3a30b6400b83bb25283b4234b41ba07515df9a696ea44de59c10186103771b0`; diff review `068b3519326e08a939df40b5abe0106ddcbab80bd796616c76b5084a21462b84` in `/home/wsl/railspike-dev/docs/research/effort_estimation/`; feedback `ef335604ecd5b51e5c04067d88e4d6f01cb4fdb21b8f4d7dba7b5b90e4012bfc` verified from Git `4f6cfe2b436fae8d939b1349fe724a3879ee0a0e`. Historical sources were not silently replaced by current feedback.
