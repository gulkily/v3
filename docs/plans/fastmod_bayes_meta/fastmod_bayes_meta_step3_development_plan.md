> **Feature plan:** [Step 1](./fastmod_bayes_meta_step1_solution_assessment.md) · [Step 2](./fastmod_bayes_meta_step2_feature_description.md) · [Step 3](./fastmod_bayes_meta_step3_development_plan.md) · Step 4

Debug mode: enabled

Effort study: `railspike-effort` / `v3/fastmod_bayes_meta`; [cycle ledger](../effort_ledger/fastmod_bayes_meta.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.45**

Step 2 approved (Revision 1: Russian always not meta) and committed as `6b17a17` on `feat/fastmod-bayes-meta`. Step 2 Revision 2 (local score sweep task and Retrain button) committed as `11d1e3d`.

Concepts touched: [Fastmod scoring](../../concepts/fastmod_scoring.md) and [effective tags](../../concepts/effective_tags.md) updated in Stage 7; new **local rubric model** entry added in Stage 7.

## Completion Contract

- **Normal entry:** Tools → Fastmod → Retrain (or `./v3 fast-score local-train` on the server). The task queue trains, then sweeps scores. Then Tools → Fastmod → meta source: Local.
- **End to end:** every root has a local meta score shown on post detail. With source Local, roots at or above the model's threshold show `#meta` on live pages at once and on static/offline pages after the usual refresh. New roots are scored when published.
- **Recovery:** a new model build or a failed publish-time score queues one local score sweep, which scores every root missing a current-build score. Too few labels stops training with counts and the `--weak-negatives` hint. A missing or unreadable model or score table makes tagging fall back to stored LLM scores and log why. Source back to LLM removes local-derived tags on the next read.
- **Deployment:** deploy is the usual pull. No read-model schema change. The private score database gains one table, created on first use.
- **Release condition:** merged, deployed, the production report passes the gate (precision ≥ 0.95, recall ≥ 0.75 on held-out roots) or the maintainer accepts it, and Stage 8 is done on production.

## Key Risks

- **High risk:** too few clear not-meta labels on production. Impact: a model that calls everything meta. Early validation: Stage 5's label counts. Mitigation: a minimum count stops training; `--weak-negatives` is opt-in and reported.
- **High risk:** false `#meta` on software talk that isn't support. Impact: noisy public tags. Early validation: Stage 5's disagreement file. Mitigation: threshold chosen for precision; slice 2 is gated on Stage 5.
- **Data:** local scores mistaken for LLM scores. Mitigation: a separate table keyed by model build; `post_fast_scores`, rubric revisions and score transfer stay untouched. Stage 3 and 7 tests cover it.
- **Rollback:** source back to LLM, or turn off automatic tagging. Nothing canonical is written.

## Stage 1

**Estimate: 0.06**

- Goal: a pure local meta model: tokenize, detect Cyrillic, train, score, explain.
- Dependencies: none.
- Expected changes: new `LocalMetaModel` in `src/ForumRewrite/Scoring/`: `train(list<array{text,label}>): self`, `score(string $subject, string $body): array{probability, top_words, signals}`, `toJson()` / `fromJson()`. Multinomial naive Bayes with add-one smoothing over lowercase Latin words of 2+ letters, minus a small stopword list. A root that is mostly Cyrillic letters scores 0 with signal `cyrillic`. The model file is readable JSON: word counts, class counts, build id (a hash of its content), threshold, build time.
- Verification approach: new `LocalMetaModelTest`: separable fixtures score correctly; Cyrillic gives 0; JSON round trip gives identical scores; same input gives the same build id.
- Estimate details: driver: model maths and file format; uncertainty: low.
- Risks or open questions: none material.
- Canonical components/API contracts touched: new class. `RelatedContentSearchService` tokenizer not reused: it's private and tuned for search. `FastScoreResult` shape reused for score output.

## Stage 2

**Estimate: 0.08**

- Goal: `./v3 fast-score local-train [--weak-negatives] [--json]` builds and reports a model from production data.
- Dependencies: Stage 1.
- Expected changes: `LocalMetaTraining` collects visible, unretracted roots from the read model. Labels: authored `meta` in `thread_labels_json`; stored LLM meta ≥ 0.90 or < 0.10 from `post_fast_scores` for the current content hash; legacy `fastmod_meta` suppressions as not-meta. Cyrillic roots are skipped. A deterministic 80/20 holdout split by post-id hash; recommended threshold = the lowest held-out threshold with precision ≥ 0.95. Prints counts, precision, recall and threshold in narrow lines. Saves disagreements (post id, label, score, top words, subject) to `state/private/fastmod_local_meta_disagreements.txt`. The final model trains on all labels and is saved atomically to `state/private/fastmod_local_meta.json`. Stops with counts below 20 of either class.
- Verification approach: `FastmodLocalTrainCommandTest` on a fixture read model and score database: label precedence, Cyrillic skip, holdout determinism, refusal below the minimum, weak negatives only with the flag.
- Estimate details: driver: label joins across read model and private store; uncertainty: medium, in how labels look on production.
- Risks or open questions:
  - Impact: authored tags may live elsewhere than `thread_labels_json` for older threads.
  - Early warning / validation: compare counts against the 231 on `/tags/meta` in Stage 5.
  - Mitigation: report authored and LLM-derived counts separately.
- Canonical components/API contracts touched: `scripts/fast_score.php` and `./v3` usage (new subcommand next to `meta-status`); `FastScoreContextFactory` content hash; `SqliteFastScoreStore` read-only; `FastmodDerivedTags` suppression read pattern.

## Stage 3

**Estimate: 0.08**

- Goal: local meta scores for every root, from a command or a queued sweep.
- Dependencies: Stage 2.
- Expected changes: new table in the private score database, created on first use: post id, content hash, rubric (`meta`), model build, probability, top words, signals, scored time; one row per post, content and build. New `LocalMetaSweep::run(): array{scored, skipped, remaining}` scores roots missing a current-build score, in batches. Task type `local_score_sweep` (dedup key `local-score-sweep`) runs it in `TaskQueueWorker` and requeues while work remains, like `fast_score_sweep`. `./v3 fast-score local-score` runs the same sweep directly. Saving a new model build enqueues the sweep. `meta-status` adds model build, threshold, source and local score count.
- Verification approach: command and worker tests: all roots scored, rerun is idempotent, edited content gets a new row, repeat enqueue reuses one task, `post_fast_scores` row count unchanged. Time a 2,000-root fixture to under 10 s.
- Estimate details: driver: new private table, sweep task and status output; uncertainty: low.
- Risks or open questions: none material.
- Canonical components/API contracts touched: `SqliteFastScoreStore` migration pattern; `SqliteTaskQueueStore` type list and `TaskQueueWorker` dispatch (pattern of `runFastScoreSweep`); `meta-status` output; the internal task queue concept's task-type list.

## Stage 4

**Estimate: 0.07**

- Goal: post detail shows the local score; Tools → Fastmod gets a Retrain button and the last training report.
- Dependencies: Stage 3.
- Expected changes: `RouteServices::latestLocalScoreForPost()` beside `latestFastScoreForPost()`; `templates/pages/post.php` adds a "Local meta" row with score, top words, and "earlier model" when the build is old. Task type `local_meta_train` (dedup key `local-meta-train`) runs Stage 2's training without weak negatives and, on success, enqueues the sweep; too few labels marks the task failed with the counts. A Retrain button on the Fastmod page queues it for a signed-in root-approved operator, following Deploy now's session check. The page shows the last report (time, counts, precision, recall, threshold, task status) and, to operators only, the disagreement list.
- Verification approach: post-detail smoke with and without a local score; Retrain refused for anonymous and non-root viewers; queued once on double press; worker test for success and too-few-labels. Check two themes at phone width.
- Estimate details: driver: one template row, one button, one task type; uncertainty: low.
- Risks or open questions: none material.
- Canonical components/API contracts touched: the existing Fastmod row in `templates/pages/post.php`; `ToolsPageController` and `templates/pages/fastmod.php`; `DeployController` operator check and queue response pattern; `TaskQueueWorker` dispatch.

## Stage 5

**Estimate: 0.02**

- Goal: slice 1 gate on production. The maintainer deploys, presses Retrain (or runs `local-train`), waits for the sweep, and shares the report and disagreement list.
- Dependencies: Stages 1–4 merged and deployed.
- Expected changes: none in code. The report is recorded in the Step 4 summary.
- Verification approach: real production numbers against the gate; zero new rows in the LLM exchange log.
- Estimate details: driver: maintainer run and review; uncertainty: high, the outcome is unknown.
- Risks or open questions:
  - Impact: a failed gate ends the cycle here.
  - Early warning / validation: label counts.
  - Mitigation: retry with `--weak-negatives` once, then stop or accept.
- Canonical components/API contracts touched: none.

## Stage 6

**Estimate: 0.10**

- Goal: a meta source setting (LLM or Local) on Tools → Fastmod, with derived tags following it.
- Dependencies: Stage 5 passed.
- Expected changes: the signed `prepare_fastmod_rubrics` / `finalize_fastmod_rubrics` payload gains an optional per-rubric source map, saved to `state/private/fastmod_rubric_sources.json`, so the rubric revision doesn't change. `FastmodDerivedTags::mappings()` gains a source per mapping. With Local, `candidates()` reads the local table for the current model build and uses the model's threshold. A missing model or table falls back to LLM scores with a logged reason. `templates/pages/fastmod.php` adds a radio pair beside the meta checkbox.
- Verification approach: extend `FastmodDerivedTagsTest` and `FastmodTaggingFlagTest` (local, LLM, missing model, automatic tagging off, legacy suppression); extend the signed-settings smoke and `tests/browser/fastmod.mjs` for the radio.
- Estimate details: driver: signed payload change and two score sources in derived tags; uncertainty: medium.
- Risks or open questions:
  - Impact: changing the payload could break existing saved selections.
  - Early warning / validation: test loading an old selection file with no sources file.
  - Mitigation: a missing sources file means LLM for every rubric.
- Canonical components/API contracts touched: `ToolsPageController`, `templates/pages/fastmod.php`, `FastmodSelection`, `FastmodDerivedTags`, `FastmodMetaRead`, `FastmodTaggingPolicy`.

## Stage 7

**Estimate: 0.05**

- Goal: new roots scored at publish; transfer stays clean; docs updated.
- Dependencies: Stage 6.
- Expected changes: `RouteServices::enqueueFastScoreForPublishedPost()` scores a new root locally when a model exists, even with LLM scoring disabled; errors are logged and queue a local score sweep; they never fail the publish. A test pins that score transfer exports only `post_fast_scores`. Update the two concepts, add the local rubric model entry, the Fastmod runbook and `./v3` usage.
- Verification approach: publish-flow test: root scored, reply not scored, a broken model doesn't block publish and queues the sweep. Transfer test. Smoke set plus Fastmod tests; `git diff --check`.
- Estimate details: driver: publish hook and docs; uncertainty: low.
- Risks or open questions: none material.
- Canonical components/API contracts touched: `RouteServices`, `FastmodScoreTransfer`, `docs/concepts/`.

## Stage 8

**Estimate: 0.02**

- Goal: production acceptance of slice 2.
- Dependencies: Stage 7 merged and deployed.
- Expected changes: none in code. The maintainer sets meta to Local, refreshes static/offline, and posts a test root.
- Verification approach: `meta-status` shows source Local and a matching candidate count; a support-style test root shows `#meta` on its next load; `/tags/meta` count is plausible; switching back to LLM removes local tags.
- Estimate details: driver: maintainer check; uncertainty: low.
- Risks or open questions: none material.
- Canonical components/API contracts touched: none.

## Study checkpoint

2026-11-14T19:40:00Z, `railspike-effort` / `v3/fastmod_bayes_meta`, Step 3 handoff. Step 2 approved with Revision 1 and committed as `6b17a17`. Eight stages: model, train command, score command, post detail, production gate, source setting, publish hook and docs, production acceptance. Stage work 0.06/0.08/0.05/0.03/0.02/0.10/0.05/0.02, non-additive. Projected total **0.40** reaffirmed. Same R1/P2, single exposed Claude rater.

Revision 1, 2026-11-14T20:15:00Z: Step 2 Revision 2 adds the local score sweep task (Stage 3) and the queued Retrain button with report (Stage 4); publish failures queue the sweep (Stage 7). Stage work 0.06/0.08/**0.08**/**0.07**/0.02/0.10/0.05/0.02, non-additive; Stage 3 and 4 originals 0.05 and 0.03 kept. Projected total 0.40 → **0.45**. Same R1/P2, single exposed Claude rater.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*
