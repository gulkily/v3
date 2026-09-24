# Tools Page Bookmarklets Step 3 Development Plan

## Stage 1
- Goal: Add the public `/tools/` route and shared-nav entry so the Tools page becomes a first-class surface.
- Dependencies: Approved Step 2 feature description; existing `Application` route dispatch and `TemplateRenderer` nav contract.
- Expected changes: Add `GET /tools/` handling in the application layer; add a renderer-backed page entry point; extend the shared nav item list with a Tools link and active-section support.
- Verification approach: Request `/tools/` directly and confirm a normal page response; confirm the main nav shows Tools and highlights it on the Tools page.
- Risks or open questions:
  - Decide whether Tools should use its own active section or share an existing one; prefer its own section to avoid nav ambiguity.
- Canonical components/API contracts touched: `Application` route table; `TemplateRenderer` shared nav contract; shared layout/nav rendering.

## Stage 2
- Goal: Render the first Tools page with bookmarklets as the initial tools category.
- Dependencies: Stage 1 route and nav support; approved bookmarklet-first product direction.
- Expected changes: Add a `tools.php` page template using the shared card/page pattern; provide a small canonical bookmarklet data set with clear labels, descriptions, and bookmarklet href values inspired by the `pollyanna` clip/rip families.
- Verification approach: Load `/tools/` and confirm the bookmarklets render as usable links with explanatory copy; verify the page stays understandable without reading source.
- Risks or open questions:
  - Bookmarklet copy must explain same-window versus new-window behavior clearly enough for non-technical users.
  - Bookmarklet href generation should stay simple and deterministic for V1 rather than introducing a more abstract tooling layer too early.
- Canonical components/API contracts touched: shared page template contract; card/browse presentation pattern already used by `users` and `instance` pages.

## Stage 3
- Goal: Cover the new page with smoke-level verification and route-surface consistency checks.
- Dependencies: Stages 1 and 2 completed.
- Expected changes: Add smoke coverage for `/tools/`, nav visibility, and the presence of the initial bookmarklet entries; confirm unrelated routes and shared nav behavior remain intact.
- Verification approach: Run the relevant PHP test suite or targeted smoke tests; verify `/tools/` content, nav link presence, and at least one representative bookmarklet label/href in rendered HTML.
- Risks or open questions:
  - If exact bookmarklet payload strings are tested too rigidly, harmless copy cleanup may become noisy; prefer stable, meaningful assertions.
- Canonical components/API contracts touched: route smoke-test coverage; shared navigation expectations; public HTML route contract for `/tools/`.
