Cycle: `v3/offline_card_extra_line`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); [debug log](offline_card_extra_line_debug_log.md)
Effort study: `railspike-effort` / `v3/offline_card_extra_line` — [host ledger](../effort_estimation_trial.md#cycle-v3offline_card_extra_line); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.06**
Status: implemented and verified locally; next permitted action: push and PR (merge and deploy need the maintainer)

## Original request

In a Claude chat on 2026-11-14 the maintainer noticed that ZenMemes posts look different, "more meat on them", in offline (airplane) mode, and called it an accidental new feature to lean into. Investigation in the same chat found the cause: online, zenmemes uses the `list` board (`thread_row.php`, one ellipsized excerpt line), while the offline reader (`public/assets/offline_reader.js`) still draws full `thread-card`s with the whole first body line. On 2026-11-15 he chose: "we want to leave the online list the same as it is now, but for the offline view, we want to add one more line of body text for each card, please."

## Authorization

The 2026-11-15 instruction above, after two one-shots were proposed (this one and a separate airplane indicator). It authorizes a one-shot for the bounded outcome below, a pushed branch and a PR. Excluded: any change to the online board, the airplane indicator (its own one-shot), the offline thread page, merging and deploying.

## Outcome and work type

Application change. Normal entry: open zenmemes with the network off, so the service worker serves the offline reader for `/`, `/threads/` or a tag page. Outcome: each offline board card shows one more line of the root post's body than it does today.

- Today a card shows the body's first line (the read model's `body_preview`), or nothing when that line repeats the title.
- After: the card shows the next non-empty body line too. A card whose first line repeats the title now shows the second line; one whose first line differs shows the first and second lines, each as its own line.
- Image reference lines (`Image-v1: ...`) are skipped, as the online row's excerpt skips them. Threads with no further body text look the same as today.

Recovery: none needed; a missing root post in the snapshot falls back to today's card.

## Approach and risks

`snapshotPresentation.boardRows` already reads `threads`; extend its query with a `LEFT JOIN posts` on the root post to read the root body (the snapshot always stores the root post for an included thread). `appendThreadCards` builds the preview from the existing `body_preview` plus the next non-empty, non-image body line, rendered with a line break inside the existing `thread-card__preview` paragraph.

- Risk: Markdown posts. The snapshot has no `body_format`, so the extra line is raw text (e.g. a leading `- `), the same as the offline thread page shows it. The first line keeps using `body_preview`, which the server already plain-texted. Accepted and recorded.
- Risk: very long second lines. Today's first line is also unclamped; the extra line is clamped to the card with no new CSS only if needed after screenshots.
- Risk: snapshot schema. No schema change; the join reads existing columns, so cached snapshots and `applyUpdate` merges keep working.
- Compact density hides `.thread-card__preview` entirely; unchanged.

## Reuse decisions

- `public/assets/offline_reader.js`: `boardRows`, `appendThreadCards`, `threadTitle` (extend; used by the board, Liked view and tag result pages).
- Existing `thread-card__preview` element and styles; no new markup class or CSS planned.
- Image-line skip mirrors `templates/partials/thread_row.php`.
- Test: extend the existing browser harness pattern from `tests/browser/offline_board_toolbar.cjs` (snapshot fixture from `AbsentRetractionSnapshotTest::publicationFixture`) with a new focused check.

## Completion conditions

On the offline board (served from a real snapshot built by `PublicOfflineSnapshotBuilder`), a thread whose root body has two lines shows both; a thread whose first line repeats the title shows the second; a one-line thread is unchanged; no page errors or horizontal overflow at 390px and 1280px. Online pages unchanged.

## Concepts touched

Offline reader board (changed). No inventory change.

## Verification and recovery plan

- New browser check against a built snapshot, plus `node tests/browser/offline_board_toolbar.cjs` (shared consumer).
- `./v3 test --smoke` plus any PHP test covering offline snapshot presentation; full suite not run (maintainer runs it).
- Screenshots of the offline board at 390px and 1280px, checked by eye.
- Not checked: physical phones, real airplane mode on a device, production.

