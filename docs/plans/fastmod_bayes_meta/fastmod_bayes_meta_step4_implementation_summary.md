> **Feature plan:** [Step 1](./fastmod_bayes_meta_step1_solution_assessment.md) · [Step 2](./fastmod_bayes_meta_step2_feature_description.md) · [Step 3](./fastmod_bayes_meta_step3_development_plan.md) · [Step 4](./fastmod_bayes_meta_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/fastmod_bayes_meta`; [host ledger](../effort_estimation_trial.md#cycle-v3fastmod_bayes_meta). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.45**

## Step 4 entry

2026-11-14T20:20:00Z. Step 3 approved (Revision 1) and committed as `a61c320` on `feat/fastmod-bayes-meta`, after Step 1 `94b70e0`, Step 2 `6b17a17` and Step 2 Revision 2 `11d1e3d`. Branch based on `459d212`. Whole-cycle projection **0.45** reaffirmed. Stage originals 0.06/0.08/0.08/0.07/0.02/0.10/0.05/0.02 copied from Step 3 Revision 1 (Stage 3 and 4 first values 0.05 and 0.03); non-additive. Same R1/P2, single exposed Claude rater.

## Stage 1 - Local meta model

**Original estimate: 0.06**
**Retrospective: 0.05**

- Changes:
  - New `src/ForumRewrite/Scoring/LocalMetaModel.php`: `train()`, `score()`, `withThreshold()`, `buildId()`, `toJson()` / `fromJson()`, `isCyrillic()`, `tokens()`.
  - Naive Bayes over document frequencies: each word counts once per post, add-one smoothing, words seen in fewer than two posts dropped.
  - Mostly Cyrillic text scores 0 with signal `cyrillic` and is skipped in training.
  - `score()` returns the up to five words that pushed hardest toward the result.
  - The build id hashes the counts only, so retraining on the same labels gives the same id. `fromJson()` rejects a file whose counts don't match its id.
  - The model file stays JSON: internal derived state, which `CONTRIBUTING.md` allows.
  - New `tests/LocalMetaModelTest.php`, registered in `tests/run.php`.
- Verification:
  - `./v3 test LocalMetaModelTest`: 4 passed. The first run failed one assertion because the fixture had words seen only once; the fixture was widened, the model left as is.
  - Full suite not run.
- Notes: the `RelatedContentSearchService` tokenizer was not reused (private, search-specific stopwords), as Step 2 recorded.
- Estimate details: original scope as Step 3; no revision; uncertainty low.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*
