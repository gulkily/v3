# Offline Board Navigation Step 1 Solution Assessment

## Problem statement

Allow every read-only Board filter/sort view and Tags index/result view to work
offline from the bounded public archive, while keeping New Post and every other
write or personalized route online-only.

## Option A — Extend the snapshot reader for normal Board and tag URLs

- Pros:
  - Keeps one bounded, public-only archive as the offline source of truth.
  - Preserves normal Board and tag URLs without caching rendered page copies.
  - Lets every offline filter, sort, and tag result operate consistently on
    the saved archive.
- Cons:
  - Requires parity work across Board controls, tag navigation, empty states,
    and deep links.
  - Results must clearly mean “in the saved archive,” not the complete live
    forum.

## Option B — Cache rendered Board and tag pages

- Pros:
  - Reuses the online presentation for the exact pages cached.
  - May require less snapshot-query UI initially.
- Cons:
  - Cannot reliably cover every filter combination or tag URL.
  - Creates duplicate, inconsistently fresh cached content and an unpredictable
    cache-size boundary.

## Option C — Publish separate static offline pages for each view

- Pros:
  - Makes each supported offline route a simple page response.
  - Can closely match the server-rendered views.
- Cons:
  - Duplicates archive data across pages and grows rapidly with tags and
    filter combinations.
  - Couples offline coverage to static-release rendering and makes archive
    refreshes less focused.

## Recommendation

Choose **Option A**. Extend the existing snapshot reader to render normal
read-only Board and tag URLs from the saved archive. Treat all filters, sorts,
and tag results as archive-local; show an honest empty state when a matching
live thread or tag is not saved. Keep New Post, all writes, profiles, search,
and other personalized routes online-only.
