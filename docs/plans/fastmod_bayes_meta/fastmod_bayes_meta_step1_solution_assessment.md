> **Feature plan:** [Step 1](./fastmod_bayes_meta_step1_solution_assessment.md) · [Step 2](./fastmod_bayes_meta_step2_feature_description.md) · [Step 3](./fastmod_bayes_meta_step3_development_plan.md) · Step 4

Debug mode: enabled

Effort study: `railspike-effort` / `v3/fastmod_bayes_meta`; [cycle ledger](../effort_ledger/fastmod_bayes_meta.md). Standing P2 enrollment. No parent cycle; follows [fastmod_meta_score](../fastmod_meta_score/fastmod_meta_score_step1_solution_assessment.md) and [fastmod_virtual_tags](../fastmod_virtual_tags/fastmod_virtual_tags_step1_solution_assessment.md).

Extended estimates: enabled

Cycle estimate: 0.35

## Original Query

So I think for some of the Fastmod categories, especially meta, we can probably get away with a Bayesian filter, or a Bayesian categorizer. Can you please make that assessment, and if it makes sense, write a Step 1.

Follow-up: We have a lot of existing data on production and we can't run the LLM right now.

## Understood Intent

Fastmod sends every post to an LLM, which returns one probability per enabled rubric: `moderation` and `meta`. A `meta` score at or above `FASTMOD_META_THRESHOLD` (0.90) adds a derived `#meta` tag to the thread root. The request asks whether a local naive Bayes classifier can produce the `meta` score instead, and for which categories that is reasonable.

**Assessment: yes for meta, no for moderation.**

- Meta is a topic question, and topic is what word-count classifiers do well. Support posts on this site share a narrow vocabulary: bug, button, page, theme, flag, feature, deploy, ZenMemes, "it would be nice if".
- There is enough labeled data. The live site has about 1,219 threads and 231 tagged `#meta`, roughly one in five. Labels come from authors' own `#meta` tags, from stored LLM `meta` scores, and from operator suppressions of wrong tags. The tag page does not say how many of the 231 are authored and how many are derived.
- Moderation should stay on the LLM. Its positives are rare and varied, posters can game word lists, and its exceptions (quotation, satire, rebuttal, contextual profanity) need reading, not counting.
- The saving is smaller than it looks while moderation stays on. Both rubrics share one call, so dropping `meta` removes one prompt bullet and one number, not a call. The bigger gains: the tag can appear at publish time, it works on instances with no provider key (a fresh clone, QDB, dev), it costs nothing to backfill, and each score can show the words behind it. Each further cheap category also stays out of the prompt.
- The main risk is software talk that is not support. Code reviews, Railspike notes and posts about other projects use the same words as bug reports. Naive Bayes will likely tag some of them. A held-out comparison against the LLM should measure this before any tag depends on it.

## Revised Direction - Revision 1

Production has a lot of labeled data, and the LLM cannot run there for now. This changes the recommendation:

- **No LLM fallback for now.** Option B's "LLM when unsure" has nothing to call. Bayes must decide `meta` alone, which is Option C.
- **Training and checking use only stored data.** Labels come from authored `#meta` tags, operator suppressions, and LLM `meta` scores already in the private score store. The agreement report compares against those stored scores and needs no live call. Option A's report still works.
- **Train only on human or LLM labels, never on Bayes's own scores.** While the LLM is off, new labels come only from authors' tags and operator suppressions. Without this rule the model would mostly be learning from its own guesses, and Option C's compounding risk would apply.
- **Backfill becomes the main gain.** Posts published since the LLM stopped have no `meta` score. A local pass can score all of them on the server in seconds, at no cost.
- **Training runs on production.** The private score store lives there, so the trainer is a `./v3` command run on the server. No scores or labels leave it.
- **Label counts gate the cycle.** The trainer first reports how many meta and not-meta labels it found. If stored LLM scores below 0.10 are too few, there aren't enough clear negatives, and Step 2 must decide whether untagged, unsuppressed posts count as weak negatives.
- **Gating is unchanged.** Local scores still go through the 0.90 threshold, the automatic-tagging flag and suppressions, and an operator can still remove a wrong tag.

Problem: The `meta` category depends on an LLM call and its provider, though a cheap local classifier could likely decide most posts.

Concepts touched: [Fastmod scoring](../../concepts/fastmod_scoring.md) (changed: a rubric score can come from a local source, not only the provider); [effective tags](../../concepts/effective_tags.md) (used unchanged in Options A and B: derived `#meta` still comes from a stored score at the threshold). Candidate new concept: **local rubric model**, a per-instance word-count model trained from the instance's own labels and rebuilt from them.

