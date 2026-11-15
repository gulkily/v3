# Photo details collapsed on the post page — one-shot record

Cycle: `v3/photo_details_collapsed`; Mode: one-shot; Record/index: this file, [plans index](../README.md). Predecessor: [image_metadata](../image_metadata/image_metadata_step4_implementation_summary.md) (PR #257, merged).
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/photo_details_collapsed`; [host ledger](../effort_estimation_trial.md)
Extended estimates: enabled

**Cycle estimate: 0.08**

Status: planning; next permitted action: planning commit, then implementation.

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
Planning commit: pending.

## Study history

- 2026-11-15, intake (after reading the renderer and offline mirror written in the predecessor cycle): 0.08. Claude, single exposed rater.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
