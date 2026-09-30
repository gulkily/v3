# Offline Snapshot Publish Step 1 Solution Assessment

## Problem statement

Operators need a fast, safe command that refreshes everything uniquely needed
for offline reading without rendering every static post page through
`build-static`.

## Option A — Replace the snapshot inside the active static release

- Pros:
  - Smallest command and no routing changes.
  - Reuses the currently published release path.
- Cons:
  - Mutates an otherwise immutable release and can race with a full static
    publication.
  - Makes rollback and partial-publication behavior ambiguous.

## Option B — Publish an independent, atomically replaced offline snapshot

- Pros:
  - Builds only the bounded public SQLite snapshot from the current read model;
    it does not render post HTML.
  - Keeps full static releases immutable and preserves the last valid snapshot
    if publishing fails.
  - Lets the public snapshot route prefer the fast publication, with the static
    release snapshot as a compatible fallback.
- Cons:
  - Adds a small second publication location and route-resolution contract.
  - Depends on an already-current read model; it does not fetch or rebuild site
    data.

## Option C — Create a snapshot-only copy of each static release

- Pros:
  - Retains a single release-shaped publication model.
- Cons:
  - Requires copying or recreating static release contents and loses most of
    the time savings.
  - Still creates coordination complexity with full static publishing.

## Recommendation

Choose **Option B**. Add an explicit fast offline-snapshot publish command that
atomically publishes the bounded public snapshot from the existing read model,
without rendering post pages. It must retain the public-only and
approved-members-only boundaries, report that deployed reader assets (including
the SQLite runtime) remain a separate prerequisite, and leave `build-static`
as the complete-site build path.
