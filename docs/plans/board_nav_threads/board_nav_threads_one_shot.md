Cycle: `v3/board_nav_threads`; Mode: one-shot; Record/index: [plans index](../README.md). Follow-up to [zen_hero](../zen_hero/zen_hero_step1_solution_assessment.md) (PR #350, merged).

Debug mode: enabled

Effort study: `railspike-effort` / `v3/board_nav_threads`; host ledger [board_nav_threads.md](../effort_ledger/board_nav_threads.md). Standing P2 enrollment.

**Cycle estimate: 0.10**

Status: implemented; PR #385 open; next permitted action: maintainer review.

Original request (2026-11-23): separate the board from the front page, because the Zen memes at the top of the board every time are annoying. After discussion: "i think pointing board to threads is good for now. i'm ok with landing on them initially, just not every time i click board", then "please one-shot to pr".

Authorization: the maintainer asked for a one-shot to PR. Exclusions: a separate front page, login-dependent home pages and a start-page preference were discussed and not authorized here.

Outcome and work type: application change. The Board nav item links to `/threads/`, the same board without the Zen hero. `/` (domain, logo) still shows the hero. QDB's Welcome nav item is unchanged.

Reuse decisions: no new route or component; `/threads/` already renders the board without the hero (`zenHome` in `Application.php`), and the offline reader and service worker already treat `/`, `/threads` and `/threads/` as the board. Nav icon rules in `theme-nav-icons.css` add a `/threads/` selector beside `/` in each icon set.

Concepts touched: none changed.

Process note: the record was written after the implementation commit and PR, because AGENTS.md was read late; the estimate below is therefore not prospective.

Starting revision and branch: `fix/board-nav-threads` from `origin/main` `9ae843c`.

Dated execution history:
- 2026-11-23: `TemplateRenderer` Board href `/` to `/threads/`; tag page and offline reader "Back to Board" links likewise; icon selectors in all four rule groups (shared list, pixel, smooth, Bubblegum).
- 2026-11-23: tests updated for the new href (`LocalAppSmokeTest`, `AppNavigationPlacementTest`, `OfflineSnapshotPresentationTest`).

Study history:
- 2026-11-23 UTC, closeout: retrospective scope assessment **0.10**; no prospective intake recorded (see process note). Claude, single exposed rater. Maintainer judgment pending; measured time unknown.

Verification:
- `./v3 test --smoke LocalAppSmokeTest OfflineSnapshotPresentationTest NavIconSetsTest AppNavigationPlacementTest`: 487 run, 487 passed (after updating three assertions on the old href). The full suite was not run.
- Local PHP server, system Chrome: `/threads/` renders Board active with href `/threads/`; the Board icon is present in Word97, Gloss and Bubblegum on `/threads/` and `/` (screenshots checked for Gloss and Bubblegum).

Outcome: delivered. Clicking Board no longer brings back the Zen memes; the home page still opens with them.

Attribution: Claude · 2026-11-23 UTC · Model: claude-opus-5-5
