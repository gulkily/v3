> **Feature plan:** [Step 1](./tweet_bookmarklet_images_step1_solution_assessment.md) · [Step 2](./tweet_bookmarklet_images_step2_feature_description.md) · [Step 3](./tweet_bookmarklet_images_step3_development_plan.md) · [Step 4](./tweet_bookmarklet_images_step4_implementation_summary.md)

Debug mode: enabled
Study: railspike-effort / v3/tweet_bookmarklet_images / R1 / P2
Extended estimates: enabled
Scope version: v2/B; Step 1 approved; Step 2 awaiting approval

**Cycle estimate: 0.55**

## Problem and user story

The tweet bookmarklet brings text into a draft but leaves its photos behind. As a person clipping a tweet, I want its first photo attached to my draft so that I can save it with the text and attribution.

## Core requirements

- Both bookmarklet launch modes populate the ordinary thread composer with the selected tweet's text, attribution and first attached still photo. Text-only tweets keep working.
- Show the imported photo in the existing attachment preview; let the user remove or replace it before posting. No automatic publication.
- Import only the first photo belonging to the selected tweet, excluding avatars, emoji, quoted-tweet media and video thumbnails. Preserve links to additional photos in the draft; no photo picker or multiple attachments.
- Preserve existing image eligibility and publication rules. If import, preparation, eligibility or publication fails, retain text and source links, show the failure, and offer the applicable retry or manual attachment path. Never silently post without an expected attachment.
- Keep existing bookmarklets, copy behavior, ordinary image uploads, and text posting compatible; add no dependencies or canonical format changes.

## Delivery scope and completion boundary

Work type: application change. Deliver tweet photo capture, composer handoff and integration with the existing image flow, focused regression/browser checks, relevant contract/reference documentation and touched concept inventory entries; include cycle planning, debug and study records. Exclude videos, animation, quoted-tweet photos, image galleries and changes to membership or signing requirements.

Completion means clicking the installed tweet bookmarklet, reviewing the draft, posting normally, and seeing the saved photo with the published text. Verify both launch modes, single/multiple/no-photo tweets, removal/replacement, ineligible identity, import failure and posting retry. Check affected shared composer consumers, relevant themes, phone/desktop layouts and first-paint behavior. Record live-site/browser limitations explicitly; fixtures alone do not establish live X recovery. Handoff requires these checks or explicit unresolved limitations, with material flow failures blocking completion.

## Risks and early validation

- Cross-origin restrictions may prevent automatic photo access. Before dependent implementation, validate a bounded browser-to-composer transfer with existing tools; if unavailable, revise the approach for review rather than silently delivering links only. Manual attachment remains recovery, not proof automatic capture works.
- Tweet markup may mix author photos with quotes, avatars and other tweets. Validate representative selected-tweet and multi-photo fixtures early; constrain capture to the selected tweet's own photos and retain source links on failure. Live behavior remains unverified until replayed.
- Existing attachment restrictions may prevent publishing or importing, or a retry may lose work. Check the shared eligibility/draft/retry contracts before planning dependencies; preserve the existing signed one-image flow and actionable failure feedback.

## Shared components and concepts touched

Reuse/extend `public/assets/tools_bookmarklets.js`, `templates/pages/bookmarklets.php` and existing bookmarklet copy/browser checks for capture and entry. Reuse the composer contract in `src/ForumRewrite/Http/ComposeAndAccountKeyController.php`, `templates/pages/compose_thread.php`, `templates/partials/thread_compose_form.php` and shared `composer.php`; reuse `templates/partials/image_compose_fields.php`, `public/assets/image_prepare.js`, `public/assets/image_upload.js` and existing signing/publication behavior in `src/ForumRewrite/Write/LocalWriteService.php`. Preserve the image record contract in `docs/specs/canonical_post_record_v1.md`. Reuse shared attachment controls and styles rather than creating another uploader. Any new handoff helper must bridge source-page capture to those components, not duplicate them.

Concepts touched: tweet clipping (changed; proposed inventory entry), image attachments (used unchanged; proposed inventory entry). Add current-behavior descriptions during approved implementation only.

## Flow and success criteria

1. Open a tweet and click the bookmarklet.
2. Review its text and first photo in the composer; remove or replace the photo if desired.
3. Post normally and confirm the saved photo displays; on failure, retain the draft and retry or attach manually using the preserved source link.

Success: one photo becomes a local attachment; additional photo links survive; unrelated images are excluded; both launch modes and existing text-only behavior pass; failure/retry checks demonstrate preservation of the draft and attachment intent.

## Requests and study checkpoint

2026-10-31 UTC: user approved Step 1 and clarified “yeah, let's start with just one for now.” Disposition: narrow recommended B to the first photo without a picker, scope v2; retain additional source links. Step 2 approval pending; no implementation authorized. The prior question about saving all images was not an affirmative request for a gallery.

Step 2 projection: 0.55, revised from Step 1 v1/B 0.65 because the picker is removed; plausible range 0.40–0.80, dominated by unverified cross-origin transfer/recovery. Preserve original baseline a1164752242c3acd0105c91d635100720ebe84ef, missing pristine intake and single exposed Codex rater. Frozen R1 report/review/feedback hashes reverified at this checkpoint. No measured labor, independent rating or accepted outcome. No runtime tests performed during this planning step.

*— Codex · 2026-10-31 UTC*

Approval: user explicitly approved Step 2 on 2026-10-31 UTC. Scope v2/B unchanged; Step 3 prepared for review, implementation still unapproved.

*— Codex · 2026-10-31 UTC*
