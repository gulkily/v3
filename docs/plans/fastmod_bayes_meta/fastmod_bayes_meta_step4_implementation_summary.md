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

## Stage 2 - Train command

**Original estimate: 0.08**
**Retrospective: 0.09**

- Changes:
  - New `LocalMetaTraining`: `collect(bool $weakNegatives)`, `run()`, `runAndSave()`, `chooseThreshold()`.
    - Labels in precedence order: legacy suppression (not meta), authored `meta` in `thread_labels_json`, `board_tags_json` or `post_tags_json` (meta), stored LLM meta score for the root's current content hash, ≥ 0.90 meta and < 0.10 not meta. Scores between are skipped as unsure.
    - A stored score for older text doesn't count, so edited posts aren't mislabeled.
    - Reads only `post_fast_scores` rows with source `llm`, never local scores.
    - Visible, unretracted roots only; replies and mostly Cyrillic roots skipped.
    - Holdout: one in five roots by post-id hash. The threshold is the widest cut still at 0.95 precision, placed halfway in log-odds between the last kept score and the next one. A cut right at a held-out score was fragile: naive Bayes pushes scores to 0.9999…, and the full model then scored a clear support post just under it.
  - New `LocalMetaTrainingRefused`: names the counts and suggests `--weak-negatives` when not-meta labels are short. Exit code 2.
  - New `LocalMetaFiles`: model at `state/private/fastmod_local_meta.json`, report at `state/private/fastmod_local_meta_report.txt`, both written atomically with mode 0600. A refusal leaves the previous model.
  - Report is `Key: value` lines, then one block per held-out disagreement (post, label and its source, score, top words, subject). The disagreement list lives in the report file rather than a separate file, so Stage 4 has one file to show.
  - `./v3 fast-score local-train [--weak-negatives] [--json] [--database-path=PATH]` in `scripts/fast_score.php` and the `./v3` usage. Text output is the summary lines plus the report path.
  - New `tests/LocalMetaTrainingTest.php` (6 tests), registered.
- Verification:
  - `./v3 test --smoke LocalMetaTrainingTest LocalMetaModelTest FastmodMetaTest`: 464 run, 463 passed. The one failure, `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`, also fails on `459d212` (checked in a temporary worktree), so it isn't from this change.
  - Full suite not run.
- Notes:
  - The CLI test covers only the refusal, because a successful run writes into `state/private/` under the project root. The save path is covered through `LocalMetaFiles` with a temporary root.
  - Real label counts and accuracy are unknown until Stage 5 runs on production.
- Estimate details: original scope as Step 3; no revision; retrospective slightly higher for the threshold rework; uncertainty medium until Stage 5.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*
