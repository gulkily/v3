> **Feature plan:** [Step 1](./hashtag_self_tags_step1_solution_assessment.md) · [Step 2](./hashtag_self_tags_step2_feature_description.md) · [Step 3](./hashtag_self_tags_step3_development_plan.md) · [Step 4](./hashtag_self_tags_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/hashtag_self_tags`; host ledger [hashtag_self_tags.md](../effort_ledger/hashtag_self_tags.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

**Cycle estimate: 0.55**

## Original Query

our todo should not have shown up there. what's the best way to fix this? https://zenmemes.com/threads/thread-20261120173657-2cb6e813e62a6457e4cde6eccd4c5828

how do we differentiate between hashtags used as references to something vs self-tagging hashtags?

yes, please write this up, and add a separate task for implementing special #notx tags

## Understood Intent

A task thread from claude landed in the Zen strip because its prose named the strip's two tags with hashtags, and every hashtag in a body labels the thread. We want a rule that tells a hashtag meant as a tag from one that only mentions or points at something. Opt-out not-labels are logged separately as their own task.

## Problem

Every hashtag in a post body becomes a thread label, except in quoted lines, so numbers, URL anchors, colour codes, code and mentions of a tag all label the thread by accident.

Evidence, from a local import of zenmemes.com on 2026-11-20: 793 live posts produced labels. 616 had hashtags only on hashtag-only lines. 107 had them only inside sentences, and 70 had both. The in-sentence ones were mostly PR and list numbers (#329, #2), colours (#008080), URL anchors (…/guidelines/#design), code (`#prompt`) and mentions ("follow #issue, not #meta"). A few were deliberate tags at the end of a sentence ("please add margin support to gallery view #meta").

Feedback: [PF-20261111-F58](../../feedback/items/PF-20261111-F58.txt) (mentioning a hashtag labels the post) is this request. Related: [PF-20261104-F76](../../feedback/items/PF-20261104-F76.txt) (hashtag-only first line and titles).

Concepts touched: thread labels (`Support/ThreadLabels.php`, no concept entry yet); [tag browsing](../../concepts/tag_browsing.md) and [Zen strip](../../concepts/zen_strip.md) read labels and are unchanged.

## Shared by every option

- Labels are written as separate records when a post is saved, so the new rule only affects posts written after it ships. No existing thread gains or loses a label, and no past post changes meaning.
- Hashtags that don't tag still link to their tag page, as today.
- The rule lives in one place, `ThreadLabels::fromBody`, which the write side, the Activity drawer and the tag-only reply check all use.
- The composer shows the tags a post will get, as a short line under the text box, so the author sees the rule at work before posting. The browser needs the same rule as the server; keeping the two in step is a risk under every option.
- A feature flag can restore today's rule.
- llms.txt and the agent posting guide describe the new rule in one sentence.

## Option A — Tags at the end, references in the middle

**Estimate: 0.55**
Main driver: the rule, its tests on real-post examples, and the composer preview in JavaScript. Uncertainty: medium (agreeing the edge cases).

A hashtag tags the thread when it is on a hashtag-only line (anywhere in the post) or in the run of hashtags that ends the last line. A hashtag inside a sentence is a reference. Hashtags in code, inside URLs, in quoted lines, and all-digit or colour-like ones never tag.

- Pro: covers every deliberate case seen in the data, including "…gallery view #meta".
- Pro: matches how people already write: 616 of 793 posts tag on their own line.
- Con: a rule of position takes a sentence to explain; the composer preview carries most of that.

## Option B — Keep every hashtag, minus obvious non-tags

**Estimate: 0.35**
Main driver: exclusions in one function, plus the preview. Uncertainty: low.

Every hashtag still tags, except in code, inside URLs, in quoted lines, and all-digit or colour-like ones.

- Pro: the smallest change; nobody's habits change.
- Con: doesn't fix the case that started this. "(#zen and #meme)" in a sentence still tags.

## Option C — Only hashtag-only lines tag

**Estimate: 0.45**
Main driver: a simpler rule, but the same preview and guide changes. Uncertainty: low.

Only a line made entirely of hashtags tags the thread (and a hashtag-only reply, as today). Everything else is a reference.

- Pro: the easiest rule to state and to predict.
- Con: "please add margin support to gallery view #meta" no longer tags, which breaks a habit some members use.

## Recommendation

Option A. It keeps every deliberate tag seen in the data, drops every accidental one, and the composer preview makes the rule visible.

Vertical slice: claude posts a thread whose prose says "(#zen and #meme)" and ends with a `#issue #meta` line. The thread gets only issue and meta, stays out of the Zen strip, and both mid-sentence hashtags still link to their tag pages. A member typing "…gallery view #meta" in the browser composer sees "Tags: #meta" under the text box and gets that label.

## Open questions for the maintainer

- Should hashtags at the end of a line other than the last also tag ("#meta please fix the gallery" leads with one)? Option A says no; a hashtag-only first line still tags.
- Do agents get the same rule as people? Option A says yes, with no exception.

## Enhancement requests

- **Request:** a separate task for not-labels (a `not`-prefixed label cancels a tag). **Disposition:** logged as its own task, not part of this cycle: [Not-labels thread](https://zenmemes.com/threads/thread-20261120182548-197d13010d7e9459bfb703350158eec0).
- **Request:** fix the task thread that landed in the strip. **Disposition:** done outside this cycle. Claude retracted it on 2026-11-20 and reposted it with plain words: [gallery task](https://zenmemes.com/threads/thread-20261120182535-0db81c46de8339600736c7ae1fec6e37). **Follow-up:** `docs/concepts/zen_strip.md` and the zen_strip_grid record still link to the retracted thread; update them in this cycle's implementation.

## Study snapshots and approval

- 2026-11-20 UTC, intake after code inspection (`ThreadLabels.php`, `LocalWriteService.php`, `PostHashtags.php`) and the hashtag count on a local import: 0.55 projected total for the recommended scope. No pristine intake. Claude, single exposed rater; R1 hashes not re-verified; baseline `origin/main` `597f5328f`.
- Step 1 handoff: 0.55 for Option A (alternatives B 0.35, C 0.45; not summed). No implementation. Awaiting `Approved Step 1`.

*— Claude · 2026-11-20 UTC · Model: claude-opus-5-5*
