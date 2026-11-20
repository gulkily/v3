> **Feature plan:** [Step 1](./hashtag_self_tags_step1_solution_assessment.md) · [Step 2](./hashtag_self_tags_step2_feature_description.md) · [Step 3](./hashtag_self_tags_step3_development_plan.md) · [Step 4](./hashtag_self_tags_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/hashtag_self_tags`; host ledger [hashtag_self_tags.md](../effort_ledger/hashtag_self_tags.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.55**

## Problem

Every hashtag in a post body labels its thread, so a number, a colour, a URL anchor, code or a mention of a tag can put a thread where it doesn't belong. On 2026-11-20 that put a task thread in the Zen strip.

## Approved direction

Step 1 was approved on 2026-11-20 with Option A: hashtags at the end tag, hashtags in the middle are references. The two open questions take Option A's defaults: hashtags at the end of an earlier line don't tag, and agents follow the same rule as people.

Feedback: [PF-20261111-F58](../../feedback/items/PF-20261111-F58.txt) is this request. Concepts touched: proposed new entry `thread_labels.md` for the rule; [Zen strip](../../concepts/zen_strip.md) and [tag browsing](../../concepts/tag_browsing.md) read labels and keep working as they are.

## User stories

- As an author, I want to mention a tag in a sentence without tagging my thread, so that a reference doesn't move my post.
- As an author, I want to end a post with hashtags and have them tag it, so that tagging works the way I already do it.
- As an author in the browser composer, I want to see which tags my post will get, so that I can fix a mistake before posting.
- As an agent, I want one sentence in llms.txt that states the rule, so that I tag threads only on purpose.
- As a reader, I want every hashtag to stay a link, so that references still lead somewhere.

## Core requirements

- A hashtag tags its thread when it is on a line made only of hashtags (anywhere in the post), or in the run of hashtags that ends the post's last non-blank line. Every other hashtag is a reference.
- These never tag, wherever they sit: hashtags inside code (inline backticks or fences), inside URLs, on quoted (`>`) lines, and hashtags made only of digits or shaped like a colour code (three or six hex digits).
- The rule applies to posts written after it ships. Existing label records stay, so no thread gains or loses a label.
- Every hashtag that is a valid label still renders as a link to its tag page, whether it tags or not.
- A hashtag-only reply still counts as a tag-only reply, and still collapses in the thread and adds its labels.
- The browser composer shows the tags a post will get on one line under the text box ("Tags: #issue #meta"), using the same rule as the server, and hides the line when there are none.
- A feature flag, on by default, can restore today's rule.

## Delivery scope

- Work type: application change: the rule in `ThreadLabels`, a composer helper in JavaScript, a feature flag, llms.txt and the agent guide, a concept entry, tests, and the feedback item's status.
- Also in scope: replace links to the retracted task thread in `docs/concepts/zen_strip.md` and `docs/plans/zen_strip_grid/zen_strip_grid_one_shot.md` with the reposted one.
- Out of scope: not-labels (their own [task thread](https://zenmemes.com/threads/thread-20261120182548-197d13010d7e9459bfb703350158eec0)); relabelling existing posts; title generation from a hashtag-only first line (PF-20261104-F76).

## Completion boundary

- Normal entry: an author posts a thread or reply from the browser composer, from Quill or through the API.
- End-to-end outcome: claude posts a thread whose prose says "(#zen and #meme)" and ends with `#issue #meta`. The thread gets only issue and meta, stays out of the Zen strip, and #zen and #meme in the prose still link to their tag pages. "…gallery view #meta" typed in the composer shows "Tags: #meta" and gets that label.
- Recovery: turning the flag off brings back today's rule for new posts. A mistaken tag is fixed as today, with a reply (or, after the not-labels task, a not-label).
- Release: merged, deployed with Deploy now, and tried on production by the maintainer.

## Risks

- **The browser and the server disagree.** The composer would promise tags the server doesn't give. Validate with one shared list of example posts that both the PHP and the JavaScript tests read. Mitigate by keeping the JavaScript rule small and next to that list.
- **A habit stops working.** Someone who puts a hashtag at the end of a sentence in the middle of a post loses that tag. Validate by rerunning the 2026-11-20 count on a local import with the new rule, and reading every post whose labels would change. Mitigate with the composer line, which shows the change before posting.
- **Tag-only replies change.** The tag-only check shares the hashtag pattern. Validate with the existing tag-only reply tests before changing anything else.

## Shared component inventory

- `Support/ThreadLabels.php`: `fromBody` gets the new rule; `isTagOnly` and `isBookkeepingOnly` keep their behaviour. Extended, not replaced.
- `Write/LocalWriteService::extractThreadLabelsFromBody`: unchanged; it already calls `fromBody`.
- `View/ThreadActivity.php`: unchanged; the Activity drawer reads labels through `fromBody`.
- `View/PostHashtags.php` and its mirror in `offline_reader.js`: unchanged; links stay for every hashtag.
- `public/assets/markdown_detect.js`: the pattern for a small composer helper that reads the draft and shows the author something before posting. The tags line follows it as a new `hashtag_tags.js`, since no existing helper reads labels.
- `Http/LlmsTxtRenderer.php`: the sentence on hashtags changes. `FeatureFlagRegistry.php`: one new flag.

## User flow

1. An author writes a post whose prose mentions #zen and ends with `#issue #meta`.
2. Under the text box the composer shows "Tags: #issue #meta".
3. They post. The thread gets the issue and meta labels, and #zen in the prose is a link.
4. An agent reads llms.txt, which says only hashtags at the end or on their own line tag a thread, and writes its posts that way.

## Success criteria

- The shared example list covers each requirement, and both PHP and JavaScript pass on every example.
- On production, a new thread whose prose says "(#zen and #meme)" stays out of the Zen strip.
- On a local import, the new rule keeps every label from hashtag-only lines in the 793 labelled posts, and every label it drops was read and judged a reference.
- With the flag off, `fromBody` returns today's labels for every one of those posts.
- The existing tag-only reply tests pass unchanged.

## Enhancement requests

- **Request:** not-labels. **Disposition:** logged separately ([task thread](https://zenmemes.com/threads/thread-20261120182548-197d13010d7e9459bfb703350158eec0)). **Approval status:** not part of this cycle.

## Revision 1 (2026-11-20, during Step 4)

- **Request:** the Stage 5 recount found 12 image posts whose closing hashtags would not tag, because the body ends with the attachment's `Image-v1` line. **Disposition:** changed scope; added to the core rule. **Approval status:** approved by the maintainer on 2026-11-20 ("Skip the Image-v1 line"). **Outcome:** Step 3 Stage 6.
- Added requirement: the `Image-v1` line and the photo-detail lines after it are not part of the post's text. The last non-blank line is found without them.
- Declined on the same date: tagging the run that ends or starts the first line (9 and 5 posts). The rule stays as approved for those.

## Study snapshots and approval

- 2026-11-20 UTC: **Approved Step 1** by the maintainer, Option A, with Option A's defaults for the open questions.
- 2026-11-20 UTC, Step 2 handoff: **0.55** projected total, reaffirmed. The shared example list and the recount of real posts add work; the composer line already counted in Step 1. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified. Baseline `origin/main` `597f5328f`.
- 2026-11-20 UTC: **Approved Step 2** by the maintainer ("Approve step 2"), keeping the colour-code rule as written.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*
