Cycle: `v3/prompt_card_like`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); no separate log unless something comes up
Effort study: `railspike-effort` / `v3/prompt_card_like` — [host ledger](../effort_estimation_trial.md#cycle-v3prompt_card_like); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.2**
Status: implemented and verified locally; pushed with a PR; merge and deploy need the maintainer

## Original request

Maintainer, in a Claude session on 2026-11-15, after the byline move ([prompt_card_meta_corner](../prompt_card_meta_corner/prompt_card_meta_corner_one_shot.md), PR #269, merged): "also, we need an appropriate equivalent for upvote button, if not an upvote button?" Claude proposed the site's existing Like, as a button on each prompt card in the bottom-right line next to the count. Maintainer: "go ahead please". Then: "also, let's sort the page by number of likes descending by default".

## Authorization

"go ahead please", 2026-11-15, for the proposed Like button as its own one-shot PR. Merging and deploying are not covered.

## Outcome and work type

Application change. Each prompt card on `/agent/` gets a Like button in its bottom-right line. Pressing it likes the prompt's thread, exactly as Like on the thread page does (same signed record, same account setup for a visitor without a key, same Outbox retry). The count beside it goes up at once. A prompt the viewer already liked on this device shows Liked.

Sort: no change needed. The default sort (Top) already orders by the thread's score, which is its like count (a flag subtracts 100); ties go newest first. Recorded here so the request has an answer.

Excluded: a separate upvote/downvote kind; un-liking from the card (retraction lives on the thread's records); Like on the Waiting list or the site's own prompts; showing Liked for a like made on another device (the page's HTML stays the same for every viewer, so this comes from the device's remembered reactions, as on cached thread pages).

## Approach and risks

- `templates/pages/agent.php`: the card `<section>` becomes a thread reactions root (`data-thread-reactions-root`, `data-thread-id`); the like count becomes the root's `data-role="thread-score"` node; a `thread-reaction-button` Like button and a `thread-reaction-feedback` line are added.
- `public/assets/thread_reactions.js`: one new score format, `likes` ("1 like", "2 likes", empty at zero), beside `labeled`, `bare` and `bare-ratio`, so the optimistic and confirmed count updates keep the card's wording.
- `AgentPageController`: load the same identity, outbox and reaction scripts the thread page loads.
- `public/assets/agent.css`: fit the button into the meta line.

Risks: themes restyle buttons heavily (Word97, Chicago, Bubblegum stretch or bevel them), so the button needs a look in each theme at phone and desktop widths. The page gains scripts it did not load before; check that copy and search still work.

## Reuse decisions

Reuse the thread page's Like wholesale: `thread_reactions.js` binding (`data-thread-reactions-root`, `data-action="apply-thread-tag"`, `data-tag="like"`, `data-applied-label="Liked"`), `/api/apply_thread_tag`, remembered-reaction hydration, Outbox. Button class `thread-reaction-button` as in `templates/partials/thread_root_card.php`. No new endpoint, record kind or script. The only new code path is the `likes` score format, needed because the existing formats print "Score: N" or "(N)".

## Completion conditions

On `/agent/`, a signed-in member presses Like on a prompt card: the button reads Liked, the count goes up by one, a signed thread-label record is written, and after reload the card shows Liked and the new count. A failed save restores the button and count and says why. Copy and search still work.

## Concepts touched

Prompt library card: changed (gains Like). Thread like / reaction: used unchanged. No inventory change.

## Verification and recovery plan

`./v3 test --smoke PromptLibraryTest PresentationProfileMatrixTest` plus the tests that read `thread_reactions.js`. A browser test, `tests/browser/prompt_card_like.mjs`, on the signed-reactions fixture with a prompt thread added: like from the card, check the record, the count and the reloaded state. Screenshots across themes at phone and desktop widths. Full suite not run (maintainer runs it).

Starting revision and branch: `c13e187ac3b783f6c877582b19c7bff29223ba3f`; `feat/prompt_card_like`
Planning commit: `fe3a852565297e1c3cc0501236f9481511f44604`

## Dated execution history

- 2026-11-16T04:25Z: no matching item in `docs/feedback/items/`.
- 2026-11-16T04:35Z: implemented as planned. The page loads the reaction scripts only when a prompt card is shown.
- 2026-11-16T04:40Z: discovery while writing the browser test: a newcomer's like does not move the thread score, so the card's count is approved members' likes, as it already was before this change. Correction to the plan's wording "its like count"; no code change.
- 2026-11-16T04:45Z: themes give the button their own button look (pill in Gloss, bevel in Word97 and Chicago, and so on), matching Copy on the same card. Left as is; a quieter text-link look would need a per-theme override and is the maintainer's call.

## Study history

- 2026-11-16T04:25Z intake: **0.2**, scope v1, R1, P2, single exposed rater Claude (claude-fable-5-2), rated after reading `thread_reactions.js`, `thread_root_card.php` and `AgentPageController` (no pristine intake).

- 2026-11-16T04:50Z closeout: retrospective **0.2**. Maintainer acceptance pending; no accepted-count increment.

## Verification

- Passed: `./v3 test --smoke PromptLibraryTest PresentationProfileMatrixTest ReactionOutboxTest BrowserSigningNormalizationTest QuoteCardDisplayNumberTest ReactionRecordFormatParityTest ForteBoardReaderTest`: 608/608.
- Passed: `node tests/browser/prompt_card_like.mjs` (local Chrome, signed-reactions fixture with root-001 labeled #prompt by an approved member): nothing shown at zero; a failed save restores the button and count and shows the error; Like writes the signed thread-label record, the button reads Liked and the count reads "1 like"; after reload the count and Liked remain; Copy still works.
- Looked at: the card in Gloss, Gloss Dark, Light, Dark, Word97, Chicago, Midnight, Bubblegum, Spearmint, Sticker and Engagify at 390px and 1280px (screenshots from the same script). The line stays bottom right with the button at its end in each.
- Not run: full suite (maintainer runs it); a visitor with no key pressing Like on this page (it goes through the same account setup as the thread page, not replayed here); the live site, which needs the deploy.

## Outcome

Delivered as scoped. Sort needed no change (see Outcome and work type). Next action: maintainer merges and deploys.

## Final review

Diff: `templates/pages/agent.php`, `public/assets/agent.css`, `public/assets/thread_reactions.js` (the `likes` score format only), `AgentPageController.php`, the browser test and its fixture, this record, the plans index and the ledger. No duplicate of the reaction code. No debug findings beyond the two notes in the history.

Commit/merge/publication status: completion commit pending at time of writing; pushed with a PR; not merged or deployed.

Attribution: Claude (claude-fable-5-2), 2026-11-16 UTC.
