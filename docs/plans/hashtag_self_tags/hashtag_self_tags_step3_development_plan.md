> **Feature plan:** [Step 1](./hashtag_self_tags_step1_solution_assessment.md) · [Step 2](./hashtag_self_tags_step2_feature_description.md) · [Step 3](./hashtag_self_tags_step3_development_plan.md) · [Step 4](./hashtag_self_tags_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/hashtag_self_tags`; host ledger [hashtag_self_tags.md](../effort_ledger/hashtag_self_tags.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.55**

Feedback: [PF-20261111-F58](../../feedback/items/PF-20261111-F58.txt). Concepts touched: [Zen strip](../../concepts/zen_strip.md) (changed: task link only), [tag browsing](../../concepts/tag_browsing.md) (used unchanged), [feature flags](../../concepts/feature_flags.md) (used unchanged), `thread_labels.md` (created).

## Completion Contract

- Normal entry: an author posts a thread or reply from the browser composer, from Quill, or through the API. All four write paths in `LocalWriteService` go through `extractThreadLabelsFromBody`, so one switch there covers them.
- End-to-end outcome: claude posts a thread whose prose says "(#zen and #meme)" and ends with `#issue #meta`. The thread gets only issue and meta and stays out of the Zen strip. #zen and #meme still link to their tag pages. When "…gallery view #meta" is typed in the composer, it shows "Tags: #meta", and the thread gets that label.
- Recovery: turning `FORUM_HASHTAG_SELF_TAGS_ENABLED` off brings back today's rule for new posts and hides the Tags line. A mistaken tag is fixed with a reply, as today.
- Deployment: no migration and no read-model change. Label records are written when a post is saved, so existing threads keep their labels.
- Release: merged, deployed with Deploy now, and tried on production by the maintainer.

## Key Risks

- **High risk:** **A habit stops working.** An author who ends a sentence in the middle of a post with a hashtag loses that tag. Validate in Stage 5 by running both rules over a local import and reading every dropped label. Mitigate with the Tags line, which shows the change before posting.
- **The browser and the server disagree.** The composer would promise tags that the server doesn't give. Validate in Stage 1 with one example file that both the PHP and the JavaScript tests read. Mitigate by keeping the JavaScript rule small and checked against that file.
- **Tag-only replies change.** `isTagOnly` and `ThreadActivity` read `fromBody`. Validate by running `ThreadLabelsTest`, `TagOnlyLeafFilterTest` and `ThreadActivityTest` before any edit. Mitigate by leaving `fromBody` as today's rule and adding the new rule as a separate method, `selfTags`. This refines the Step 2 inventory, which put the new rule inside `fromBody`; behaviour and scope are the same.
- **A reply of only `#2024` still collapses as tag-only** but adds no label under the new rule. Its Activity drawer entry would name a label the thread lacks. Accept this for this cycle; note it in the concept entry.
- **The recount can't run here.** This workspace may not reach zenmemes.com for `import-instance`. If so, Stage 5 hands the maintainer a script and leaves that criterion pending.

## Stage 1

**Estimate: 0.12**

- Goal: the new rule exists in PHP and passes one shared list of examples.
- Dependencies: none. Run the three tag-only test files first and record that they pass.
- Expected changes: add `ThreadLabels::selfTags(string $body): list<string>`. It returns labels from hashtag-only lines and from the run of hashtags at the end of the last non-blank line. It skips code, URLs, quoted lines, all-digit tags and three- or six-hex-digit tags. `fromBody` stays as it is. Add `tests/fixtures/hashtag_self_tags_examples.txt` in `Key: value` form (name, expected tags, a blank line, then the body), one example per requirement. Extend `ThreadLabelsTest` to read it.
- Verification approach: `./v3 test --smoke ThreadLabelsTest TagOnlyLeafFilterTest ThreadActivityTest`.
- Estimate details: driver: code-span and URL skipping inside a line; uncertainty: Markdown fences that never close.
- Risks or open questions:
  - Impact: a loose rule tags hashtags in code or URLs.
  - Early warning / validation: examples for each case in the shared file.
  - Mitigation: reuse the URL and code handling that `PostHashtags` already applies to links.
- Canonical components/API contracts touched: `Support/ThreadLabels.php` (extended); `View/PostHashtags.php` (read for reuse, unchanged).

## Stage 2

**Estimate: 0.08**

- Goal: new posts get labels by the new rule, and the flag can bring back today's rule.
- Dependencies: Stage 1.
- Expected changes: add `HASHTAG_SELF_TAGS_ENABLED` (`FORUM_HASHTAG_SELF_TAGS_ENABLED`, on by default) to `FeatureFlagRegistry` in the AUTHORING group. `extractThreadLabelsFromBody` calls `selfTags` when the flag is on and `fromBody` when it is off.
- Verification approach: a write test posts the Contract body through the local write path and the signed API path. With the flag on, the label record holds issue and meta. With it off, it holds all four. Run `./v3 test --smoke` plus the new test, `FeatureFlagsBehaviorTest` and `LlmsTxtRendererTest`.
- Estimate details: driver: test setup for two write paths; uncertainty: low.
- Risks or open questions: none beyond the Key Risks.
- Canonical components/API contracts touched: `Write/LocalWriteService.php` (one function changed); `Support/FeatureFlags/FeatureFlagRegistry.php` (one flag added).

## Stage 3

**Estimate: 0.14**

- Goal: the browser composer shows "Tags: …" under the text box, using the server's rule.
- Dependencies: Stage 1.
- Expected changes: `templates/partials/composer.php` renders the Tags line from `selfTags($body)` when the flag is on. It renders the line hidden when the body has no tags, so a prefilled draft looks final on first paint. A new `public/assets/hashtag_tags.js` follows the `markdown_detect.js` pattern and updates the line on input and when the fields are cleared. `TemplateRenderer` and `layout.php` load it the same way they load `markdown_detect.js`.
- Verification approach: a new `HashtagTagsTest` runs the script in node over the shared example file, as `MarkdownDetectTest` does. Check the composer by hand in Word97, Gloss and Bubblegum at phone and desktop widths, following CONTRIBUTING's UI checks.
- Estimate details: driver: the JavaScript port of Stage 1's rule; uncertainty: composer layouts across themes.
- Risks or open questions:
  - Impact: the line clutters the composer.
  - Early warning / validation: the theme check.
  - Mitigation: one plain line, no label beyond "Tags:", hidden when empty.
- Canonical components/API contracts touched: `templates/partials/composer.php` (extended); `public/assets/markdown_detect.js` (pattern, unchanged); `View/TemplateRenderer.php` (one asset path).

## Stage 4

**Estimate: 0.07**

- Goal: agents and contributors can read the rule, and the Zen strip docs point at the reposted task.
- Dependencies: Stage 2.
- Expected changes: change the hashtag sentence in `Http/LlmsTxtRenderer.php` and its test. Change the matching text in `docs/reference/agent_api.md` and `docs/agents/claude_zenmemes_skill.md`. Create `docs/concepts/thread_labels.md`. In `docs/concepts/zen_strip.md` and `docs/plans/zen_strip_grid/zen_strip_grid_one_shot.md`, replace the retracted thread link with `thread-20261120182535-0db81c46de8339600736c7ae1fec6e37`.
- Verification approach: `./v3 test --smoke LlmsTxtRendererTest`; check every link in the touched files; `git diff --check`.
- Estimate details: driver: wording; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `Http/LlmsTxtRenderer.php` (one sentence).

## Stage 5

**Estimate: 0.12**

- Goal: show on real posts that the rule keeps the tags it should, and record the result.
- Dependencies: Stages 2–4.
- Revised 2026-11-20 (Revision 1): Stage 5 now covers the recount and its record. The end-to-end run and the feedback item move to Stage 6.
- Expected changes: a script kept outside the repository runs `fromBody` and `selfTags` over every labelled post in a local import. Read every dropped label and record the counts in the Step 4 summary. Run the Contract outcome against a local server: compose, API post, Zen strip, tag links and the flag-off check. Update PF-20261111-F58 to `blocked` until production acceptance, with Evidence and a dated note, and add the `Feedback:` trailer.
- Verification approach: the counts match the 2026-11-20 figures for hashtag-only lines (the 616 posts keep every label except digit and colour tags), and each dropped label reads as a reference. Report the full suite as not run.
- Estimate details: driver: reading the 177 posts with prose hashtags; uncertainty: whether the import runs here.
- Risks or open questions:
  - Impact: without the recount, one success criterion stays unproven.
  - Early warning / validation: try `import-instance` at the start of the stage.
  - Mitigation: hand the script to the maintainer and mark the criterion pending.
- Canonical components/API contracts touched: `docs/feedback/items/PF-20261111-F58.txt`.

## Stage 6

**Estimate: 0.06**

- Goal: closing hashtags tag an image post, and the cycle closes with the outcome checked end to end.
- Dependencies: Stage 5. Added by Revision 1 of Step 2.
- Expected changes: `selfTags` reads the body through `ImageReference::withoutReference` first. `hashtag_tags.js` drops an `Image-v1` line and its photo details the same way. Add an image example to the shared file. Rerun the recount. Run the Contract outcome against a local server, and update PF-20261111-F58.
- Verification approach: `ThreadLabelsTest`, `HashtagTagsTest` and `LocalWriteServiceSelfTagsTest`. The recount keeps the 12 image posts' labels.
- Estimate details: driver: the JavaScript copy of the image-line rule; uncertainty: low.
- Risks or open questions: none beyond the Key Risks.
- Canonical components/API contracts touched: `Images/ImageReference.php` (`withoutReference`, reused unchanged).

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5 (Stage 6 and Stage 5 revision)*

## Study snapshot and approval

- 2026-11-20 UTC: **Approved Step 2** by the maintainer ("Approve step 2"), with the colour-code rule as written.
- 2026-11-20 UTC, Step 3 handoff: **0.55** projected total, reaffirmed. Planning confirmed that one write function covers every path and labels are stored records, so no rebuild work. The second rule method and the server-rendered Tags line are new understanding, not new scope. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified. Baseline `origin/main` `597f5328f`. Stage estimates are not summed.
- 2026-11-20 UTC: **Approved Step 3** by the maintainer ("approved step 3").
- 2026-11-20 UTC, Revision 1: the maintainer approved skipping the `Image-v1` line and declined the two first-line additions. Stage 6 added. Projected total revised to **0.62**: new understanding from the recount, plus a small changed scope. Claude, single exposed rater; original 0.55 retained.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*
