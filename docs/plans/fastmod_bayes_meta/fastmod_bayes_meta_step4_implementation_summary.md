> **Feature plan:** [Step 1](./fastmod_bayes_meta_step1_solution_assessment.md) · [Step 2](./fastmod_bayes_meta_step2_feature_description.md) · [Step 3](./fastmod_bayes_meta_step3_development_plan.md) · [Step 4](./fastmod_bayes_meta_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/fastmod_bayes_meta`; [cycle ledger](../effort_ledger/fastmod_bayes_meta.md). Standing P2 enrollment. No parent cycle.

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

## Stage 3 - Local scores and sweep task

**Original estimate: 0.08** (first value 0.05, before Step 3 Revision 1 added the sweep task)
**Retrospective: 0.08**

- Changes:
  - New `LocalMetaScores`: table `local_rubric_scores` in the private score database (post, content hash, rubric, model build, probability, top words, signals, time), created on first write. `readOnly()` opens it without writing, for status and pages. `transaction()` keeps a whole sweep to one disk sync.
  - New `LocalMetaSweep`: `run(int $limit = 5000)` scores visible roots missing a score from the current build for their current text; `enqueue()`; `forProject()` returns null when no model exists.
  - Task type `local_score_sweep` (dedup key `local-score-sweep`) in `SqliteTaskQueueStore`. `TaskQueueWorker` gains `runLocalScoreSweep`: requeues while work remains, and fails without retry when no model exists, saying to press Retrain or run `local-train`.
  - `scripts/task_queue.php` wires the sweep and prints a "Local score sweep" line instead of the Fastmod sweep line.
  - `local-train` now queues the sweep after saving a model. New `./v3 fast-score local-score` runs the sweep directly. `meta-status` adds local model build and time, threshold, and local score count, read-only. The meta source line waits for Stage 6.
  - `docs/concepts/internal_task_queue.md` lists the new task type.
  - New `tests/LocalMetaSweepTest.php` (5 tests), registered.
- Verification:
  - `./v3 test --smoke LocalMetaSweepTest LocalMetaTrainingTest LocalMetaModelTest FastmodMetaTest TaskQueueWorkerTest`: 480 run, 479 passed; the one failure is the same pre-existing `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`.
  - End to end with fixture databases and `FORUM_SECRETS_PATH`/`FORUM_TASK_QUEUE_DATABASE_PATH`: `local-train` printed the report and queued the sweep; `local-score` scored 66 roots; `meta-status` showed the build and 66 scores; `./v3 task-queue run --limit=1` completed the queued sweep (66 scored). Model and report files removed afterwards.
  - 2,000 roots score well under 10 s in the test; `post_fast_scores` row count unchanged.
  - Full suite not run.
- Notes: `SqliteTaskQueueStoreTest` doesn't exist; the store change is covered through `LocalMetaSweepTest`'s enqueue and worker test.
- Estimate details: scope as Step 3 Revision 1; uncertainty low.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 4 - Post detail, Retrain button and report

**Original estimate: 0.07** (first value 0.03, before Step 3 Revision 1 added Retrain and the report)
**Retrospective: 0.08**

- Changes:
  - Post detail: `RouteServices::latestLocalScoreForPost()` reads the score for the post's current text read-only; `templates/pages/post.php` adds a "Local meta" row with score and top words, "(earlier model)" when the model has changed since, and "Russian" for Cyrillic posts. `ThreadAndPostPageController` passes it through `RouteServices`, so its constructor is unchanged.
  - Task type `local_meta_train` (dedup key `local-meta-train`). `TaskQueueWorker` gains `runLocalMetaTrain`: too few labels fails without retry and keeps the counts in the message; other errors retry. `scripts/task_queue.php` trains without weak negatives, saves, and queues the sweep.
  - Tools → Fastmod: a Local meta model section shows the last report, the latest training and scoring task states, and, for a signed-in root-approved operator, a Retrain button and the held-out disagreements (linked to their threads).
  - The Retrain button is a plain form `POST /tools/fastmod/` with `action=retrain`, checked like Deploy: `SameOriginRequest::check()` and `securityReviewAllowed()`. No JavaScript; the reply is the page with a notice. A second press reuses the queued task.
  - `LocalMetaFiles::parseReport()` reads the saved report back for the page. `LocalMetaFiles::probability()` shows ">0.99" and "<0.01" instead of a rounded 1.00 or 0.00, because naive Bayes scores often land within a hair of either end; the report and post row use it.
  - Task times show as `YYYY-MM-DD HH:MM` so they don't wrap on a phone.
  - `docs/concepts/internal_task_queue.md` lists both new task types.
  - New `tests/LocalMetaPageTest.php` (4 tests) and `WriteApiSmokeTest::testPostDetailShowsLocalMetaScoreForCurrentText`.
- Verification:
  - `./v3 test --smoke LocalMetaPageTest LocalMetaSweepTest LocalMetaTrainingTest LocalMetaModelTest FastmodMetaTest TaskQueueWorkerTest DeployPageTest` plus the two post-detail `WriteApiSmokeTest` methods: 492 run, 491 passed; the one failure is the pre-existing `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`.
  - Retrain refused (403, nothing queued) for signed-out, identity-hint-only, non-operator, cross-site and no-origin requests; unknown action 400.
  - Rendered the operator page with a sample report and two disagreements at 390 px wide in the Gloss, Word97 and Midnight themes: no horizontal overflow; section screenshots checked. The theme setting didn't visibly change the section's look in the static capture, so per-theme styling is not fully verified.
  - Sample report and model files removed from `state/private/` afterwards.
  - Full suite not run.
- Notes:
  - The disagreement list and button are decided on the server from the session, as Deploy does; anonymous visitors see the summary only.
  - No new navigation destination, so no icon changes.
- Estimate details: scope as Step 3 Revision 1; uncertainty low.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

## Stage 5 - Production gate

**Original estimate: 0.02**
**Retrospective: pending**

- Status: pending, waiting on the maintainer. Slice 1 (Stages 1–4) is on the PR for this branch.
- To run after merging and deploying:
  1. Tools → Fastmod → Retrain (or `./v3 fast-score local-train` over SSH).
  2. After the next worker run, read the report on the same page.
  3. Gate: precision ≥ 0.95 and recall ≥ 0.75 on held-out roots, or the maintainer accepts the numbers. If training refuses for too few not-meta labels, retry once with `./v3 fast-score local-train --weak-negatives`.
- Stages 6–8 don't start until this passes.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*
