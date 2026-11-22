> **Feature plan:** [Step 1](./fastmod_bayes_meta_step1_solution_assessment.md) · [Step 2](./fastmod_bayes_meta_step2_feature_description.md) · [Step 3](./fastmod_bayes_meta_step3_development_plan.md) · Step 4

Debug mode: enabled

Effort study: `railspike-effort` / `v3/fastmod_bayes_meta`; [cycle ledger](../effort_ledger/fastmod_bayes_meta.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

**Cycle estimate: 0.55**

Step 1 approved (Revision 1, Option C in two slices) and committed as `94b70e0` on `feat/fastmod-bayes-meta`.

## Problem

Production has many posts and stored labels, but the LLM can't run there, so new posts get no `meta` score and no derived `#meta` tag. A local word-count model can learn meta from what is already stored and score every post on the server at no cost.

## Correction to Step 1

Step 1 said an operator can remove a wrong derived tag. Individual tag removal is retired (`./v3 fast-score meta-status` says so); only legacy suppressions are still honored. In this cycle, a wrong tag is fixed by retraining, raising the local threshold, or switching meta back to the LLM source. Per-post correction is listed under Enhancement Requests.

## User stories

- As an operator, I want to train a meta model from our own production data with one server command, so that meta scoring works while the LLM is off.
- As an operator, I want a report of how the model does on posts it didn't train on, including every disagreement, so that I can decide whether to trust it before any tag depends on it.
- As an operator, I want to switch the meta rubric between the LLM and the local model on the Fastmod page, so that I can turn the local model on and off without a deploy.
- As a reader, I want new support threads tagged `#meta` soon after they're posted, so that I can find feedback and bug reports.
- As an operator, I want to retrain the model from a Retrain button on the Fastmod page, so that I don't need SSH to keep it current. (Revision 2)
- As an operator, I want scoring to catch up in the background through the task queue after a retrain or a failed publish-time score, so that no root is left unscored. (Revision 2)
- As an operator, I want to correct training labels in a private file, so that untagged support posts stop teaching the model that support talk is not meta. (Revision 3)
- As an operator looking at a post, I want to see its local meta score and the words that drove it, so that I can tell why a post was or wasn't tagged.

## Core requirements

- **No provider calls.** Training, scoring and reporting work with no LLM key and with LLM scoring disabled.
- **Labels come only from people and stored LLM scores.** Meta: an authored `#meta` tag, or a stored LLM `meta` score at or above 0.90. Not meta: a legacy suppression, or a stored LLM `meta` score below 0.10. The model never trains on its own scores. Untagged posts with no LLM score count as weak not-meta only when the operator asks for it, and the report says whether they were used.
- **Thread roots only.** The `#meta` tag applies to thread roots, so the model trains on and scores roots.
- **Scores don't grow with post length** (Revision 3). A post's score is the average evidence of its words, damped toward the prior for short posts, instead of the sum. Hashtags and URLs are ignored, so an authored `#meta` can't teach the model its own label.
- **Operator labels come first** (Revision 3). A private file, `state/private/fastmod_local_meta_labels.txt`, with lines like `thread-…: meta` or `thread-…: not`, overrides every other label. It is never published, exported or turned into a tag.
- **The threshold comes from cross-checking** (Revision 3). Training scores every labeled root with a model that didn't see it (five folds), picks the threshold from all of those scores, and reports precision and recall over them. One held-out fifth proved too small.
- **Russian posts are always not meta** (Revision 1). A root that is mostly Cyrillic gets a local meta score of 0 with a `cyrillic` signal and is left out of training.
- **Local scores stay apart from LLM scores.** Each local score records its source, the model build it came from, and the content it scored. It never replaces a stored LLM score, never appears as one, and never leaves the instance: no public export, no score transfer, no content repository.
- **Tagging uses its own threshold.** A local score gets the tag only at or above a local threshold, which the training report recommends from held-out precision. The automatic-tagging flag and legacy suppressions still apply. Changing the meta source leaves stored moderation scores valid.

## Delivery scope

- Work type: application change.
- **Slice 1, report only:** trainer, local scoring of all roots, held-out report, and the local score on post detail. Nothing public changes.
- **Slice 2, tagging:** the meta source setting on the Fastmod page, derived `#meta` from local scores, and scoring new roots at publish.
- **Gate:** slice 2 starts only if the slice 1 report on production meets the success criteria below, or the maintainer accepts the numbers it shows.

## Completion boundary

- **Normal entry:** an operator presses Retrain on Tools → Fastmod (or runs `./v3 fast-score local-train` on the server), then sets meta to "Local" there. (Revision 2: Retrain button.)
- **End to end:** existing roots get local scores; roots above the local threshold show `#meta` on live, static and offline pages after the usual refresh; a new root gets its score when published.
- **Recovery:** a failed publish-time score or a new model build queues a local score sweep, which scores every root missing a current score on the next worker run. If training finds too few labels, the command stops, names the counts, and suggests the weak-negative option. If the model file is missing or unreadable, tagging falls back to stored LLM scores and logs why, the same way missing private scores do today. Switching the source back to LLM removes local-derived tags on the next refresh.
- **Release condition:** slice 2 merged and deployed, the maintainer runs the trainer on production, and the report passes the gate.

## Known environment

Production, with the LLM unavailable. Entry is SSH into `~/v3`, often from iPhone in a-Shell, so command output must read well on a narrow terminal and the disagreement list must also be saved to a file. Evidence must come from production's own data: the label counts and held-out numbers from a run there. A run on a local clone with test data proves the code works, not that the model is good enough.

## Risks

- **Too few clear not-meta labels.** Impact: a model that calls everything meta. Earliest check: the label-count line of the first production run. Mitigation: the weak-negative option, reported separately, and the run stops below a minimum.
- **Software talk that isn't support.** Impact: false `#meta` tags on code reviews, Railspike notes and posts about other projects. Earliest check: the disagreement list. Mitigation: threshold chosen for precision, top words shown per post, and the slice 2 gate.
- **Naive Bayes is overconfident.** Its raw outputs bunch near 0 and 1, so the LLM's 0.90 doesn't mean the same thing. Mitigation: a separate local threshold picked from held-out results, never the shared 0.90.
- **Score identity coupling.** Derived tags read scores keyed by the current rubric revision. A careless change could hide stored LLM scores or make local ones look like LLM ones. Earliest check: Step 3 test with both kinds of score present. Mitigation: local scores keyed by model build; the meta source setting doesn't change the LLM revision.

## Shared component inventory

- `src/ForumRewrite/Scoring/SqliteFastScoreStore.php`: private score store, already records a `source` per score. **Reuse** for local scores. Step 3 decides whether that needs a new key or a table beside it.
- `src/ForumRewrite/Scoring/FastScoreContextFactory.php`: content hash. **Reuse**, so local scores match edited content the same way LLM scores do.
- `src/ForumRewrite/Scoring/FastScoreResult.php`: `scoredSet(..., source)`. **Reuse** with a new source value.
- `src/ForumRewrite/Scoring/FastmodDerivedTags.php`, `FastmodMetaRead.php`, `FastmodTaggingPolicy.php`, `FastmodMetaConfig.php`: derived tags and policy. **Extend**: the meta mapping reads local scores when its source is Local, with its own threshold.
- `templates/pages/fastmod.php`, `src/ForumRewrite/Http/ToolsPageController.php`: rubric controls with signed operator saves. **Extend** with a per-rubric source choice using the same signed settings action.
- `scripts/fast_score.php` and the `./v3` launcher: `fast-score` subcommands, including read-only `meta-status`. **Extend** with train, score and report subcommands in the same style. `meta-status` reports the source and model build.
- `templates/pages/post.php`, `RouteServices::latestFastScoreForPost`: per-rubric score display. **Extend** to show the local score and its top words.
- `src/ForumRewrite/TaskQueue/SqliteTaskQueueStore.php`, `TaskQueueWorker.php`, `scripts/task_queue.php`: allowlisted task types with deduplication and visible status. **Extend** with two types, local training and local score sweep. The LLM `fast_score_sweep` stays separate, since it depends on the provider. (Revision 2)
- `src/ForumRewrite/Http/DeployController.php`: Deploy now queues work for a signed-in root-approved operator. **Reuse** that pattern for Retrain. (Revision 2)
- Operator labels file (Revision 3): **New**, read by `LocalMetaTraining`. Seeded with Claude's 1,213 labels of public ZenMemes roots, kept in this plan folder.
- `src/ForumRewrite/Scoring/FastmodScoreTransfer.php`: score transfer. **Extend** only to exclude local scores.
- `src/ForumRewrite/Analysis/RelatedContentSearchService.php` has a private ASCII tokenizer with a stopword list. **New**: a small shared Latin tokenizer and the naive Bayes model, with no new dependency. Russian needs no tokenizing, since it is never meta. The related-content tokenizer stays unchanged in this cycle.

## Concepts touched

- [Fastmod scoring](../../concepts/fastmod_scoring.md): changed. A rubric score may come from a local model, with its own source and identity.
- [Effective tags](../../concepts/effective_tags.md): changed. Derived `#meta` can come from a local score at the local threshold.
- **Local rubric model** (new inventory entry): a private, per-instance word-count model built from the instance's human and stored LLM labels and rebuilt by command.

## User flow

1. The operator presses Retrain on Tools → Fastmod, or runs the train command on the server. The task queue runs training, then a local score sweep. (Revision 2)
2. It prints label counts, held-out precision and recall, a recommended local threshold, and the number of disagreements. It saves the full disagreement list to a private file.
3. The operator runs the score command. Every root gets a local meta score; post detail shows it with its top words. Nothing public changes.
4. *(Slice 2)* On Tools → Fastmod, the operator sets meta's source to Local and saves.
5. *(Slice 2)* After the usual static/offline refresh, roots at or above the local threshold show `#meta`.
6. *(Slice 2)* A new root post gets its local score when published and shows `#meta` on its next page load if it qualifies.
7. Later, the operator reruns training as labels grow. A new model build rescores roots; older scores are kept until replaced.

## Success criteria

- On production's held-out roots, at the recommended threshold: precision at least 0.95 and recall at least 0.75 against stored labels. This is the slice 2 gate.
- Zero provider calls across training, scoring and tagging, checked by the exchange log.
- Scoring every production root takes under a minute on the server.
- With meta set to Local, every root at or above the threshold shows `#meta` on live, static and offline pages, and none below it do.
- No local score or model content appears in the public export, a score transfer file, or the content repository.

*— Claude · 2026-11-14 UTC · Model: claude-opus-5-5*

**Revision 1 (2026-11-14):** the maintainer said to ignore Russian text for now and treat it as always not meta, and approved Step 2 with that change.

**Revision 2 (2026-11-14):** the maintainer asked for both the task-queue sweep and a Retrain button.

**Revision 3 (2026-11-22):** the production run with weak negatives found no threshold at 0.95 precision. Offline, on public ZenMemes data: one in four untagged roots is really meta, and summing word evidence lets long posts score near 1. The maintainer approved fixing the model, adding an operator labels file seeded with Claude's labels, and picking the threshold by cross-checking.

## Study checkpoint

2026-11-14T19:30:00Z, `railspike-effort` / `v3/fastmod_bayes_meta`, Step 2 handoff. Step 1 approved and committed as `94b70e0`. Scope v2 as approved, made specific: thread roots only, separate local threshold, Fastmod source setting, transfer exclusion. Projected total 0.35 → **0.40** (new understanding: a per-rubric source setting with signed save, a separate threshold, and keeping local scores out of transfer). Same R1/P2, single exposed Claude rater. No implementation.

Revision 1, 2026-11-14T19:20:00Z: Russian roots are always not meta and left out of training, so no Unicode tokenizer is needed. Projected total **0.40** reaffirmed (a slightly smaller tokenizer, offset by script detection). Approved with this change.

Revision 2, 2026-11-14T20:10:00Z: maintainer asked for a local score sweep task and a queued Retrain button. Projected total 0.40 → **0.45** (new understanding: two task types and an operator button). Approved with the request.

Revision 3, 2026-11-22T23:30:00Z: maintainer approved the model fix, operator labels file and cross-checked threshold after the failed production gate. Projected total 0.45 → **0.55** (new understanding: label noise and length bias found in Stage 5; model change, labels file, five-fold threshold, and about 1,200 hand labels).

## Enhancement Requests

- "Local, LLM when unsure" (Option B's fallback). Disposition: follow-up cycle, once the LLM can run on production again.
- Other cheap categories (bug vs feature request, todo). Disposition: follow-up, once the source setting exists.
- Per-post correction of a wrong derived tag, which also becomes a training label. Disposition: follow-up cycle; individual removal is retired today.
- A Unicode tokenizer for related-content search. Disposition: follow-up, could reuse this cycle's tokenizer.
- Real meta scoring for Russian posts. Disposition: follow-up; always not meta for now (Revision 1).
- Retire the 0.90 threshold for Bayes-only scores in favor of a calibrated one. Disposition: resolved here; a separate local threshold is recommended by the report.
