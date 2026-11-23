# Favicon in light theme — one-shot

Cycle: `v3/favicon_light_theme`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](favicon_light_theme_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/favicon_light_theme`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/favicon_light_theme.md))
Extended estimates: enabled (no real alternatives or work units worth estimating separately)

**Cycle estimate: 0.10** (intake 0.15)

Status: technically complete on `fix/favicon_light_theme`; pushed for review, not merged or deployed. Next action: maintainer review, merge, deployment and a look at a real browser tab.

## Request and authorization

Original request: [PF-20261111-B02](../../feedback/items/PF-20261111-B02.txt), "The favicon looks odd in light theme." Source thread (2026-11-04, the whole thread, no replies): https://zenmemes.com/threads/thread-20261104200237-80b737bc, "the favicon looks odd in light theme #meta".

Authorization: the maintainer, 2026-11-23 (Claude session): "Work on this v3 backlog item as one Railspike one-shot cycle. [...] Read first: docs/agents/backlog_to_pr.md, docs/feedback/items/PF-20261111-B02.txt. Begin now. Go all the way to an open pull request, or as close as you can get." Under the backlog procedure this authorizes one-shot execution, pushing the branch, opening the PR and a thread reply. Exclusions: merge, deployment, the other sites' favicons, home-screen icons.

Item checks (2026-11-23): `Workflow: untriaged`; no remote branch or open PR names it or favicons. It is a real request, not done (the favicon has not changed since May 2026), and no other item covers it.

## Outcome and work type

Application asset change. On ZenMemes, the browser tab icon looks clean on light browser chrome and light site themes, and still reads on dark ones: a rounded slate tile with a crisp white Z, drawn at 16, 32 and 48 px.

## Approach and risks

Diagnosis. `public/favicon.ico` holds one 32 px image: a slate (`#1f2937`) square to the edges, with a white Z and a gold glow. Browsers draw tab icons at 16 px. Downscaled, the glow turns into an olive-brown fringe around the Z, and the hard square corners sit as a dark block on a light tab strip. The thread gives no more detail, so this is the most likely reading of "odd".

Options considered:

- A (chosen). Redraw the default favicon as a multi-size ICO: rounded slate tile, transparent corners, white Z without the glow, each size drawn for its own pixel grid. Same path, same markup. Reversible by restoring one file.
- B. An SVG favicon that swaps colours with `prefers-color-scheme`. That follows the browser's colour scheme, not the site theme the reader picked, and Safari ignores SVG favicons. More moving parts for an uncertain gain.
- C. Swap the favicon in the browser when the reader picks a light theme. Needs script before paint on every page for a tab icon; out of proportion to the report.

Risks: the old icon stays in browser caches for a while (the favicon path is not fingerprinted); that is acceptable and needs no change. The home-screen PNGs keep the glow; at 180 px and up it reads well, so they stay as they are.

## Reuse decisions

- `FaviconRegistry` (`src/ForumRewrite/View/FaviconRegistry.php`) keeps mapping the default slot to `/favicon.ico`; no new path, so docs publication, static artifacts, the manifest and both layouts pick up the new file unchanged.
- The colours and Z proportions come from the existing favicon and `public/assets/app-icon-*.png`, so tab and home-screen icons stay one mark.
- `PresentationSlotRegistryTest` already checks the favicon files; extend it rather than add a test class.

## Concepts touched

None in the concept inventory.

## Completion conditions and verification plan

- Normal entry: open any ZenMemes page; the tab shows the new icon. On a light tab strip it shows a rounded tile with a clean Z and no muddy fringe; on a dark strip the tile still reads.
- Test: `PresentationSlotRegistryTest` checks the default favicon holds 16 and 32 px images.
- `./v3 test --smoke PresentationSlotRegistryTest LocalAppSmokeTest::testFrontControllerDerivesManifestAndWorkerFromTheActiveProfile`. Full suite not run.
- Render check: composite each size on light and dark backgrounds and look at it. A real browser tab cannot be checked from here; the reported device and browser are unknown, so the original condition is not replayed.

Starting revision and branch: `e32bef64ed367accdf10501d173dc534b7460541` on `main`; branch `fix/favicon_light_theme`.

Planning commit: `c1391b64e` (2026-11-23T06:24Z), containing only this record and its index link.

## Dated execution history

- 2026-11-23T06:22Z: plan written after reading `FaviconRegistry`, the layouts' favicon links, the tests that name favicons, the app-icons record, and decoding `public/favicon.ico`.
- 2026-11-23T06:24Z: planning commit `c1391b64e` confirmed. Drew the new icon with a scratch script outside the repo: each size drawn on its own pixel grid at 16x supersampling, slate `#1f2937` tile with corner radius about 22%, white `#f8fafc` Z with strokes on whole pixels, no glow. Saved as one ICO with 16, 32 and 48 px images. The script is not committed, since nothing rebuilds the file (same as the app icons).
- 2026-11-23T06:30Z: added `PresentationSlotRegistryTest::testDefaultFaviconCarriesTabSizedImages`. It fails on the old file and passes on the new one.
- 2026-11-23T06:32Z: item updated with the diagnosis and a `Done when:`; `./v3 feedback check` and `./v3 feedback index` run.

## Study history

- Intake, 2026-11-23T06:15Z: **0.15** whole-cycle projection, scope v1, R1/P2, recorded before inspecting the implementation. Single Claude rater; exposed only to the item text.
- Execution entry, 2026-11-23T06:22Z: revised to **0.10**. The fix is one asset and one test assertion; no code path changes.
- Closeout, 2026-11-23T06:35Z: delivered scope v1. Retrospective **0.08**. Maintainer fit and acceptance pending.

## Outcome

Delivered: `public/favicon.ico` redrawn as a 16, 32 and 48 px icon, a rounded slate tile with a crisp white Z and no glow. Every page, docs build and static artifact keeps the same path, so nothing else changed. Not delivered (out of scope): favicons for QDB, chouse.club and the Rap Club; the home-screen PNGs, which keep their glow; a theme-aware or SVG favicon (options B and C), left as follow-ups if the tile still looks wrong in some theme.

## Verification

- `./v3 test --smoke PresentationSlotRegistryTest LocalAppSmokeTest::testFrontControllerDerivesManifestAndWorkerFromTheActiveProfile`: 492 of 492.
- `./v3 test PresentationSlotRegistryTest IndependentDocsPublicationTest LegacyDocsMigrationTest` (the docs tests copy and hash the favicon): 14 of 14.
- The new test fails against the old `favicon.ico` (checked by swapping the file back) and passes against the new one.
- Render check: old and new icons composited at 16 and 32 px on white, Chrome's light grey tab strip, the site's cream (`#f4f1e8`) and a dark strip, then viewed. The new icon has no olive fringe and transparent corners on light backgrounds, and the Z still reads on dark.
- `./v3 feedback check`: 0 problems; `./v3 feedback index` regenerated.
- Not run: the full suite. Not checked: a real browser tab, and the reporter's device and browser (unknown), so the reported condition is not replayed.

## Final review

Diff checked: one binary asset, one test method, the item, the feedback index, this record, its debug log and ledger, and the plans index. No code paths, markup or other sites' assets changed. No duplicated components; the `sizes="32x32"` hint on the icon link stays correct, since the file still holds a 32 px image.

## Commit, merge and publication status

Planning commit `c1391b64e`; completion commit pending at the time of writing (its hash is reported in the PR). Not merged, not deployed.

*— Claude · 2026-11-23 UTC · Model: Claude Opus 5.5*
