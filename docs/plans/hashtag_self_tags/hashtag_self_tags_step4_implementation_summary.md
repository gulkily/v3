> **Feature plan:** [Step 1](./hashtag_self_tags_step1_solution_assessment.md) · [Step 2](./hashtag_self_tags_step2_feature_description.md) · [Step 3](./hashtag_self_tags_step3_development_plan.md) · [Step 4](./hashtag_self_tags_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/hashtag_self_tags`; host ledger [hashtag_self_tags.md](../effort_ledger/hashtag_self_tags.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.55**

Branch: `hashtag-self-tags`, reused for this cycle as the maintainer asked. Planning commit: `72b56ea3`. Feedback: PF-20261111-F58.

## Step 4 entry

- 2026-11-20 UTC, Step 4 entry: **0.55** projected total, Step 3 value reaffirmed before implementation. Claude, single exposed rater. Scope v1, reference R1, baseline `597f5328f`.
- Original stage work, copied from Step 3: Stage 1 0.12, Stage 2 0.08, Stage 3 0.14, Stage 4 0.07, Stage 5 0.12. These are not summed.
- Baseline check: `./v3 test --smoke ThreadLabelsTest TagOnlyLeafFilterTest ThreadActivityTest` passed, 487 run, 487 passed.

## Enhancement requests

- **Request:** not-labels. **Disposition:** separate work ([task thread](https://zenmemes.com/threads/thread-20261120182548-197d13010d7e9459bfb703350158eec0)). **Approval status:** not part of this cycle. **Outcome:** pending in its own thread.

## Stage 1 - selfTags rule and shared examples

**Original estimate: 0.12**

**Retrospective stage work: 0.10**

- Changes:
  - `ThreadLabels::selfTags` adds the new rule. `fromBody`, `isTagOnly` and `isBookkeepingOnly` are unchanged.
  - The hashtag pattern and the protected text (code spans, Markdown links, entities, URLs) copy `View/PostHashtags.php`. So a hashtag tags only if it also renders as a tag link.
  - `tests/fixtures/hashtag_self_tags_examples.txt` holds 20 examples in `Key: value` form, one or more per requirement.
  - `ThreadLabelsTest` reads the file. It also checks that `selfTags` and `fromBody` agree on hashtag-only bodies.
- Verification:
  - Passed: `./v3 test --smoke ThreadLabelsTest TagOnlyLeafFilterTest ThreadActivityTest`, 489 run, 489 passed.
  - Passed: `git diff --check`.
  - Full suite not run.
- Notes:
  - A comma between hashtags ("#bug, #ux") makes the line prose, so only the trailing #ux tags. A full stop after the last hashtag ("Thanks #meta.") stops it tagging. Both are recorded as examples.
  - `PostHashtags` keeps its own copy of the pattern, so the two stay separate code. The comments in `ThreadLabels` name the file.
- Estimate details: original scope as Step 3; uncertainty about unclosed fences resolved (an unclosed fence hides the rest of the post); no revisions.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Stage 2 - Feature flag and write path

**Original estimate: 0.08**

**Retrospective stage work: 0.09**

- Changes:
  - `FeatureFlagRegistry` adds `FORUM_HASHTAG_SELF_TAGS_ENABLED`. It is on by default, site-mutable, in the AUTHORING group after Calendar feed, and released 2026-11-20.
  - `LocalWriteService::extractThreadLabelsFromBody` calls `selfTags` when the flag is on and `fromBody` when it is off. The local, prepared, signed and bundle write paths all call it.
  - `FeatureFlagEvaluatorTest` lists the flag in both orders it checks.
  - New `LocalWriteServiceSelfTagsTest`, registered in `tests/run.php`. It covers a local thread, a local reply, a signed prepared thread, and the flag turned off.
- Verification:
  - Passed: `./v3 test --smoke LocalWriteServiceSelfTagsTest FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest LlmsTxtRendererTest`, 503 run, 503 passed.
  - Full suite not run.
- Notes:
  - A test file runs only when `tests/run.php` lists it. The first run of the new file reported only the smoke set, which is how this showed up.
  - `docs/concepts/feature_flags.md` lists no flags, so it needs no change.
- Estimate details: original scope as Step 3; no revisions.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Stage 3 - Composer Tags line

**Original estimate: 0.14**

**Retrospective stage work: 0.14**

- Changes:
  - `templates/partials/composer.php` renders "Tags: …" under the text box from `selfTags($body)` while the flag is on. The line is hidden when there are no tags, so a prefilled draft is final on first paint.
  - New `public/assets/hashtag_tags.js` ports the rule and follows the `markdown_detect.js` binding pattern: input, Discard draft, and late-mounted composers on focus.
  - `TemplateRenderer` passes `hashtagSelfTagsEnabled` and loads the script only while the flag is on. `layout.php` loads it after `markdown_detect.js`. `compose.css` gives the line a small top margin.
  - New `HashtagTagsTest`, registered in `tests/run.php`. It runs the script in node over the shared examples and checks the binding.
  - `docs/concepts/shared_composer.md` gets a Tags line entry.
- Verification:
  - Passed: `./v3 test --smoke HashtagTagsTest ThreadLabelsTest MarkdownDetectTest`, 489 run, 489 passed. The PHP and JavaScript rules agree on all 20 examples.
  - Passed: on a local server, `/compose/thread` renders the hidden line and loads the script. `/compose/thread?body=…%23meta` renders "Tags: #meta" on the server. The reply form on `/threads/root-001` has the line.
  - Passed: headless Chrome, with a draft typed in, in Word97, Gloss and Bubblegum at 390px and 1280px. Each showed "Tags: #issue #meta" under the text box with no sideways overflow. Screenshots were kept outside the repository.
  - Inapplicable: navigation icons, since no destination changed.
  - Full suite not run.
- Notes:
  - On New post the line sits above the collapsed "Tags · #general" row for board tags. Both say "Tags", for board tags and thread labels. The UI copy rules favour fewer words, so no extra explanation was added; the maintainer may want to judge this on production.
- Estimate details: original scope as Step 3; no revisions.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*
