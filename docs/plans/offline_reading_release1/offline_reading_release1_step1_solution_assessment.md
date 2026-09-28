# Offline Reading Release 1 Step 1 Solution Assessment

## Problem Statement

Visitors who have previously loaded zenmemes.com need to read a bounded set of recent public forum content without a connection, without caching private or operational data or changing canonical writes.

## Option A: Precache the complete public static release

Pros:
- Reuses existing static board, thread, post, profile, and tag artifacts.
- Gives broad, deterministic offline availability for a cached release.

Cons:
- Initial download and cache size grow with all public history.
- The cached release becomes stale immediately after a later publication.
- Requires careful exclusion of private or personalized pages.

## Option B: PWA shell plus recent HTML-page snapshot

Pros:
- Reuses normal rendered pages and needs little new offline presentation code.
- Can preload recent listings and a bounded set of recent thread pages, then retain other visited pages.
- Fingerprinted assets already fit service-worker caching well.

Cons:
- Requires maintaining a route/asset manifest for every page in the snapshot.
- Repeats shell and asset data across cached HTML pages.
- Snapshot bounds are indirect: a thread's full content and related metadata are spread across routes.

## Option C: PWA shell plus a public, bounded SQLite snapshot

Pros:
- The browser already downloads and queries SQLite locally through the existing SQLite Viewer and sql.js runtime.
- A purpose-built snapshot can precisely include the recent thread list, all visible replies for those threads, and needed display-profile data within a size budget.
- One cached data file can power a small offline thread-list and thread-reader view.
- Keeps normal online pages and writes authoritative and unchanged.

Cons:
- The current `read_model.sqlite3` cannot be precached: it is a complete database that includes operational/workflow tables, not a public offline contract.
- Requires a new sanitized snapshot format and a small client-side offline reader.
- Offline reader scope must remain deliberately narrow; full online-page parity would enlarge the release.

## Recommendation

Recommend Option C.

- Precache the PWA shell, browser SQLite runtime, and a separately generated public snapshot containing the 50 most recently active visible threads, every visible reply in those threads, and necessary display metadata, subject to a storage budget.
- Refresh the snapshot on successful later online visits; label it with its generation time and preserve the last complete copy until replacement succeeds.
- Limit Release 1 offline UI to recent-thread browsing and reading. Exclude API responses, private/personalized pages, account/tools pages, writes, and the complete existing read-model download.
- This remains a viable single FDP cycle only with that narrow reader scope. Offline writes, general offline page parity, and a whole-history download remain later releases.
