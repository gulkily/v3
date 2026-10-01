# Offline Board Navigation Step 3 Development Plan

## Stage 1 - Admit supported normal navigation routes
- Goal: Send only supported Board and Tag navigations to the cached reader after a network failure.
- Dependencies: Existing root-scoped network-first worker and cached reader shell.
- Expected changes: Extend the navigation policy for both Board routes and valid Tags index/result routes; version the cache; leave writes and personalized routes excluded.
- Verification approach: Worker route-contract checks cover accepted Board/tag URLs and rejected compose, profile, search, and malformed routes.
- Risks or open questions: A broad matcher could expose an unsupported route offline.
- Canonical components/API contracts touched: `supportsOfflineNavigation(URL)`, root service-worker cache lifecycle, normal-route policy.

## Stage 2 - Share archive tag grouping
- Goal: Derive Tag groups from the saved archive with the same public semantics as online.
- Dependencies: Existing snapshot `threads` tag/label fields and shared snapshot presentation layer.
- Expected changes: Add archive row tag/label hydration and a `tagGroups(database): TagGroup[]` presentation contract; preserve existing tag ordering, counts, and five-thread previews; no database changes.
- Verification approach: Fixture-driven browser/DOM checks cover board tags, labels, ordering, counts, previews, and an empty archive.
- Risks or open questions: Malformed archived JSON must behave as no tag rather than breaking the reader.
- Canonical components/API contracts touched: Public snapshot schema, `TagGrouping` semantics, snapshot presentation API.

## Stage 3 - Render canonical Board control URLs
- Goal: Make every read-only Board filter/sort state work at normal Board routes while offline.
- Dependencies: Stage 1 and existing Board list/control renderer.
- Expected changes: Recognize both Board paths, preserve the current Board path and query state when controls change, and retain archive-local empty and saved-thread behavior.
- Verification approach: Fixture-driven checks cover All/Liked × Newest/Oldest/Top at `/` and `/threads/`, plus history and saved-thread links.
- Risks or open questions: Offline controls must not silently redirect a canonical `/threads/` URL to another route.
- Canonical components/API contracts touched: `BoardViewOptions` semantics, normal Board URLs, `renderOfflineBoard(database)`.

## Stage 4 - Render the Tags index
- Goal: Make the normal Tags index discoverable from the saved archive while offline.
- Dependencies: Stages 1-2.
- Expected changes: Add `/tags/` route presentation with tag headings, archive-local counts, five-thread previews, and normal tag/thread destinations; show a bounded-archive empty state.
- Verification approach: Fixture-driven checks cover tag ordering, preview limit, normal destinations, and no-tags state.
- Risks or open questions: Tag labels and board tags must remain deduplicated per thread.
- Canonical components/API contracts touched: Tags-index presentation, `tagGroups(database)`, normal `/tags/` URL.

## Stage 5 - Render individual tag results
- Goal: Make normal saved tag-result URLs work offline.
- Dependencies: Stages 1-2 and Stage 4.
- Expected changes: Add `/tags/{tag}` route presentation for all matching archive threads, Back to Tags/Board navigation, and an explicit not-in-snapshot reconnect state; planned contract: `renderOfflineTag(database, tag)`.
- Verification approach: Fixture-driven checks cover a populated tag, a tag available only through a label, a missing tag, and saved-thread navigation.
- Risks or open questions: A live tag absent from the bounded archive must not be represented as a definitive site-wide absence.
- Canonical components/API contracts touched: Tag-result presentation, normal `/tags/{tag}` URLs, thread-detail destination contract.

## Stage 6 - Document and verify the boundary
- Goal: Record supported navigation and prove it remains bounded and recoverable.
- Dependencies: Stages 1-5.
- Expected changes: Extend offline-reading regression coverage and runbook recovery guidance for Board controls and Tag routes; no database changes.
- Verification approach: Script syntax checks, focused offline route/presentation tests, full suite, manual online-to-offline route smoke, and approved-members-only regression.
- Risks or open questions: Browser-held public snapshots remain non-revocable and require the existing recovery guidance.
- Canonical components/API contracts touched: Offline Reading Runbook, public-snapshot privacy boundary, service-worker registration exclusion.
