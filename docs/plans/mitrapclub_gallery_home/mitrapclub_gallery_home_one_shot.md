# Gallery as mitrapclub's home page

Cycle v3/mitrapclub_gallery_home · One-shot · [Plans](../README.md)

Effort study: `railspike-effort` / `v3/mitrapclub_gallery_home`; host ledger [mitrapclub_gallery_home.md](../effort_ledger/mitrapclub_gallery_home.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.10**

Status: implemented, PR open; next permitted action: maintainer merge and deploy.

## Original request

"also, for mit rap club, can you make gallery the default home page" (Ilya, 2026-11-25).

## Outcome

- New site-profile setting `homePage` (`board`, the default, or `gallery`; validated). `SiteProfileRegistry::homePage()` reads it. The mitrapclub profile sets `gallery`.
- On such a site, a bare `/` renders the same page as `/gallery/` (`RecentPageController::gallery()`, so the Gallery nav item is the active one). The board stays at `/threads/`, where the Board nav already points. `/` with `view`, `sort`, `with`, `page` or `format` (RSS) still means the board, and so does `/` with the image gallery flag off.
- Static `/index.html` already gets rebuilt on every new thread or reply (`StaticArtifactInvalidator`), so a baked home page goes stale no more than the board did.

## Reuse decisions

Same pattern as `gettingStarted` (site-profile key, validated, mitrapclub only). Reuses the gallery controller and template; no new page or component.

## Checks

- `./v3 test --smoke BoardListTest SiteProfileRegistryTest`: 505/505 passed, including the new `testGalleryHomeSiteShowsGalleryAtTheBareHomePage`. Full suite not run.
- mitrapclub.com already serves /gallery/ and links it in the nav, so the gallery flag is on there.
