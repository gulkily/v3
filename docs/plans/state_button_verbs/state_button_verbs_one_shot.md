# State button verbs

Cycle: `v3/state_button_verbs`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_ledger/state_button_verbs.md). Standing P2 enrollment. Split off from `v3/editable_tagsets` (Step 1) as separate work; ships before that cycle's Step 2.

Extended estimates: disabled (one approach, no alternatives or stages)

**Cycle estimate: 0.15 (intake after source inspection; no pristine intake)**

Status: plan written; next permitted action: planning commit, then implementation.

## Original request

Maintainer, 2026-11-22, during `editable_tagsets` Step 1 review: "we at some point want to solve the tense inconsistencies with e.g. close/closed, should we roll that into here or do it separately?" After Claude suggested a separate one-shot that changes only the button wording: "please do it as one-shot first, approved step 1".

## Authorization

Maintainer, 2026-11-22: "please do it as one-shot first". This covers the label-only fix Claude proposed in the same exchange, through an open PR. It does not cover renaming the stored tags, merging, deploying or production access.

## Outcome and work type

Application change.

Today the state buttons on a reply or opening post in an `#issue` thread mix tenses: Claim, Resolved, Closed, then Unclaim or Reopen. After this change every state button is a verb: Claim, Resolve, Close, Unclaim, Reopen.

- The stored tags stay `claimed`, `resolved`, `closed` and `open`. Existing marks, `resources/tagsets.txt`, the API and the read model do not change.
- The state words stay past tense where they describe a state: the "Resolved" fold label, "Claimed by NAME", the confirmation "Resolved." after a tap, and the Tools page.
- While a tap is saving, the button shows the past tense ("Resolved"), as Like shows "Liked".

Exclusions: renaming tags; labels for tags other than the four state tags; per-tag labels in the tagset format (left to a later cycle, noted in `editable_tagsets`).

## Approach and risks

`PostStateView::buttonLabel()` returns the verb. A new `PostStateView::appliedLabel()` returns the past tense for `data-applied-label`. `post_unlocked_tags.php` uses each for its own attribute. `thread_reactions.js` already reads the button text and `data-applied-label` separately, so it needs no logic change.

- Risk: the confirmation after a tap is built from `data-applied-label`. If that turned into a verb, the message would read "Resolve.". Mitigation: keep `data-applied-label` past tense; for `open` it stays Unclaim or Reopen, because `stateMarkMessage()` picks "Unclaimed." or "Reopened." from that value. Early check: `ReactionSendOrderTest` and `TagUnlocksTest`.
- Risk: after a tap, a state button might keep the pending past-tense text when it reappears later (for example Resolve after Reopen). Mitigation: state buttons restore their captured text after the save (`restorePostReactionState`); the browser test checks the label after Reopen.

## Reuse decisions

- `src/ForumRewrite/Support/PostStateView.php`: extend; it already owns the state wording that `thread_reactions.js` mirrors.
- `templates/partials/post_unlocked_tags.php`: reuse; only the applied-label source changes.
- `public/assets/thread_reactions.js`: reuse unchanged; update its mirror comment if needed. The `ReactionSendOrderTest` fake already uses Close/Closed and Resolve/Resolved, the target split.
- No new component.

## Completion conditions

- Normal entry: an approved member opens a reply in an `#issue` thread and sees Claim, Resolve and Close; after Resolve, the reply folds with "Resolved" and offers Reopen; after Reopen, Resolve and Close are back with verb labels.
- The opening post shows the same verbs.
- Confirmation messages are unchanged ("Resolved.", "Closed.", "Reopened.", "Unclaimed.").
- Release: PR open; maintainer merges and deploys. Production replay is pending until then.

## Concepts touched

- [tagset](../../concepts/tagset.md): changed wording only; the scenario names the buttons Resolve and Close.

## Verification and recovery plan

- `./v3 test --smoke TagUnlocksTest ReactionSendOrderTest TagsetsTest`, with `TagUnlocksTest` asserting the new verbs and the unchanged past-tense fold label.
- `node tests/browser/tag_unlocks.mjs` if the browser harness runs in this environment, otherwise reported blocked.
- Themes: text-only change in an existing button, so no layout check beyond the browser test's viewport check.
- Recovery: none needed; a reload shows the server-rendered labels.
- Full suite not run (maintainer runs it).

## Starting revisions and branch

Branch `state-button-verbs` from `origin/main` at `e6fd4b0`.

Planning commit: pending.

## Dated execution history

- 2026-11-22: record written.

## Study history

- 2026-11-22T22:40Z intake **0.15**, scope v1, R1, P2. Single exposed Claude rater; `PostStateView`, the button template, `thread_reactions.js` and their tests were read before this estimate. Not hours.

## Enhancement requests

None yet.

*— Claude · 2026-11-22 UTC · Model: claude-opus-5-5*
