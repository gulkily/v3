# Offline Board Navigation Step 2 Feature Description

## Problem

Offline normal navigation currently supports saved threads and Board state at
the root fallback, but not the normal Board control URLs or Tags index/result
URLs. Readers cannot consistently use those normal navigation paths without
reconnecting, despite their views being derivable from the saved public archive.

## User stories

- As a reader, I want each read-only Board view to work offline so that I can browse my saved public threads in the order I chose.
- As a reader, I want the Tags index and tag-result URLs to work offline so that I can discover and follow saved topics without a connection.
- As a reader, I want archive-local results and empty states to be clear so that I do not mistake them for complete live-forum results.
- As an operator, I want offline navigation to retain its public-data and online-only boundaries so that no personalized or write capability is exposed.

## Core requirements

- Support normal read-only Board routes, including the `/threads/` All/Liked and Newest/Oldest/Top combinations, from the bounded saved public archive.
- Support the normal Tags index and individual tag-result URLs from that same archive; an absent or empty saved tag must state that reconnecting may show live results.
- Preserve URL-driven navigation among Board, tags, and saved threads, while labeling content with the saved-archive timestamp and limited scope.
- Keep New Post, every write, profiles, search, and all other unsupported or personalized routes online-only with a clear reconnect path.
- Preserve the existing public-snapshot bounds and approved-members-only exclusion; do not cache rendered Board or tag documents.

## Shared component inventory

- **Bounded public snapshot and offline reader:** extend as the canonical offline data source and archive presentation layer; no second cache or route-specific page copies.
- **Board page and shared Board controls:** reuse the established All/Liked and Newest/Oldest/Top semantics for offline Board states; extend the offline presentation to honor them.
- **Tags index and tag-result pages:** reuse their existing grouping, preview, counts, and thread-result semantics; extend the offline presentation for the normal tag URLs.
- **Normal-route offline navigation boundary:** extend the existing network-first fallback to the additional read-only Board and tag URLs; retain its online-only exclusions.
- **Thread presentation:** reuse the existing saved-thread navigation target from Board and tag results; no new thread-data surface is needed.

## User flow

1. A reader visits the public site online and the bounded public archive is saved.
2. Offline, they open or select a normal Board view or Tags URL.
3. The view renders the matching archive-local threads, tag data, and saved timestamp.
4. They open a saved thread or change a read-only Board/tag view using normal URLs.
5. An unavailable tag, thread, or unsupported destination explains that reconnecting is required; online behavior resumes when connected.

## Success criteria

- With networking disabled after an online visit, every supported Board filter/sort URL, `/tags/`, and a saved `/tags/{tag}` URL renders from the saved archive.
- Board ordering/filtering and tag counts, previews, and result lists match the archive contents; absent archive results show an explicit limited-scope/reconnect state.
- Board/tag thread links open saved threads when present, and every write or unsupported route remains unavailable offline.
- Online Board, Tag, and write behavior remains unchanged, and approved-members-only deployments do not register or serve offline navigation.
