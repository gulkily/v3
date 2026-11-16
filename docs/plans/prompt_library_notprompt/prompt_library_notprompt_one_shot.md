Cycle: `v3/prompt_library_notprompt`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); no separate log (nothing beyond the note under Final review)
Effort study: `railspike-effort` / `v3/prompt_library_notprompt` — [host ledger](../effort_estimation_trial.md#cycle-v3prompt_library_notprompt); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.1**
Status: implemented and verified locally; pushed with a PR; merge and deploy need the maintainer

## Original request

Maintainer, in a Claude session on 2026-11-15: "There are some non-prompt things on the prompts page. Can you please look into this and see how to most easily remove them?" After the findings and a proposed `#notprompt` tag: "I can't run any commands right now, so please help me fix it."

## Authorization

The second message, 2026-11-15, read as the go-ahead for the proposed fix carried through to a pushed branch and an open PR, and for "claude" to tag the two threads once it is deployed. Merging and deploying are not covered.

## Findings

Two threads were on `/agent/` that are not prompts. Both really carry the `prompt` tag:

- `thread-20261111144438-7d47edd5` ("What ZenMemes does that other spaces don't"): a reply by "claude" says "Members share prompts as #prompt threads". A post's hashtags become thread labels, and "claude" is approved, so the thread counted as member-tagged.
- `thread-20261115105316-17bcdbf5` (a screenshot post by the maintainer): no labels; the tag is the post's own board tag, which the "Share a prompt" composer link fills in as a hidden field. An approved author lists at once.

Neither can be undone from the browser today: hashtag-owned labels are not retractable yet, and a board tag is part of the post.

## Outcome and work type

Application change, read-model query only. An approved member's `#notprompt` label takes a thread out of the library and out of the Waiting list. The label must still be active on the thread, so once label retraction exists, retracting `#notprompt` puts the thread back. `notprompt` never shows as a category. A newcomer's `#notprompt` does nothing.

This adds a record instead of removing one, in line with the rule that a later action must not override an earlier one.

Excluded: stopping hashtags in prose from labeling a thread (site-wide rule, needs its own discussion); a visible "posting as a prompt" notice in the composer; any button for `#notprompt` (a reply with the hashtag is enough for now).

## Reuse decisions

Same `viewer_tags` + approved `profiles` test the library already uses for `#prompt`, mirrored for `notprompt`; same `ThreadKeysetPage::listJson()` check on active labels. No schema change.

## Verification

- Passed: `./v3 test --smoke PromptLibraryTest PresentationProfileMatrixTest`: 480/480. New test covers an approved member's `#notprompt` on an approved prompt and on a waiting one, a newcomer's `#notprompt`, and a `#notprompt` whose label is no longer active.
- Not run: full suite (maintainer runs it); the live site, which needs the deploy and then the two `#notprompt` replies.

Starting revision and branch: `c54c3375d33f388b6b76ce34aabfb47843040d81`; `fix/prompt_library_notprompt`

## Dated execution history

- 2026-11-16T01:36Z: no matching item in `docs/feedback/items/`.
- 2026-11-16T01:45Z: pushed; PR #267 open. Not merged or deployed.

## Study history

- 2026-11-16T01:36Z intake: **0.1**, scope v1, R1, P2, single exposed rater Claude (claude-fable-5-2), rated after reading `PromptLibrary` and its test. Retrospective **0.1**. Maintainer acceptance pending; no accepted-count increment.

## Final review

Diff is `PromptLibrary.php` and its test. Debug note: the prompt_library record listed "label by approved member" as an approval path without asking how a label gets made; a hashtag in passing is one way, which is how this happened. Worth a line in the implementation standards about checking every way a record a feature trusts can come to exist.

Attribution: Claude (claude-fable-5-2), 2026-11-16 UTC.
