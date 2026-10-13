> **Feature plan:** [Step 1](./case_insensitive_tags_step1_solution_assessment.md) · [Step 2](./case_insensitive_tags_step2_feature_description.md) · [Step 3](./case_insensitive_tags_step3_development_plan.md) · [Step 4](./case_insensitive_tags_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Capitalization separates otherwise identical tags in `/tags/` and can make linked tag pages unreachable. Users miss matching threads, and admins cannot rely on a single browsing destination for each tag.

## User stories

- As a user, I want tags to match regardless of capitalization so that I can discover all matching threads from the directory or a saved link.
- As an admin, I want existing capitalization variants combined automatically so that I do not need to retag historical content.

## Core requirements

- Combine board tags and thread labels that differ only in ASCII letter case into one browsing group, including existing and future content; count and display each thread once per group.
- Use lowercase group names and generated links. Mixed-case tag URLs, with either supported trailing-slash form, must reach the same group; unknown tags retain the existing not-found behavior.
- Preserve current visibility, access rules, thread ordering, and the five-thread directory preview limit, applying counts and previews after merging.
- Keep Forte tag selection and supported offline tag browsing consistent with the same matching rule; offline results remain limited to the available snapshot.
- Preserve stored spellings and existing tag entry, scoring, moderation, and reaction behavior. Retain the existing tag alphabet apart from accepting uppercase equivalents.

## Delivery scope

**Work type: application change.** Deliver Option A from Step 1 across the directory, linked pages, and related browsing consumers. No historical rewrite, manual cleanup, new administration screen, or international tag-name expansion is included.

## Completion boundary

Enter through `/tags/`, a saved tag link, or the existing Forte filter; reach the combined visible thread list and open a thread. Preserve back navigation and existing unknown-tag or unavailable-snapshot recovery. Release when normal navigation and mixed-case links pass the acceptance checks online and in supported offline browsing, with access rules intact.

## Risks

- **Duplicate membership:** merging variants could inflate counts or previews. Earliest validation: a thread carrying variants in both tag sources; mitigation: require unique thread membership throughout browsing.
- **Consumer drift:** Forte and offline pages could disagree with the directory. Earliest validation: compare their existing selection and grouping flows before Step 3; mitigation: apply one behavioral contract to all three surfaces.
- **Access or action changes:** broader matching could accidentally affect hidden content or special tags. Earliest validation: review route access checks and tag-driven actions before Step 3; mitigation: confine normalization to browsing and verify existing access and action behavior.

## Shared component inventory

- **Online directory and tag pages:** extend `TagsPageController` and canonical `TagGrouping`; reuse existing templates and thread cards.
- **Forte tag navigation and filtering:** extend the shared grouping and existing selection controls together.
- **Offline directory and tag pages:** extend the existing snapshot presentation and route handling to match online browsing semantics.
- **Board/thread/post cards, identity details, and text API:** reuse current metadata rendering; original label spellings may remain visible in content details.
- **Compose/reply forms, tag/reaction APIs, and SQLite inspection/export tools:** retain existing entry, action, and raw-data contracts. No new UI or API is needed.

## Simple user flow

1. Open the tag directory and see one lowercase entry for all capitalization variants.
2. Select that entry, or open a mixed-case saved link, to see each matching visible thread once.
3. Open a thread or return to the directory using existing navigation.

## Success criteria

For content tagged `PHP`, `Php`, and `php`, show exactly one `php` group with the unique combined thread count, at most five preview threads, and every eligible thread on its detail page. All three URL spellings resolve equivalently. Forte and offline browsing produce the same membership for equivalent available content. Existing unknown-tag, access, and action behavior passes regression checks; no stored tag records change.
