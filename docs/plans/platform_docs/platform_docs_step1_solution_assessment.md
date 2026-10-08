# Public Platform Docs — Step 1 Solution Assessment

## Problem

The platform's architecture and extension guidance live in the repository but
are not comfortably discoverable from the public site, and every displayed
document must identify its repository source path.

## Option A — Curated public docs library

- Render public Markdown under `docs/` as site pages, with a curated catalog
  for reader-friendly sections and discovery.
- Display the exact repository-relative source path on every document page.
- Pros: open-repository friendly, low-maintenance, static-release friendly,
  and makes the platform easy to understand without a per-file gate.
- Cons: needs a documentation-root boundary and Markdown presentation surface.

## Option B — Public docs and full source browser together

- Add a file tree and renderer for selected docs and application-code roots.
- Display source paths for both document and code pages.
- Pros: immediately supports transparent code exploration and cross-links.
- Cons: substantially broader path, size, binary, and sensitive-file policy;
  risks making the first public docs experience harder to navigate.

## Option C — Link readers to GitHub

- Add a small site page linking into the repository documentation on GitHub.
- Pros: minimal implementation and GitHub already provides paths/history.
- Cons: does not make the knowledge part of the site or provide an integrated,
  approachable docs experience.

## Recommendation

Choose **Option A**: ship a public curated docs library first, with source
paths visibly rendered and links to the corresponding GitHub file where
available. The catalog is for discovery, not a per-file access gate: any safe
Markdown path under `docs/` remains browsable. Keep public code browsing as a
separately planned security and UX decision.

Reply **Approved Step 1** to continue.
