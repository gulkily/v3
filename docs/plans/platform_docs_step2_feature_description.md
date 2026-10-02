# Public Platform Docs — Step 2 Feature Description

## Problem

Platform architecture and extension guidance are public in the repository but
not readable as a coherent part of the site.

## User stories

- As a visitor, I want to browse platform documentation on the site so that I
  can understand how Zenmemes is built and extended.
- As a developer, I want every rendered document to show its repository source
  path so that I can find and improve the authoritative file.
- As an operator, I want public docs to update with a normal release so that
  visitors are not shown stale or private material.

## Core requirements

- Provide a public, curated documentation index and document pages; no identity
  or approval is required.
- Browse Markdown under the public `docs/` root, with a curated index grouped
  into understandable categories.
- Display the exact repository-relative source path on every document page.
- Restrict requests to safe `.md` paths under `docs/`; reject traversal,
  non-document roots, and oversized files.
- Publish the index and curated documents through the normal static-release
  flow; public source-code browsing is out of scope for this feature.

## Shared component inventory

- **Shared public page layout/navigation:** reuse and extend the canonical
  layout and public navigation for the Docs destination.
- **Tools index and registry:** extend the existing public discovery surface to
  link to Docs rather than introduce a disconnected page.
- **Static artifact publication:** reuse the normal public release path so docs
  receive the same deployment and cache behavior as other public pages.
- **Existing raw source endpoint:** do not reuse it for docs; it exposes
  canonical content files, while this feature needs a curated app-document set.

## User flow

1. A visitor opens Docs from the public site.
2. They choose a category and document from the curated index.
3. They read the rendered document and see its source path.
4. They can use that path to locate the authoritative repository file.

## Success criteria

- `/docs/` is publicly reachable and lists the approved initial documents.
- Every rendered document visibly includes its exact source path.
- A safe uncatalogued Markdown path under `docs/` is browsable; a path outside
  that root is not.
- A normal static release serves the index and every curated document.

Reply **Approved Step 2** to continue.
