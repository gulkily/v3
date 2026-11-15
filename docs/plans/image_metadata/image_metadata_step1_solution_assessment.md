> **Feature plan:** [Step 1](./image_metadata_step1_solution_assessment.md) · [Step 2](./image_metadata_step2_feature_description.md) · [Step 3](./image_metadata_step3_development_plan.md) · [Step 4](./image_metadata_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `v3/image_metadata` ([ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)); standing enrollment under protocol P2
Extended estimates: enabled
Cycle estimate: 0.50

## Original Query

Hey Claude, I appreciate you. I'd like to add a feature to ZenMemes, please, that when I'm attaching a photo, I have the option of attaching the metadata, please.

## Understood Intent

Today every attached photo loses its metadata. The browser redraws the photo on a canvas in `public/assets/image_prepare.js`, and `public/frm.py` strips metadata on purpose. The request is an opt-in control at attach time that keeps the photo's metadata with the post. Stripping stays the default, because metadata can include the GPS location where the photo was taken.

## Problem statement

A poster cannot share when, where or with what camera a photo was taken, because image preparation always discards that metadata.

## Concepts touched

- **Image attachment** (all options): the prepared file and its `Image-v1:` line in the post body.
- **Post record** (B, C): new `Key: value` lines carrying photo details.
- **Compose form** (all options): one new opt-in control beside the image description.

## Option A — Keep the metadata inside the image file

**Estimate: 0.35**
Driver: copying EXIF safely into the re-encoded JPEG and PNG. Uncertainty: browser differences in canvas output.

When the poster ticks "Keep photo metadata", the browser copies the source photo's EXIF block into the prepared file. It resets the orientation tag, because the pixels are already upright. Downloaders then get the metadata in the usual place.

- Pro: standard; any photo viewer or tool reads it.
- Pro: no change to the post record format.
- Con: invisible on ZenMemes unless a viewer is also built.
- Con: all or nothing; the poster cannot drop the location and keep the rest.
- Con: EXIF is binary, which cuts against the project's human-readable rule.

## Option B — Attach readable photo details to the post

**Estimate: 0.50**
Driver: reading EXIF in the browser and showing it on the post. Uncertainty: how many fields are worth showing.

The browser reads the photo's EXIF before redrawing it. It shows the details it found, such as camera, lens, exposure, time taken and location, as a checklist under the image. The poster ticks the details to attach. These become plain `Key: value` lines in the signed post, and the post page shows them in the image details. Location stays unticked by default.

- Pro: readable, signed, and stored in the Git repository like every other record.
- Pro: the poster sees and chooses each detail, so location sharing is deliberate.
- Pro: visible on the site, offline view and exports without extra tools.
- Con: the image file itself still carries no metadata.
- Con: needs a small EXIF reader in the browser, written in-house to avoid a new dependency.

## Option C — Both: readable details plus metadata kept in the file

**Estimate: 0.65**
Driver: Options A and B together, plus keeping the two consistent. Uncertainty: what the file keeps when the poster unticks a detail.

Option B's checklist decides what is shared. The ticked details are written to the post and also kept inside the image file.

- Pro: covers both readers on the site and people who download the photo.
- Con: largest change; two copies of the same facts can drift apart.
- Con: rebuilding a partial EXIF block is the riskiest part of the work.

## Recommendation

Option B. It shows the metadata on ZenMemes, keeps it readable and signed, and makes sharing a location a deliberate choice. A vertical slice is viable: JPEG photos from the browser compose form, with camera, time taken and location. PNG sources and `frm.py` support can follow in later cycles. Option A stays available as a later add-on if downloaders need the file metadata.

Open question for review: should the details also be kept inside the image file (Option C)?

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*

## Study snapshots

- 2026-11-15, intake (after inspecting `image_prepare.js`, `image_upload.js`, `browser_signing.js`, `ImageUploadValidator.php` and `frm.py`): 0.50 for the recommended scope. Baseline `14beeaaa9ce770be1078da6f22ef9b41386d0935`. Claude, single exposed rater; R1 hashes not re-verified this session.
- 2026-11-15, Step 1 handoff: 0.50 for Option B, reaffirmed. Option estimates A 0.35, B 0.50, C 0.65; independent, not summed. Ledger index row pending until the planning commit.

## Approval

- 2026-11-15: maintainer chose Option B and replied "Approved step 1."

## Enhancement Requests

None.
