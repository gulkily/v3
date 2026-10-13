> **Feature plan:** [Step 1](./case_insensitive_tags_step1_solution_assessment.md) · [Step 2](./case_insensitive_tags_step2_feature_description.md) · [Step 3](./case_insensitive_tags_step3_development_plan.md) · [Step 4](./case_insensitive_tags_step4_implementation_summary.md)

Debug mode: disabled

## Original Query

As a user and admin, I want these tags to be case-insensitive. Please write Step 1 of Railspike.

## Understood Intent

The preceding question concerns `/tags/` and the tag pages it links to. Users and admins should find the same threads under `PHP`, `Php`, and `php`, including existing content, without manually correcting capitalization. This includes both board tags and thread labels shown in that directory.

## Problem

The tag directory separates capitalization variants, individual pages require an exact tag match, and uppercase tag URLs do not match the current route, so capitalization can fragment discovery or produce unusable links ([grouping](../../../src/ForumRewrite/ReadModel/TagGrouping.php), [routing](../../../src/ForumRewrite/Application.php)).

## Option A

Treat capitalization variants as one tag when building the directory and displaying its linked pages, using lowercase display names and links while preserving original stored content.

- **Pros:** Covers existing and future content without an admin cleanup or data migration; gives users one predictable destination per tag.
- **Cons:** Stored spellings can still differ; other tag-dependent features need an explicit scope review to avoid inconsistent behavior.

## Option B

Standardize tags to lowercase when they are saved and reconcile existing capitalization variants so browsing operates on one stored spelling.

- **Pros:** Establishes a consistent stored representation that other tag-dependent features can reuse.
- **Cons:** Requires broader review of existing records, imports, and tag entry paths; changing historical content adds compatibility and recovery work beyond tag browsing.

## Recommendation

Choose **Option A** as a complete, bounded browsing improvement:

- Merge capitalization variants into one directory entry and one destination; count and display each matching thread once even when it carries multiple variants.
- Generate lowercase names and links, and accept mixed-case tag URLs as equivalent destinations. Unknown tags retain the existing not-found behavior.
- Include existing content automatically, with no manual retagging or change to stored records.
- Keep current visibility rules and unrelated tag-driven actions intact. Review shared consumers during subsequent planning; broader changes to moderation, scoring, or tag administration require explicit scope.

This is a viable vertical slice: a user enters through `/tags/` or an existing mixed-case link and reaches all visible threads for that tag. Proposed scope retains the existing tag alphabet; adding international tag names is a separate decision. Later planning should verify merged counts, duplicate prevention, working links, and shared-view consistency before implementation.
