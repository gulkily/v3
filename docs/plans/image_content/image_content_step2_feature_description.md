> **Feature plan:** [Step 1](./image_content_step1_solution_assessment.md) · [Step 2](./image_content_step2_feature_description.md) · [Step 3](./image_content_step3_development_plan.md) · [Step 4](./image_content_step4_implementation_summary.md)

Debug mode: disabled

## Problem

Users cannot attach owned images to posts or preserve them through existing backups. Add minimal image support while retaining portable text records and instance access controls.

## User Stories

- As an approved member, I want to attach an image with descriptive text so that I can illustrate a post or reply.
- As an authorized reader, I want to download the image collection so that I can preserve and restore the forum independently.
- As an operator, I want to disable image support and later relocate storage so that adoption remains reversible.

## Core Requirements

- Support one validated JPEG/PNG per post/reply, with descriptive text and published byte/dimension limits; uploads require approved membership and existing posting permissions. Preserve drafts on failure; missing images show a readable fallback.
- Store images outside Git in one local instance store shared across site profiles. Keep stable, host-independent references and integrity metadata in canonical content. Future **Option C**, remote object storage, must preserve references and backup portability without rewriting posts; no cloud backend is included now.
- Extend Backup with a complete retained-image export, inventory/checksums, repository-revision association, and restoration guidance. Identify mismatched or incomplete backup pairs and missing images; never present partial exports as complete. Restoration must work without the original host.
- Register **`FORUM_IMAGE_CONTENT_ENABLED`**, default off, using existing operator controls and override precedence. Off blocks upload UI/API, inline rendering, and image delivery, including generated pages; retain bytes/references and authorized backup access.
- Enforce instance access rules on image reads and exports, including members-only sites. Prevent public-asset bypasses; keep existing text posting and media embeds independent.

## Delivery Scope and Completion Boundary

**Work type:** application change. Deliver normal compose → publish → view → Backup → documented restoration on another instance, including upload failure and missing-image recovery. Release only after that flow and flag/access checks pass. Defer galleries, transformations, cloud integration, URL ingestion, private-message attachments, automatic deletion, and offline image caching. Reassess scope if planning exceeds one day/eight stages.

## Risks

- **Access leakage:** direct or cached images could bypass membership/flag changes. Before Step 3, trace serving and generated-page paths; require protected delivery and cache invalidation. Previously downloaded copies cannot be recalled.
- **Incomplete preservation:** separate exports can diverge during writes. Before Step 3, establish revision/inventory consistency and restore checks; reject incomplete results and explain pair mismatches.
- **Resource exhaustion:** uploads or full exports can exceed host limits. Before Step 3, assess bounded files, total storage, and export capacity; select explicit limits and actionable capacity failures.

## Shared Component Inventory

- Extend shared thread/reply forms used by compose pages and Forte panes/dialogs, plus existing posting APIs; add image upload/delivery handling because no owned-image service exists.
- Extend canonical shared body rendering for thread/post/root/reply cards, board previews, profiles, reply context, QDB quotes/news, and Forte detail/API fragments; reuse it for generated HTML.
- Preserve readable references/fallbacks in activity summaries, text APIs, RSS, SQLite views, and offline readers. Repository-only imports and offline copies must disclose unavailable media.
- Extend existing Backup/download and Feature Flags surfaces; reuse identity, membership, and instance configuration rules.

## Simple User Flow

1. Operator enables image support; approved member selects an image and adds descriptive text in normal compose.
2. Member publishes; authorized readers see the image, or a clear unavailable state.
3. Reader downloads repository and image exports from Backup; operator verifies and restores both on another instance.

## Success Criteria

- One thread and one reply complete upload → view → export → restore with matching image checksums and unchanged references.
- Invalid/oversized uploads preserve drafts; missing images and incomplete exports produce explicit outcomes.
- Flag-off and unauthorized requests cannot upload or retrieve images; authorized backup still works when disabled, and text-only behavior remains intact.
