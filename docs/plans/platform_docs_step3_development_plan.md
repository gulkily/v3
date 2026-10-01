# Public Platform Docs — Step 3 Development Plan

## Stage 1 — Documentation root and curated catalog

- Goal: define the public documentation root plus the categories and paths
  promoted on the catalog.
- Dependencies: approved Step 2.
- Expected changes: add a docs-root path policy and a small catalog contract
  with document title, category, path, and optional description; initially
  cover architecture, extension, operator, and CLI guidance.
- Verification approach: tests prove catalog paths and a representative
  uncatalogued document stay under `docs/` and resolve to readable Markdown.
- Risks or open questions:
  - A moved catalog document needs a catalog update; uncatalogued docs remain
    directly reachable by their safe source path.
- Canonical components/API contracts touched: new public-doc catalog; existing
  app checkout documentation files.

## Stage 2 — Public index and document pages

- Goal: let any visitor browse the catalog and read any safe document path.
- Dependencies: Stage 1 catalog.
- Expected changes: add public Docs index/detail routes and rendered Markdown
  pages; every detail page visibly renders its exact repository-relative source
  path, while traversal and non-Markdown requests are rejected.
- Verification approach: route/render tests cover public index, category links,
  catalogued and uncatalogued documents, visible source paths, and invalid
  paths.
- Risks or open questions:
  - Markdown must render safely without allowing untrusted raw HTML.
- Canonical components/API contracts touched: Application route dispatch,
  public page layout, template renderer, new docs controller/templates.

## Stage 3 — Public discovery and static release support

- Goal: make Docs discoverable and publish the index plus curated pages in a
  normal public release.
- Dependencies: Stage 2 routes.
- Expected changes: extend the canonical public discovery/navigation surface;
  include the index and every catalog document in static artifact publication;
  safe uncatalogued docs remain available through the public app route.
- Verification approach: build a clean static release and confirm Docs links,
  source paths, and every catalog URL resolve from the release.
- Risks or open questions:
  - A catalog change must be accompanied by the normal static rebuild.
- Canonical components/API contracts touched: shared navigation/Tools discovery,
  StaticArtifactBuilder, FrontController static serving.

## Stage 4 — Public-boundary regression coverage

- Goal: prevent catalog drift or paths escaping the public documentation root.
- Dependencies: Stages 1–3.
- Expected changes: add focused tests for docs-root-only resolution, source-path
  display, safe Markdown output, and public anonymous access.
- Verification approach: targeted docs/static smoke suite plus existing public
  route smoke tests.
- Risks or open questions:
  - Future source-code browsing needs a separately reviewed allowlist and is
    not enabled by these routes.
- Canonical components/API contracts touched: public docs catalog/controller,
  existing routing and smoke-test harness.

Reply **Approved Step 3** to begin implementation.
