# Favicon in light theme — one-shot

Cycle: `v3/favicon_light_theme`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](favicon_light_theme_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/favicon_light_theme`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/favicon_light_theme.md))
Extended estimates: enabled (no real alternatives or work units worth estimating separately)

**Cycle estimate: 0.10** (intake 0.15)

Status: plan written; implementation not started. Next permitted action: planning commit, then redraw the favicon.

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

Planning commit: pending.

## Dated execution history

- 2026-11-23T06:22Z: plan written after reading `FaviconRegistry`, the layouts' favicon links, the tests that name favicons, the app-icons record, and decoding `public/favicon.ico`.

## Study history

- Intake, 2026-11-23T06:15Z: **0.15** whole-cycle projection, scope v1, R1/P2, recorded before inspecting the implementation. Single Claude rater; exposed only to the item text.
- Execution entry, 2026-11-23T06:22Z: revised to **0.10**. The fix is one asset and one test assertion; no code path changes.

*— Claude · 2026-11-23 UTC · Model: Claude Opus 5.5*
