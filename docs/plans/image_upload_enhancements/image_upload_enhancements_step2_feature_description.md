> **Feature plan:** [Step 1](./image_upload_enhancements_step1_solution_assessment.md) · [Step 2](./image_upload_enhancements_step2_feature_description.md) · [Step 3](./image_upload_enhancements_step3_development_plan.md) · [Step 4](./image_upload_enhancements_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Image posting requires users to resize or convert files themselves and exposes controls to people who cannot upload. Approved Option A makes image preparation automatic and provides one accessible uploader across thread and reply composition.

## User stories

- As an approved member, I want to select, drop, or paste a large image so that I can publish without external editing.
- As a mobile or keyboard user, I want accessible controls and a preview so that I can confidently attach, replace, or remove an image.
- As someone unable to upload, I want image controls hidden so that text composition remains clear.

## Core requirements

- Prepare browser-decodable raster images locally, including sources above today's 5 MiB/4096-pixel restrictions and additional formats. Produce JPEG/PNG within existing stored-output limits, with a 2048-pixel maximum edge, no upscaling, correct orientation/aspect ratio, and preserved transparency. Bound processing; explain unsupported inputs. Animated inputs become disclosed still previews.
- Upload and retain only the prepared copy; the preview, signed reference, and published image must agree. Before accepting a new upload, the server independently validates actual image contents, successful bounded decoding, JPEG/PNG format, still-image output, the 5 MiB byte limit, the 2048-pixel maximum edge, and agreement with the signed hash/type/size/dimensions; filenames, declared MIME types, and client checks are insufficient. Reject invalid output with a recoverable error; do not transform signed bytes. Preserve existing images, local source files, and backup/restore compatibility.
- Provide shared file selection, drag-and-drop, scoped clipboard image paste, preview, replace/remove, preparation status, and manual description. Controls have keyboard access, visible focus, and touch targets at least 44 × 44 CSS pixels; ordinary text paste remains intact.
- Show image controls only when upload eligibility is established, including enabled support, approved signing identity, and online secure-context availability. Recheck on identity/permission changes; enforce authorization on the server. Text posting remains available under its existing rules.
- Preserve text and the prepared attachment through recoverable failures while the page remains open; retry without duplicate publication. Explain that reload requires reselection. Unsupported inputs, conversion failures, and unavailable upload conditions offer removal/reselection without silently sending originals; images remain unavailable to Outbox.

## Delivery scope and completion boundary

**Work type: application change.** One image per post; one day / at most eight stages. Defer LLM descriptions, server conversion, animation preservation, and capacity expansion. Release requires the normal compose → prepare → preview → publish → view journey and recovery working across shared entry points. At completion, mark only implemented, verified items in `todo.txt`; describe partial size/type coverage accurately and leave deferred work open.

Server validation is in this cycle; server conversion remains deferred. The 2048-pixel rule applies to new uploads, not existing-image reads, backups, restores, or repair of an existing signed attachment. Without the original, the server cannot prove absence of upscaling or preservation of source orientation/aspect ratio/transparency; verify these through client preparation tests.

## Risks

- **Mobile decoding:** memory/format differences can prevent preparation. Before Step 3, establish representative large, rotated, transparent, and animated samples and browser coverage; mitigate with bounded preparation and actionable failure states.
- **Eligibility discovery:** hidden controls could strand approved browser identities or expose stale permissions. Before Step 3, trace identity discovery and switching; use existing authoritative approval checks and fail closed for image controls.
- **Signing/retry consistency:** changing prepared bytes could invalidate signatures or duplicate posts. Before Step 3, trace preparation and retry ownership; preserve one prepared attachment per attempt and existing publication recovery.
- **Validation compatibility:** header-only inspection can accept damaged images, while globally tightening limits can break old attachments. Before Step 3, assess bounded server decoding and upload-versus-legacy validation; reject malformed/new oversized uploads while preserving existing signed media.

## Shared component inventory

- Extend the canonical image fields and thread/reply forms across board, thread, dedicated compose, QDB add, and paned compose/dialog surfaces; no separate upload page.
- Extend shared browser signing, lazy initialization, styling, and draft clearing; preserve Outbox exclusions. Reuse prepare-thread/reply and finalize-post APIs and their server authorization.
- Extend server upload validation; reuse canonical image references/store, shared post-media rendering (including generated pages), image delivery, and instance backup/download/restore surfaces, preserving legacy contracts.

## User flow

1. Eligible member opens any existing composer and selects, drops, or pastes an image.
2. Review the prepared preview; optionally describe, replace, or remove it.
3. Publish and view the image, or recover from an error with the draft intact.

## Success criteria

Representative oversized and additional-format inputs publish as bounded prepared copies; all three input methods work. Mobile/touch and keyboard journeys pass; ineligible states expose no uploader and reject direct uploads. Retry produces one post, text paste/posting remain intact, and prepared images survive backup/restore.

Direct-upload tests bypass client preparation: reject images over 2048 pixels or 5 MiB, false type declarations, malformed/undecodable files, animated output, and signed-reference mismatches before publication/storage. Valid prepared images pass; previously valid images above 2048 pixels remain readable, restorable, and repairable.