## Shared by every option

- Multinomial naive Bayes over subject and body words, Unicode-aware so Russian posts work. Pure PHP, no new dependency.
- Training labels, in priority order: an operator suppression (not meta), an authored `#meta` tag (meta), then a current-revision LLM `meta` score (meta at or above the threshold, not meta below 0.10, uncertain scores skipped).
- The model is derived state: private, per instance, rebuilt by one command from current labels. It never enters the content repository or the public export.
- Every local score records `source: bayes`, the model's build time and the few words that pushed it most, so the post detail can say why.
- Moderation stays on the LLM in every option.

## Option A - Bayes runs beside the LLM, for comparison only

**Estimate: 0.25**
Main driver: trainer, scorer, and an agreement report. Uncertainty: low.

The Bayes `meta` score is stored beside the LLM score and changes nothing public. A report shows agreement on held-out posts, at the current threshold, and lists the disagreements.

- Pro: answers "can we get away with it" with this site's numbers before anything depends on it.
- Pro: no change to tags, prompts or the provider call.
- Con: no savings yet; every post still sends the meta rubric.

## Option B - Bayes owns the meta rubric, LLM only when unsure

**Estimate: 0.40**
Main driver: Option A plus a per-rubric choice of source and a confidence band. Uncertainty: medium, in how wide the band must be for software-talk posts.

On the Fastmod page, the operator sets `meta` to "local, LLM when unsure". A confident Bayes score (below 0.10 or above 0.95, tunable) becomes the stored `meta` score. Otherwise `meta` joins the normal LLM call. With moderation off and Bayes confident, no call happens.

- Pro: `#meta` can appear at publish time, and works with no provider key.
- Pro: uncertain posts still get the LLM's reading.
- Pro: the same switch serves later cheap categories.
- Con: the rubric revision must record the score's source, so cached and transferred scores stay comparable.
- Con: a prompt that changes per post makes provider usage harder to compare.

## Option C - Bayes only, LLM out of meta

**Estimate: 0.30**
Main driver: Option A plus removing `meta` from the prompt. Uncertainty: medium.

The `meta` rubric is always scored locally. The LLM sees only moderation.

- Pro: simplest prompt and the smallest provider bill.
- Con: the software-talk mistakes go straight onto the public tag.
- Con: the model then trains mostly on its own past labels, so errors can compound unless operators suppress them.

## Recommendation

~~Choose Option B, shipped in two slices. Bayes fits meta, and the LLM fallback covers the posts where word counts mislead.~~

~~First vertical slice: Option A, the trainer, shadow scores on post detail and the agreement report. If it agrees with the LLM on most confident posts at the 0.90 threshold, the second slice adds the "local, LLM when unsure" switch. If agreement is poor, the cycle stops at Option A, having cost little.~~

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

**Revision 1:** Choose Option C now, shipped in two slices, with the training rule from Revised Direction. Leave Option B's fallback as a follow-up for when the LLM can run again.

First vertical slice: the trainer, local `meta` scores on post detail, and the agreement report against stored labels, all run on production with nothing public changing. The report lists every disagreement, so we can see the software-talk mistakes ourselves. Second slice, if agreement is good: local scores feed the derived `#meta` tag and backfill every unscored post. If agreement is poor, we stop after slice one.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-11T02:20:00Z, `railspike-effort` / `v3/fastmod_bayes_meta`, intake and Step 1 handoff. Scope v1: Option B in two slices, as recommended above. Starting revision `989f478`. Reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Before rating, a read-only pass read the Fastmod scorer, rubric, derived-tag and config code and counted tags on the live site. Pristine intake is missing. Intake and Step 1 projected total **0.40**. Option estimates 0.25, 0.40 and 0.30 are alternatives and do not add up. No implementation, measured labor or accepted outcome.

Revision 1, 2026-11-11T22:20:00Z: the maintainer said production has a lot of existing data and the LLM cannot run now. Scope v2: Option C in two slices (trainer, local scores and agreement report against stored labels; then derived tags and backfill), training on human and stored LLM labels only. Option B's fallback moves to a follow-up. Projected total 0.40 → **0.35** (new understanding: no fallback routing or per-post prompt in scope; label-count gate and backfill added). Option estimates unchanged and non-additive. Same R1/P2, single exposed Claude rater.

## Enhancement Requests

- "Local, LLM when unsure" (Option B's fallback). Disposition: follow-up cycle, once the LLM can run on production again.
- Other cheap categories (bug vs feature request, todo). Disposition: follow-up, once the switch exists.
- Retire the 0.90 threshold for Bayes-only scores in favor of a calibrated one. Disposition: Step 2 decides, using the agreement report.
