> **Feature plan:** [Step 1](./fastmod_meta_score_step1_solution_assessment.md) · [Step 2](./fastmod_meta_score_step2_feature_description.md) · [Step 3](./fastmod_meta_score_step3_development_plan.md) · [Step 4](./fastmod_meta_score_step4_implementation_summary.md)

Debug mode: enabled

Implementation in progress on `feat/fastmod-meta-score`. Planning commit: `08b9b874`.

## Stage 1 - Modular rubric contract
- Changes: Added catalog/composer with deterministic stable identities, compact moderation/support templates, shared introduction, nonempty selection validation, and effective composition hashes. Configuration accepts a rubric list; production wiring follows in Stage 3.
- Verification: `php tests/run.php FastmodRubricsTest FastScoringConfigTest` — 6 passed; new PHP files lint clean. Tests cover omission, ordering, invalid sets, added templates, empty text, and revision changes. Compared moderation template against existing categories and exceptions.
- Notes: Both default rubrics are enabled when unset; sorting establishes the output order independent of operator click order. No core read-model change.
