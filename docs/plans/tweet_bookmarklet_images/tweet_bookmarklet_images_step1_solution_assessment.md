> **Feature plan:** [Step 1](./tweet_bookmarklet_images_step1_solution_assessment.md) · [Step 2](./tweet_bookmarklet_images_step2_feature_description.md) · [Step 3](./tweet_bookmarklet_images_step3_development_plan.md) · [Step 4](./tweet_bookmarklet_images_step4_implementation_summary.md)

Debug mode: enabled
Study: railspike-effort / v3/tweet_bookmarklet_images / R1 / P2
Extended estimates: enabled
Scope version: v1; planning only; no implementation approval

**Cycle estimate: 0.65**

## Original Query

It would be nice for the tweet bookmarklet to also capture images.

## Problem

The tweet bookmarklet copies text and attribution but omits attached photos.

## Option A — Preserve photo links

**Estimate: 0.25**

Include the selected tweet's photo URLs in the draft alongside its text and attribution.

- Captures all photo references with a small change to the existing bookmarklet.
- Images remain dependent on the external host; this does not save image attachments locally.

## Option B — Import a photo into the existing attachment flow

**Estimate: 0.65**

Carry the selected tweet's photos into the composer, prepare one as a local attachment, and let the user review it before posting. For multiple photos, offer a choice and preserve the remaining source links rather than silently dropping them.

- Saves a photo using the existing signed publication, preparation, preview and recovery flow.
- Preserves the current one-image-per-post contract and membership requirements.
- Cross-origin image access must be proven early; failures must preserve text and source links with an actionable manual attachment fallback.

## Recommendation and boundary

Recommend B as a usable first slice: bookmarklet → populated composer → reviewed attachment → published post, with retry/manual recovery preserving the draft. Exclude video, GIF animation, quoted-tweet media, automatic publication and multiple local attachments per post. If saving every photo locally is essential, assess that broader attachment change before proceeding. No new dependencies proposed.

## Existing contracts and concepts touched

Reuse `public/assets/tools_bookmarklets.js` for tweet selection, attribution and both launch modes; `public/assets/image_prepare.js` and `public/assets/image_upload.js` for preparation and attachment state; `src/ForumRewrite/Write/LocalWriteService.php` and `docs/specs/canonical_post_record_v1.md` for signed image publication. No new parallel uploader or canonical record format. Proposed concept inventory entries: tweet clipping (changed) and image attachments (used unchanged); create descriptions only during approved implementation.

## Study checkpoint and request record

2026-10-31 UTC: baseline a1164752242c3acd0105c91d635100720ebe84ef; Codex, single exposed rater. Pristine intake missing: bookmarklet, image contracts and guidance inspected before scoring. Frozen R1 report, implementation review and feedback hashes verified in `docs/research/effort_estimation/references/R1/`. Step 1 whole-cycle projection B 0.65, independently judged; option A 0.25. B's driver is browser-to-composer transfer and failure recovery; plausible range 0.50–0.90 because cross-origin access is unverified. A's driver is reliable photo selection and bookmarklet regression checks; plausible range 0.15–0.35. Estimates include planning, implementation, checks, documentation and ordinary correction; no measured labor or independent rating. Acceptance pending.

Request: capture tweet images. Disposition: proposed Option B; exact scope awaiting review. Approval status: Step 1 pending. Outcome: assessment only, application unchanged.

*— Codex · 2026-10-31 UTC*

## Approval and scope clarification — 2026-10-31 UTC

User approved Step 1, then confirmed “yeah, let's start with just one for now.” Carry recommended B into Step 2 as scope v2: import the first photo, omit the photo picker, retain links to additional photos. Original v1 options and estimates remain historical; Step 2 defines the narrowed requirements for approval. Implementation remains unapproved.

*— Codex · 2026-10-31 UTC*
