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
