> **Feature plan:** [Step 1](./nav_tree_step1_solution_assessment.md) · [Step 2](./nav_tree_step2_feature_description.md) · [Step 3](./nav_tree_step3_development_plan.md) · [Step 4](./nav_tree_step4_implementation_summary.md)

Debug mode: enabled ([log](./nav_tree_debug_log.md))

Effort study: `railspike-effort` / `v3/nav_tree`; [host ledger](../effort_ledger/nav_tree.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.70**

Branch: `feat/nav-tree` from `origin/main` `2d7805c`. Planning commit: `f585d60`.

## Stage 1 - Flag and risk probes

**Original estimate: 0.05**

**Retrospective: 0.05**

- Changes:
  - `FORUM_VISIT_HISTORY_ENABLED` in `FeatureFlagRegistry.php`: Experience group, off by default, changeable by the site, beside Usage reports.
  - `FeatureFlagEvaluatorTest` lists it in both registry lists.
  - Ledger file `effort_ledger/nav_tree.md` brought over from the planning branch, with the Step 4 entry line. Checklist notes brought over and updated.
- Verification:
  - `./v3 test --smoke FeatureFlagEvaluatorTest`: passed, 487 run, 0 failed (24 flag tests). Full suite not run.
  - Probe, outside the repository, with `playwright-core` 1.58.1 in Chromium 1243, Firefox 1509 and WebKit 2248:
    - A link with `target="_blank"`, with or without `rel="noopener"`: the new tab starts with empty `sessionStorage` in all three.
    - `window.open` without `noopener`: the new tab gets a copy of the opener's `sessionStorage` in all three. The copied cursor is the opener's current visit, which is the right parent, so no detection is needed. The copy is not shared afterwards.
    - 2,000 visits with 80-character titles and thread addresses: about 320 KB, stored without error in all three.
- Notes:
  - Neither probe changes a Step 2 decision. The cap stays at 2,000; titles are cut at 120 characters to bound the size.
  - Step 3's mitigation "a copied cursor is detected" is dropped as unneeded; recorded here as new understanding, not changed scope.
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Enhancement Requests

- Sync, in-page changes as visits, `tree_history` and `filmstrip`: unchanged from Step 2, all deferred or separate.

## Study Snapshots

2026-11-25T07:45Z; scope v2; starting revision `2d7805c8`; planning commit `f585d60`; reference R1, hashes not re-verified. Claude, single exposed rater.

- Step 4 entry: **0.70 projected total**, reaffirmed from Step 3. Stage estimates copied from Step 3, not re-rated.
