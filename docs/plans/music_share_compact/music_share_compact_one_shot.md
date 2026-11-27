# Music share box like the Board's

Cycle v3/music_share_compact · One-shot · [Plans](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/music_share_compact`; host ledger [music_share_compact.md](../effort_ledger/music_share_compact.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.10**

Status: implemented, PR open; next permitted action: maintainer merge and a look on the phone.

## Original request

"it would be nice to style the submission form some more, compare to board page. also, the page only shows one track on prod?" (Ilya, 2026-11-26, after merging PR #438, [mitrapclub_music](../mitrapclub_music/mitrapclub_music_step4_implementation_summary.md), with screenshots of /music/ and the Board on mitrapclub.com).

## Authorization

A bounded one-shot through an open PR: the share box on /music/ and the stylesheets the page loads. Not authorized: what the page lists, the row layout, or posting behaviour.

## Problem

On production the share box is the full New post form: a tall textarea, a full-width Post button, "…", Title, an open Tags drawer, Event and Options, all unstyled. The Board starts as one styled line that grows on focus. Cause: `music.php` has no entry in `TemplateRenderer`'s template stylesheet map, so neither `compose.css` nor `thread-list.css` loads on /music/; and Stage 4 used the full form.

The "only one track" part is content, not code: tagging the existing tracks was Stage 7 of mitrapclub_music, pending the maintainer's go-ahead. He chose "Tag all 12" on 2026-11-26 and the replies were posted as "claude" the same day; /music/ now lists 12 production tracks.

## Outcome and work type

UI change, phone first.

- /music/ loads `thread-list.css` and `compose.css`, as the Board does.
- The share box is the Board's composer: the compact form in an `inline-reply-composer compact-thread-compose` card, one line until focused, then Post, "…" and Options. The tag `music` rides along as a hidden field; the placeholder stays "Paste a link to a track"; Post still returns to /music/ with the draft scope `music`.

## Risks

- **No visible Title.** The compact form has none, as on the Board. Mitigation: titles are fetched for every supported provider; "…" still opens the full form.
- **The Board's composer behaves differently away from the Board.** Mitigation: `music_share.mjs` checks one line at load, growth on input, and a post with and without JavaScript.

## Reuse decisions

- The Board's composer block from `templates/pages/board_list.php` and its scripts (`inline_reply_form.js`, `lazy_compose_signing.js`, Outbox), unchanged.
- `TemplateRenderer`'s template stylesheet map, one new entry matching `board_list.php`. No new CSS.

## Completion conditions

`./v3 test --smoke MusicPageTest BoardListTest` passes, with the page test checking the compact card, the hidden tag and both stylesheets. `tests/browser/music_share.mjs` passes. Screenshots of /music/ and the Board at 800px, collapsed and focused. PR open. Feedback: no matching item in `docs/feedback/items/`. Concepts touched: [Music section](../../concepts/music_section.md) and [shared composer](../../concepts/shared_composer.md), wording updated.

Starting revision and branch: `83d6798f3`, `feat/music_share_compact`.

## Study history

- 2026-11-26 UTC, intake and entry: **0.10**. Claude, single exposed rater. R1 not reverified this session.
- 2026-11-26 UTC, closeout: retrospective **0.10**. Same rater.

## Outcome

/music/ now starts with the same one-line card as the Board, and on focus it shows the same Post row, "…" button, identity line and Options drawer. The track rows also pick up the list styles they were missing. Only `TemplateRenderer.php` (one map entry), `templates/pages/music.php`, `MusicPageController.php` (the Board's scripts), the two tests and the concept wording changed.

## Verification

- Passed: `./v3 test --smoke MusicPageTest BoardListTest` (24 run, 24 passed). New assertions: the compact card class, `board_tags` as a hidden field, and both stylesheets linked.
- Passed: `tests/browser/music_share.mjs` at 390px. The box starts collapsed, grows on input, posts and lands back on /music/ with the new track first and tagged; a no-JavaScript post works; a Music draft never shows on the Board.
- Passed: local mitrapclub server, headless Chrome at 800px, /music/ collapsed and focused beside the Board: same card, same controls.
- Not verified: on the maintainer's phone, on production. Not run: the full suite.

## Final review

Diff: one stylesheet map entry, the share box block in `music.php` (copied from `board_list.php`), the controller's script list, the page and browser tests, two concept lines, this record, the index and the ledger. No new CSS, script or component.

Commit, merge and publication: pushed to `feat/music_share_compact` with an open PR; not merged.

— Claude · 2026-11-26 UTC · Model: claude-opus-5-5
