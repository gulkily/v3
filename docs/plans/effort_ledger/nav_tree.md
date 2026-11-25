# Cycle v3/nav_tree

| Stable cycle ID | Status | Scope / reference / protocol | Projected total | Canonical checkpoints | Parent / acceptance |
| --- | --- | --- | --- | --- | --- |
| `v3/nav_tree` | Complete: merged in PR #420 and deployed 2026-11-25; accepted | v2 / R1 / P2 | 0.70 (retrospective 0.72) | [Step 1](../nav_tree/nav_tree_step1_solution_assessment.md#study-snapshots), [Step 2](../nav_tree/nav_tree_step2_feature_description.md#study-snapshots), [Step 3](../nav_tree/nav_tree_step3_development_plan.md#study-snapshots), [Step 4](../nav_tree/nav_tree_step4_implementation_summary.md#study-snapshots) | Item 1 of the [browsing tree checklist](../browsing_tree_checklist.md) (`v3/browsing_tree_checklist`); no parent cycle; maintainer: comfortable fit, accepted 2026-11-25 |

2026-11-15T02:44:42Z — v3/nav_tree Step 1 intake and handoff (standing P2 enrollment): projection **0.55** for Option B, scope v1, starting revision `8bec1f7d`. Not pristine (after reading the service worker, usage log, navigation slots and paned readers). Alternatives A 0.45, C 0.75. Single exposed Claude rater. Recorded here on 2026-11-24; it was in the draft only.

2026-11-24T17:55Z — Step 1 handoff revision: projection **0.65** for Option B, scope v2 (new understanding, same outcome), starting revision `2d7805c8`. Reason: open beside, more `pushState` readers, `replaceState` and tour frames. Alternatives A 0.50, C 0.85. Single exposed Claude rater who saw the v1 scores.

2026-11-25T07:10Z — Step 2 handoff: projection **0.65** reaffirmed for Option B, scope v2, starting revision `2d7805c8`. Single exposed Claude rater.

2026-11-25T07:25Z — Step 3 handoff: projection **0.70** (from 0.65): three-engine acceptance, pre-paint tree, tour frame check. Seven stages, not summed. Single exposed Claude rater.

2026-11-25T07:45Z — Step 4 entry after planning commit `f585d60`: **0.70** reaffirmed. Single exposed Claude rater.

2026-11-25T09:30Z — Step 4 closeout: delivered scope v2 with two in-scope refinements (Forte pages, branch bars); seven stage commits; retrospective **0.72**. Maintainer fit and acceptance pending.

2026-11-25T18:58Z — Outcome: merged (PR #420), deployed and accepted by the maintainer. Maintainer comfortable-fit judgment: **comfortable fit** (projection 0.70, retrospective 0.72). Counts as one accepted outcome. The post-merge launcher fit failure is fixed in its own one-shot, [`v3/tools_history_reading`](tools_history_reading.md), not counted here.
