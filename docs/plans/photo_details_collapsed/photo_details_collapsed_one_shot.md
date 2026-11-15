# Photo details collapsed on the post page — one-shot record

Cycle: `v3/photo_details_collapsed`; Mode: one-shot; Record/index: this file, [plans index](../README.md). Predecessor: [image_metadata](../image_metadata/image_metadata_step4_implementation_summary.md) (PR #257, merged).
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/photo_details_collapsed`; [host ledger](../effort_estimation_trial.md)
Extended estimates: enabled

**Cycle estimate: 0.08**

Status: technically complete on `feature/photo-details-collapsed`; next action: maintainer review of the PR.

## Original request

Amazing work, Claude. I've merged the PR and it looks great. And I just wanted to add one enhancement. When the details for the photo are displayed on the post page, I want them to be collapsed and expandable, please.

## Authorization

2026-11-15: the maintainer asked for this enhancement directly after merging PR #257. Its boundary is clear: change how already-attached photo details display. A one-shot cycle is enough because the approach needs no review. Exclusions: the composer checklist, the post format, and `frm.py`.

## Outcome and work type

Application change. On the post page, photo details under an image are in a collapsed row named "Photo details". It opens to show the existing label and value list. The offline reader matches this, following the offline-mode rule that it looks like the online site.

## Approach and risks

- Wrap the existing `post-photo-details` list in a native `<details>` with a "Photo details" summary, closed by default. No JavaScript.
- Risk: `<details>` inside the inline `post-image` span. Impact: browsers could move it out of the card. Validation: render the HTML in Chromium and check the DOM. Mitigation: switch the wrapper element if the DOM is wrong.
- Risk: the offline reader escapes text before adding `<br>`, so HTML cannot pass through. Mitigation: build the details markup from escaped parts after the line-break step.

## Reuse decisions

- `MediaEmbedRenderer` image card and `ImageReference::photoDetails` (`src/ForumRewrite/View/MediaEmbedRenderer.php`, `src/ForumRewrite/Images/ImageReference.php`): extended, same data.
- Disclosure look: reuse the site's existing `details`/`summary` styling plus a small rule in `public/assets/site.css`. The composer's `compose-disclosure` class is form-specific, so it is not reused here.
- Offline mirror: `nl2br` in `public/assets/offline_reader.js` and its `photoDisplay` helper.

## Completion conditions

- Normal entry: a thread page with a post whose image has photo details.
- End-to-end result: the details are hidden until "Photo details" is tapped; tapping again closes them. Posts without details are unchanged.
- Recovery: none needed. It is display only, and works without JavaScript.
- Release: smoke set plus affected tests pass; maintainer check on zenmemes.com after merge.

## Concepts touched

[Photo details](../../concepts/photo_details.md): update the scenario to say the post page shows them collapsed.

## Verification and recovery plan

- `MediaEmbedRendererTest`, `OfflineSnapshotPresentationTest` updated; smoke set.
- Chromium DOM check that `<details>` stays inside the image card, and screenshots in Gloss and Word97, closed and open.
- Inapplicable: composer tests, because the composer is unchanged.

Starting revision and branch: `43388a8` (origin/main after PR #257), branch `feature/photo-details-collapsed`.
Planning commit: `0cb3200` (confirmed with `git log`).

## Dated execution history

- 2026-11-15, clarification (correction to the outcome, not an addition): mid-implementation the maintainer added "Unless there's only one detail, then you can leave it expanded." Outcome is now: two or more details fold behind a closed "Photo details" row; a single detail shows without the row. Recorded before the rendering code was written.
- 2026-11-15: `MediaEmbedRenderer` wraps the list in `<details class="post-photo-details-disclosure">` with a "Photo details" summary when there are two or more details. `site.css` styles the summary with a 44-pixel touch height and a focus outline.
- 2026-11-15: `offline_reader.js` marks folded details with a NUL-delimited index before escaping. After line breaks are added, it swaps the marker for the escaped `<details>` markup. A stray marker with no matching entry renders nothing.
- 2026-11-15: concept entry [photo details](../../concepts/photo_details.md) updated.

## Outcome

Delivered as clarified. The composer, post format and `frm.py` are unchanged.

## Verification

- Passed: `./v3 test --smoke MediaEmbedRendererTest OfflineSnapshotPresentationTest`, 491/491. The tests now cover two or more details collapsed, a single detail visible, and the offline reader for both cases.
- Passed: Chromium DOM check in Gloss and Word97 at 390 pixels. The `<details>` stays inside the `post-image` card, starts closed, the summary is 44 pixels tall, and one tap opens it. Screenshots taken closed and open.
- Not run: the full suite; Safari. The maintainer checks on zenmemes.com after merge.

## Final review

Diff limited to the renderer, offline mirror, `site.css`, two tests, the concept entry and this cycle's records. No duplication: the list markup is built once and wrapped only when needed.

Commit/merge/publication status: completion commit pending at the time of writing; to be pushed as a new PR. Not merged.

## Study history

- 2026-11-15, intake (after reading the renderer and offline mirror written in the predecessor cycle): 0.08. Claude, single exposed rater.
- 2026-11-15, execution entry: 0.08 reaffirmed. The single-detail clarification arrived after the planning commit; it is a correction to the outcome and leaves the estimate unchanged.
- 2026-11-15, retrospective scope assessment: 0.10. The offline marker swap took a little more than planned. Maintainer judgment pending.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
