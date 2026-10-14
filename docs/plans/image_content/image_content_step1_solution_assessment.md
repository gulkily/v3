> **Feature plan:** [Step 1](./image_content_step1_solution_assessment.md) · [Step 2](./image_content_step2_feature_description.md) · [Step 3](./image_content_step3_development_plan.md) · [Step 4](./image_content_step4_implementation_summary.md)

Debug mode: disabled

## Original Query

I want to consider simplest possible image content support, not necessarily putting them in the same repo/bucket as text, but still allowing users to back up the bucket. Please explore and consider how that would fit into this project and write Step 1 of Railspike. This feature should also be gated with a feature flag.

## Understood Intent

Allow users to attach images to forum posts and independently preserve the image collection. Treat “bucket” as the instance’s image store; cloud hosting is optional.

## Problem

Add minimal, portable image authoring and viewing without undermining text-first records, user-downloadable backups, or members-only access.

## Project Fit

- [Posts](../../specs/canonical_post_record_v1.md) are canonical text; [shared rendering](../../../src/ForumRewrite/View/MediaEmbedRenderer.php) currently supports YouTube/Instagram previews, not owned image uploads.
- [Backup](../../../src/ForumRewrite/Http/InstancePageController.php) exports the repository with Git history and the SQLite index; neither backs up separately stored images.
- Reuse [registered flags](../../../src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php) and [instance access rules](../../reference/authentication_modes.md); site profiles sharing an instance must share its image store.

## Options

### Option A — Images in the content repository

Store uploaded images alongside canonical records so existing repository downloads carry both text and images.

- **Pros:** Fewest storage concepts; straightforward cloning and recovery.
- **Cons:** Binary history enlarges every repository backup/clone; upload validation, rendering, and access enforcement still need work.

### Option B — Separate local image store

Keep image files outside Git in one configured instance directory, retain portable references in text posts, and offer a separate image archive through Backup.

- **Pros:** Fits the existing PHP/filesystem deployment; avoids binary Git growth and cloud credentials; users can preserve both stores.
- **Cons:** Requires two coordinated backup artifacts and explicit restoration instructions; disk capacity and archive size need bounds.

### Option C — Separate object-storage bucket

Keep portable image references in posts while an operator-controlled remote bucket holds the files; provide users a complete export through the forum.

- **Pros:** Separates media capacity from the application host; supports independent bucket replication.
- **Cons:** Adds provider setup, credentials, transfer costs, access control, and export coordination. External image URLs alone cannot guarantee backup or restoration.

## Recommendation and Vertical Slice

Choose **Option B** initially. Reconsider C if remote storage is a deployment requirement; avoid multiple storage backends in the first slice.

- Deliver one image per post/reply: select a bounded raster image, add descriptive text, publish, view through shared rendering, download text and image backups, and restore on another instance. Preserve the draft on upload failure; show a readable fallback for missing images.
- Keep stable, host-independent image references and integrity metadata with canonical text. An image export includes the entire retained image store, an inventory/checksums, and its relationship to a repository revision; report missing objects or incomplete exports. Verify recovery with both artifacts, without the original host or bucket credentials.
- Propose **`FORUM_IMAGE_CONTENT_ENABLED`**, default off, using existing operator controls and override precedence. Off disables upload UI/API and inline image delivery/rendering, including generated-page paths; references and stored bytes survive, and authorized backup remains available.
- Image reads and exports follow instance membership restrictions; upload permission and limits are explicit. Do not expose member images as public presentation assets. Start with approved-member uploads and validated JPEG/PNG files with byte/dimension limits.
- Defer galleries, transformations, remote-URL ingestion, private-message attachments, automatic image deletion, and offline image caching. Repository-only imports/offline copies must disclose missing media. The slice is complete only after upload → view → backup → restore works; split scope before implementation if it exceeds Railspike’s one-day bound.
