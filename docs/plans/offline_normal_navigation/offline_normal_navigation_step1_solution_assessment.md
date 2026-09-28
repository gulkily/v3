# Offline Normal Navigation Step 1 Solution Assessment

## Problem statement

Allow recent public content to remain readable at its normal board and thread URLs while offline, without broadening the cached public-data boundary.

## Option A — Cache normal rendered pages

- Pros:
  - Preserves existing server-rendered presentation exactly.
  - Requires little offline-specific UI.
- Cons:
  - Cache size and recency are difficult to bound predictably.
  - Cached routes can become inconsistent with one another.
  - Does not scale to all recent thread/post URLs without downloading a large release.

## Option B — Render supported normal URLs from the public snapshot

- Pros:
  - Reuses the existing bounded, public-only SQLite snapshot.
  - Gives board and saved-thread URLs one coherent offline data source.
  - Keeps unsupported routes and all writes explicitly online-only.
- Cons:
  - Requires offline route handling and presentation parity for the supported screens.
  - The offline view must make snapshot age and unavailable content clear.

## Option C — Cache pages opportunistically, with snapshot fallback

- Pros:
  - Can preserve exact pages a visitor recently viewed.
  - Snapshot covers some content that was not individually visited.
- Cons:
  - Combines two freshness models and ambiguous offline behavior.
  - Increases cache, privacy, testing, and recovery complexity.

## Recommendation

Choose **Option B**. Extend the bounded snapshot approach to support normal recent-board and saved-thread URLs offline; keep profile, search, feeds, tools, and write interactions online-only. This directly delivers the intended experience while preserving the established content and privacy limits.
