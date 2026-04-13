# PHP Content Metadata Display Slices V1

This document outlines the implementation slices for standardizing how content metadata is displayed across the PHP forum rewrite.

## Goal

Make content surfaces display consistent, friendly metadata for:

- when something was posted or updated
- who posted it or owns it

The retained version should:

- use one standardized rendering pattern for author/owner/poster metadata
- use friendly human-readable timestamps instead of raw IDs or missing time context
- apply consistently across thread, post, board, activity, and related summary surfaces
- avoid duplicating author/timestamp formatting logic in multiple templates

## Current Context

The repo already has:

- author/profile linkage in the read model for posts
- template-based rendering via `TemplateRenderer`
- repeated author rendering logic in multiple templates
- no indexed post or thread timestamps in the current read model

So this work is partly a presentation cleanup, but it also requires a small read-model and canonical-contract extension before the UI can be standardized fully.

## Product Direction

This should not become a pile of per-page formatting decisions.

The first retained version should prefer:

- one canonical display pattern for content metadata
- one place that decides how linked authors are rendered
- one place that decides how friendly timestamps are rendered
- templates that pass structured metadata into a shared rendering primitive

And should avoid:

- repeating author link logic in page templates
- mixing raw timestamp strings and friendly labels ad hoc
- solving timestamps page by page without first deciding the canonical source field

## Slice 1: Canonical Timestamp Contract

Focus:

- define where timestamps come from and what they mean

Checklist:

- decide whether canonical post records gain a required timestamp header or an optional one for the first slice
- define the exact header name and value format
- decide whether thread-level timestamps are derived from root and latest post timestamps rather than stored separately in canonical files
- document whether friendly display uses the original post time only or also exposes last-activity time on thread/list surfaces
- document timezone and formatting assumptions for canonical storage
- update the relevant spec docs and examples

Recommended first-version rule:

- canonical post records should carry a machine-readable absolute timestamp
- thread list surfaces should derive both root-post time and last-activity time from indexed posts
- friendly rendering should always be derived from that absolute canonical value, never from file mtimes or git history

Expected outcome:

- timestamps become authoritative canonical data rather than inferred runtime decoration

Implementation status:

- canonical post records now require `Created-At`
- parser validation enforces RFC 3339 UTC `...Z` timestamps
- fixture post records and write-generated posts now include canonical timestamps
- spec examples and rules now treat canonical post time as authoritative

## Slice 2: Read-Model And Query Support

Focus:

- index the new metadata so renderers can use it everywhere

Checklist:

- extend canonical parsing to read the timestamp field
- add indexed timestamp columns to the `posts` table
- add derived timestamp columns to the `threads` table as needed for root-post time and last-activity time
- expose timestamp fields in activity queries
- review username/profile queries to decide whether any owner/poster fields should be normalized further for shared rendering
- keep SQL selection shapes aligned so templates receive consistent metadata keys

Expected outcome:

- application fetch methods can return complete metadata without page-specific reconstruction

Implementation status:

- post `created_at` now indexes into the read model
- thread rows now expose both root-post time and last-activity time
- activity rows now carry canonical timestamps directly instead of guessing from post IDs
- application fetch and text/RSS query outputs now expose canonical time fields

## Slice 3: Shared Display Primitive

Focus:

- create the standardized rendering path for who/when metadata

Checklist:

- define a single presenter/helper/partial contract for content metadata
- centralize the current approved-vs-unapproved author link behavior
- decide the exact friendly timestamp style for V1
- ensure the primitive can render:
  - linked approved usernames
  - linked unapproved profile labels
  - plain fallback labels when no profile exists
  - friendly timestamps from absolute indexed values
- keep the template API simple enough that pages only pass structured fields and do not rebuild strings

Recommended first-version output shape:

- a compact metadata line such as `Posted by <author> <friendly time>`
- thread/list surfaces may extend that shape to include `Last activity <friendly time>` when useful

Expected outcome:

- author/timestamp rendering decisions live in one place instead of many templates

Implementation status:

- `TemplateRenderer` now exposes shared helpers for author links, friendly timestamps, combined content metadata lines, and standalone time metadata
- approved, unapproved, and fallback author rendering now have a single canonical template path
- friendly timestamp formatting now emits standardized `<time datetime=\"...\">...</time>` output

## Slice 4: Surface Adoption

Focus:

- apply the shared primitive consistently across visible content pages

Checklist:

- update `templates/partials/post_card.php`
- update `templates/pages/post.php`
- update board/thread/activity surfaces
- update username and profile-adjacent summary lists where poster/owner metadata should be visible
- decide whether user-directory rows should remain aggregate-only or also show last activity
- remove duplicated author-rendering branches from page templates
- keep output wording consistent across all touched pages

Expected outcome:

- the site presents one coherent metadata language across its main read surfaces

Implementation status:

- board, thread, post, and activity pages now render through shared metadata helpers
- duplicated author-link logic has been removed from `post.php` and `post_card.php`
- username summary surfaces now show standardized started/posted metadata instead of bare links only
- thread and board surfaces now expose both poster and time context, including last activity

## Slice 5: Tests And Regression Coverage

Focus:

- prove the metadata contract and rendering stay stable

Checklist:

- add parser tests for the timestamp header
- add read-model tests for indexed and derived timestamp fields
- add smoke coverage for friendly timestamps on representative surfaces
- add smoke coverage for approved and unapproved author rendering through the shared path
- verify content still links to `/user/<username>` and `/profiles/<slug>` correctly
- verify surfaces that should show metadata do show it consistently

Expected outcome:

- the standardized display survives future template and query changes

Implementation status:

- smoke coverage now asserts friendly metadata output on thread, post, activity, API, and RSS surfaces
- write-path smoke coverage now checks that canonical post files include `Created-At`
- the local PHP test runner can execute all test files without helper redeclaration fatals

## Optional Follow-On Improvements

These are not required for the first retained slice.

- add client-side relative-time enhancement while preserving server-rendered absolute fallback text
- add `<time>` elements with machine-readable `datetime` attributes if they are not included in the first pass
- add localized formatting later if localization becomes a product goal
- extend the same metadata primitive to downloads, feeds, or API text output

## Recommended Order

1. define the canonical timestamp contract
2. index the metadata in the read model and application queries
3. build the shared display primitive
4. adopt it across page surfaces
5. add regression coverage

## Summary

This work should be implemented as a small sequence of coherent slices, not as a one-shot template sweep.

The key reason is:

- standardized display is presentation work
- friendly timestamps also require new canonical and read-model data

So the right first version is:

- make timestamps authoritative in canonical data
- expose them consistently in queries
- render author/owner/poster and time metadata through one shared path
