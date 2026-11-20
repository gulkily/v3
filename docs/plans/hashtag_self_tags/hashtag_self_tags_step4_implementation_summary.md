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

## Stage 4 - llms.txt, agent docs, concept entry, Zen strip links

**Original estimate: 0.07**

**Retrospective stage work: 0.07**

- Changes:
  - `LlmsTxtRenderer` now says a hashtag labels a thread only on a line of hashtags or at the end of the last line, and that hashtags in sentences are links. `LlmsTxtRendererTest` checks both phrases.
  - `docs/reference/agent_api.md` and `docs/agents/claude_zenmemes_skill.md` state the same rule.
  - New concept `docs/concepts/thread_labels.md`. It notes the `#2024` tag-only reply edge from the Key Risks.
  - `docs/concepts/zen_strip.md` and `docs/plans/zen_strip_grid/zen_strip_grid_one_shot.md` link the reposted gallery task, `thread-20261120182535-0db81c46de8339600736c7ae1fec6e37`. The Step 1 Original Query keeps the old link, since that text is preserved as written.
- Verification:
  - Passed: `./v3 test --smoke LlmsTxtRendererTest`, 494 run, 494 passed. Rerun after the new assertion: `LlmsTxtRendererTest`, 15 run, 15 passed.
  - Passed: the local `/llms.txt` serves the new sentence.
  - Passed: every relative link in the touched concept and reference files resolves; `git diff --check`.
  - Passed, as an affected-consumer check after Stage 3: `./v3 test WriteApiSmokeTest SingleRequestPostingTest PromptLibraryTest BoardListTest PostSignalsTemplateTest LocalAppSmokeTest ActivityProjectionTest OfflineSnapshotThreadPresentationTest`, 441 run, 441 passed. These post hashtags and check labels; all put hashtags on their own lines.
  - Full suite not run.
- Notes:
  - The llms.txt sentence describes the default rule. It does not change when a site turns the flag off.
- Estimate details: original scope as Step 3; no revisions.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*

## Stage 5 - Recount on real posts

**Original estimate: 0.12**

**Revised estimate: 0.10**: scope narrowed by Revision 1. The end-to-end run and the feedback item moved to Stage 6.

**Retrospective stage work: 0.12**

- Changes:
  - None to the application. Two scripts outside the repository did the work: `recount.php` compares `fromBody` and `selfTags` over every live post, and `variants.php` scores possible additions to the rule.
- Verification:
  - Passed: `./v3 import-instance zenmemes.com --with-approvals` into local state, 9,320 records imported. `approval suggest-roots` was not run, since approvals don't change labels.
  - Recount over 4,171 live posts: 801 have labels under today's rule. 624 have hashtags only on hashtag-only lines, 107 only in prose, and 70 both. That matches the 2026-11-20 count of 616, 107 and 70, plus 8 newer posts.
  - Under the approved rule, 686 posts keep the same labels, 115 lose at least one, and none gain one.
  - Every dropped label was read in its line. Most are references: PR and issue numbers, "#1 hack", colours, URL anchors, and mentions inside quotes or brackets.
  - **Failed against the Step 2 criterion**, before Revision 1: 26 posts lost real tags. 12 were image posts ending "… #meta", because the body ends with the attachment's `Image-v1` line. 9 tagged the end of the first line and then wrote details. 5 started the first line with hashtags. All 26 were checked as real tags.
  - The 3 posts with hashtags only on hashtag-only lines that still lose a label are `#1111`, an all-digit tag excluded by the approved rule.
- Notes:
  - **Enhancement request (Revision 1):** add the three recoveries. **Disposition:** changed scope. **Approval status:** maintainer approved skipping the `Image-v1` line and declined the two first-line additions, 2026-11-20. **Outcome:** Step 2 Revision 1, Step 3 Stage 6.
  - Without the `Image-v1` fix the composer would show "Tags: #meta" for an image post that the server gives no label. The composer's text box never contains the image line.
  - After Revision 1, 103 posts lose a label: the 89 references and other approved exclusions, plus the 14 first-line posts the maintainer chose to let go.
- Estimate details: revision recorded above; the recount itself ran here, so the "can't run here" risk did not happen.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*
