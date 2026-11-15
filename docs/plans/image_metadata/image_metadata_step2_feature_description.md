> **Feature plan:** [Step 1](./image_metadata_step1_solution_assessment.md) · [Step 2](./image_metadata_step2_feature_description.md) · [Step 3](./image_metadata_step3_development_plan.md) · [Step 4](./image_metadata_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `v3/image_metadata` ([ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)); standing enrollment under protocol P2
Extended estimates: enabled

**Cycle estimate: 0.50**

Approved direction: Step 1 Option B, readable photo details attached to the post (approved 2026-11-15).

## Problem

Image preparation always discards a photo's metadata. A poster cannot share when, where or with what camera a photo was taken.

## Concepts touched

- **Shared composer** ([shared_composer.md](../../concepts/shared_composer.md)): gains a photo details checklist beside the image.
- **Post body formatting** ([post_body_formatting.md](../../concepts/post_body_formatting.md)): post pages render photo details under the image.
- Proposed new entry **Photo details**: the chosen metadata as `Key: value` lines in a post, what each line means, and why location starts unticked.

## User stories

- As a poster, I want to see what metadata my photo carries so that I know what I could share.
- As a poster, I want to tick the details to attach so that I share the camera and time without sharing my location.
- As a reader, I want to see a photo's details under the image so that I know when and how it was taken.
- As an archivist, I want the details stored as readable signed text so that exports and the Git repository keep them.

## Core requirements

- Sharing stays opt-in: with no box ticked, the post is exactly what it is today.
- Location is never ticked by default; the poster ticks it by hand for each photo.
- Only details the poster ticked leave the device; the image file still carries no metadata.
- Details are plain `Key: value` text in the signed post body, next to the `Image-v1:` line.
- Details render under the image on the post page and in the offline reader.

## Delivery scope

- Work type: application change.
- Slice: JPEG and other EXIF-bearing photos attached from the browser composer, for new posts and replies. Fields: camera, lens, exposure settings, time taken, location.
- Out of scope for this cycle: `frm.py`, keeping metadata inside the image file (Option A), and editing details after posting.

## Completion boundary

- Normal entry: Attach image in any composer, on phone or desktop.
- End-to-end outcome: the poster ticks details, posts, and the post page shows them under the image.
- Recovery: a photo with no metadata shows no checklist and posts as today. Unreadable metadata never blocks posting. Unticking everything returns to today's behavior. Retrying a failed upload keeps the same choices.
- Release condition: smoke set and affected tests pass; manual check on iPhone Safari with a real camera photo; deployed and confirmed on zenmemes.com by the maintainer.

## Known user or environment

The maintainer posts from an iPhone. iPhone Safari may hand the page a photo with location already removed, depending on how it was picked. Ordinary entry is the reply or New post composer on iPhone Safari. If location is missing, the checklist simply has no location row, and the poster can still share the other details. Evidence: a real iPhone camera photo attached in Safari, with the posted details checked on the post page.

## Risks

- **Accidental location sharing.** Impact: reveals where someone lives. Validation: first browser test. Mitigation: location starts unticked and shows its value in plain words before posting.
- **Browser strips metadata before the page sees it.** Impact: the feature looks broken on some phones. Validation: early iPhone Safari check. Mitigation: hide the checklist when nothing is found; document the behavior.
- **Malformed EXIF in a photo.** Impact: a hang or crash during attach. Validation: tests with truncated and oversized EXIF. Mitigation: a bounded reader that gives up quietly.
- **New post format lines.** Impact: older readers show raw lines. Validation: render check on the post page, offline reader and text API. Mitigation: follow the `Image-v1:` line's existing handling in each consumer.

## Shared component inventory

- `public/assets/image_prepare.js`: already parses JPEG markers for dimensions. Extend it to read EXIF from the same bytes; no new parser file or third-party library.
- `public/assets/image_upload.js` and `templates/partials/image_compose_fields.php`: the attach state and fieldset. Add the checklist here so every composer gets it.
- `imageSignedFields` in `public/assets/browser_signing.js` and the reply path in `reply_continuation.js`: add the ticked lines where the `Image-v1:` line is written today.
- `src/ForumRewrite/Images/ImageReference.php` and `src/ForumRewrite/View/MediaEmbedRenderer.php`: parse and render details beside the existing image card.
- `public/assets/offline_reader.js`: mirror the post-page rendering of the `Image-v1:` line.
- `ApiTextController.php` and `RssFeed.php`: keep details as plain text; check that nothing breaks.

## User flow

1. The poster taps Attach image and picks a photo.
2. The preview appears with a "Photo details" checklist showing each detail found, all unticked.
3. The poster ticks camera and time taken and leaves location unticked.
4. The poster posts.
5. The post page shows the photo with those details under it.

## Success criteria

- A camera photo posted with nothing ticked produces a post identical in format to today's.
- A photo posted with chosen details shows exactly those details, and no others, on the post page and in the offline reader.
- Location appears only when the poster ticked it.
- A photo without metadata, or with damaged metadata, posts normally with no error.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*

## Study snapshots

- 2026-11-15, Step 2 handoff: 0.50 reaffirmed for Option B, scope v1. Same baseline `14beeaaa`; Claude, single exposed rater; R1 hashes not re-verified this session. Ledger index row pending until the planning commit.

## Approval

- 2026-11-15: maintainer replied "Approved. Step 2."

## Enhancement Requests

- 2026-11-15: maintainer asked for a mockup. Delivered as a design canvas; no scope change.
