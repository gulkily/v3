# Cycle v3/pledge_cards

| Stable cycle ID | Status | Scope / reference / protocol | Projected total | Canonical checkpoints | Parent / acceptance |
| --- | --- | --- | --- | --- | --- |
| `v3/pledge_cards` | Technically complete; release check and maintainer acceptance pending (every step approved by the maintainer) | v4 / R1 / P2 | 0.65 (intake 0.45; Step 1 0.45; revision 1 0.45; revision 2 0.50; revision 3 0.60; revision 4 0.65; Step 2 0.65; Step 3 0.65; entry 0.65; retrospective 0.70) | [Intake and Step 1](../pledge_cards/pledge_cards_step1_solution_assessment.md#study-snapshots); [Step 2](../pledge_cards/pledge_cards_step2_feature_description.md#study-snapshots); [Step 3](../pledge_cards/pledge_cards_step3_development_plan.md#study-checkpoint); [Step 4](../pledge_cards/pledge_cards_step4_implementation_summary.md#cycle-retrospective) | Parent `v3/pledge_frontend`; cycle 10 in [pledges checklist](../pledges_checklist.md); acceptance pending |

2026-11-17T20:10Z: Correction after closeout: approval now clears baked pages of threads the approved person posted in (maintainer report of stale "Waiting for approval" rows). Defect in this cycle's own promise; no new scope; projection and retrospective 0.70 unchanged.

2026-11-17T19:00Z: Step 4 closeout: four stages complete on `feat/pledge-cards` from `6e82449d` (planning commit `2752eebb`). Entry projection 0.65 reaffirmed; no revisions. Retrospective scope assessment **0.70**; stage retrospectives 0.22, 0.22, 0.16, 0.10, not summed. Release check from the maintainer's iPhone SE and the "claude" key's approval on zenmemes.com pending. Maintainer comfortable-fit judgment pending. Same R1/P2, single exposed rater Claude (claude-opus-5-5); R1 hashes not re-verified.

2026-11-17T18:30Z: Step 4 entry: Step 3 approved by the maintainer; planning commit `2752eebb` on `feat/pledge-cards` from `6e82449d`. Entry projection **0.65** reaffirmed.

2026-11-17T18:20Z: Step 3 handoff: **0.65** reaffirmed, scope v4. Step 2 approved by the maintainer. Stage work 0.18, 0.20, 0.17, 0.10, not summed. Baseline `6e82449d`. Single exposed rater Claude (claude-opus-5-5); R1 hashes not re-verified. No implementation.

2026-11-17T18:10Z: Step 2 handoff: **0.65** reaffirmed, scope v4. Step 1 approved by the maintainer (Option A). Baseline `6e82449d`. Single exposed rater Claude (claude-opus-5-5); R1 hashes not re-verified. No implementation.

2026-11-17T18:00Z: Step 1 revision 4, rewritten clean. The maintainer chose a "Waiting for approval" row for unapproved pledges (+0.05); projection **0.65**, scope v4. Options in the clean version: A (rows) 0.65, B (cards with "More") 0.45, C (site-wide) 1.00, not summed. Earlier versions retained here. No implementation.

2026-11-17T17:55Z: Step 1 revision 3. The maintainer asked that only approved users' pledges appear, to cut complexity. Approved-only rule and removal of `FORUM_PLEDGES_COUNT_UNNAMED_ENABLED` added (+0.10); projection **0.60**, scope v3. v1 and v2 retained. No implementation.

2026-11-17T17:50Z: Step 1 revision 2. The maintainer asked to collapse each pledge to the bare minimum. Option F (one row per pledge, joined into a list, opening to the full pledge) 0.50 added and recommended; projection **0.50**, scope v2. v1 and its 0.45 retained. No implementation.

2026-11-17T17:40Z: Step 1 revision 1. The maintainer asked to hide the less relevant buttons until a card is opened. Options D (hidden behind "More", a pending review stays out) 0.45 and E (tap the card) 0.60 added; recommendation B → D; projection **0.45** reaffirmed. Base now `origin/main` at `964966c1` (PR #313 merged). No implementation.

2026-11-17T09:20Z: standing P2 enrollment for pledge cards, staged. Intake **0.45** for Option B (compact header, one action row, a "…" menu on pledge cards), after rendering the thread at 375 pixels, reading `post_card.php` and the pledge partials, and mocking Option B in the browser; no pristine intake. Step 1 handoff **0.45**; option estimates A 0.30, B 0.45, C 0.90, not summed. Baseline `feat/pledge-frontend` at `b7e99459`. Single exposed rater Claude (claude-opus-5-5); R1 hashes not re-verified. No implementation.