## Starting revisions and branch

Host `main` at `8314fae95ea2b33e16a2e6a1d6e2ca512ef52c4d`; branch `feat/offline_card_extra_line`.

## Planning commit

`ce52a065` (confirmed with Git).

## Study history

- 2026-11-15T19:45Z intake: whole-cycle projection **0.06**, scope v1 as above, R1, P2, single exposed rater Claude (claude-opus-5-5). Exposure: the rater investigated the offline reader in chat before the cycle existed; no pristine intake.
- 2026-11-15T19:50Z execution entry: reaffirmed **0.06** after the planning commit.

## Dated execution history

- 2026-11-15T19:45Z: no matching feedback item (searched `docs/feedback/items/` for offline; F59 and F78 are unrelated). Request came from chat; no item created.
- 2026-11-15T19:55Z: implemented in `public/assets/offline_reader.js`: `boardRows` joins the root post and keeps `nextLine` (only when `body_preview` is non-empty, so excluded threads stay blank); new `nextBodyLine` returns the second non-empty, non-image body line; `appendThreadCards` renders the shown lines with a `<br>` inside the existing `thread-card__preview` paragraph. No CSS was needed (screenshots at 390px and 1280px).
- 2026-11-15T19:55Z: added `tests/browser/offline_card_extra_line.cjs` (real snapshot plus a one-line and an image-line thread). It fails on the base commit at "first and second lines" and passes after the change.
- 2026-11-15T20:00Z: addition (pre-existing failure, fixed per the maintainer's standing rule): `tests/browser/offline_board_toolbar.cjs` failed at "word97 390px: Tags at the right" on the base commit too (also recorded as a baseline failure in `offline_compose`). Word97 deliberately pads the toolbar as a window frame (`theme-word97.css`), so the check now measures against the toolbar's content edge (padding plus border). Passes for all four themes at both widths.

## Study closeout

Intake 0.06; execution entry reaffirmed 0.06; **retrospective 0.07** (the pre-existing toolbar test fix was a small addition). Single exposed rater Claude (claude-opus-5-5), R1/P2. Maintainer acceptance pending; no accepted-count increment.

## Outcome

Delivered: offline board cards (board, Liked view and tag results, which share `appendThreadCards`) show the root post's second non-empty line under the first. A card whose first line repeats the title now shows the second line instead of nothing. Image reference lines are skipped. The online list board is unchanged. Limitation: the extra line of a Markdown post is raw text, as on the offline thread page.

## Verification

- `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/offline_card_extra_line.cjs` at 390px and 1280px: pass (fails on base, as expected).
- `node tests/browser/offline_board_toolbar.cjs` (Default, Word97, Chicago, Midnight at 390px and 1280px): pass after the test fix; failed on base before it.
- `./v3 test --smoke AbsentRetractionSnapshotTest OfflineOutboxStateTest OfflineNavigationWorkerTest OfflineSnapshotPresentationTest OfflineSnapshotThreadPresentationTest`: 492 run, 492 passed.
- Screenshots of the offline board at 390px and 1280px checked by eye.
- Full suite not run (maintainer runs it). Not checked: physical phones, real airplane mode, production. `playwright-core` was installed from the existing `package.json` devDependency (`npm install --no-save`), nothing committed.

## Final review

Diff limited to `offline_reader.js`, the two browser tests and this cycle's docs. No duplicated component: the existing preview paragraph is reused. No service worker cache bump: earlier `offline_reader.js` changes did not need one.

## Commit, merge and publication status

Planning commit `ce52a065`; implementation commit `88fe4296` (confirmed with Git); this record-only handoff commit follows. Branch pushed for a PR. Not merged or deployed.

*Claude (claude-opus-5-5), 2026-11-15*
