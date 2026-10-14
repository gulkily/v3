> **Feature plan:** [Step 1](./image_upload_enhancements_step1_solution_assessment.md) · [Step 2](./image_upload_enhancements_step2_feature_description.md) · [Step 3](./image_upload_enhancements_step3_development_plan.md) · [Step 4](./image_upload_enhancements_step4_implementation_summary.md)

Debug mode: enabled

## Original Query

We want to make many enhancements to the image upload feature. Can you please write Step 1 for as many as can fit into one Railspike cycle:

- Image uploading:
  - Remove the limitations on image size and type, and allow for more flexible image handling.
  - Automatically downsample images to a reasonable size for display, and discard the original.
    - Downsample it on the client side if possible.
  - Create a feature to auto-describe images using LLM.
  - Create a modern image upload UI.
    - Support mobile devices with large area touch targets.
    - Allow drag-and-drop and paste from clipboard.
  - Do not display the form to users who cannot upload images.

## Problem

The current uploader rejects images outside JPEG/PNG, 5 MiB, and 4096 pixels per side, retains original bytes, and exposes image controls without checking upload eligibility.

## Option A — Client-side preparation and a shared modern uploader

Let eligible users select, drop, or paste a browser-decodable image, automatically prepare a display-sized copy, preview it, and publish it through the existing image-post flow.

- Pros: Covers most requested enhancements in one cohesive cycle; uploads and retains only the prepared copy; preserves existing storage, signatures, and backups.
- Cons: Format support depends on the browser; device memory still bounds input size; LLM descriptions remain a follow-up.

## Option B — Client preparation with server conversion fallback

Add server conversion for images the browser cannot prepare, alongside the same uploader, to broaden accepted formats and device coverage.

- Pros: More consistent input support, including a path toward phone formats unsupported by some browsers.
- Cons: Adds decoder deployment, original-file transfer and cleanup, and preparation before signing; likely exceeds one day with the UI and recovery work.

## Option C — Preparation, uploader, and LLM descriptions together

Extend Option A with automatic, editable image-description suggestions before publication.

- Pros: Addresses every requested area, with descriptions available for review alongside the preview.
- Cons: Existing LLM contracts are text-based; vision integration, provider disclosure, cost controls, and failure handling add a separate uncertain dependency, making a one-day cycle unlikely.

## Recommendation and cycle boundary

Choose **Option A**, targeting one day and no more than eight development stages. Include:

- Larger source images and additional browser-decodable raster formats, converted to supported stored formats. Replace the current source-file restrictions with automatic preparation; retain bounded processing and stored-output validation. This broadens support without promising unlimited sizes or every format.
- Automatic client downsampling, provisionally to a 2048-pixel longest edge without upscaling, preserving aspect ratio, orientation, and transparency. Show the resulting preview; retain only the prepared copy in application storage. Existing images and users’ local files remain untouched.
- One shared thread/reply uploader with large touch targets, keyboard-accessible file selection, drag-and-drop, clipboard image paste, preview, replace/remove, and clear preparation/error states. Preserve ordinary text paste. Animated inputs produce an explicitly disclosed still preview.
- Hide image controls until upload eligibility is established; update on identity/permission changes and retain server authorization. Keep text composition available.
- Preserve the draft and prepared image for recoverable retries while the page remains open. Unsupported or unprocessable images receive actionable errors; do not silently upload originals.

The releasable slice is **eligible user → choose/drop/paste → prepared preview → signed publication → displayed image**, including retry/removal and rejection of unauthorized uploads. Validate mobile preparation, permission discovery, and signing the exact prepared bytes early; these determine whether the cycle fits. Existing backup/restore compatibility must hold.

Defer server-only formats, animation preservation, storage-capacity expansion, and LLM auto-description. The LLM follow-up should generate editable descriptions from the prepared image, disclose provider processing, and allow manual description/publication when generation fails. Keep the existing manual description field this cycle.
