> **Feature plan:** [Step 1](./zen_hero_step1_solution_assessment.md) · [Step 2](./zen_hero_step2_feature_description.md) · [Step 3](./zen_hero_step3_development_plan.md) · [Step 4](./zen_hero_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/zen_hero`; host ledger [zen_hero.md](../effort_ledger/zen_hero.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.35**

Branch `zen-hero`, based on `origin/main` `09a15f6`. Planning commits: Steps 1–3 were committed as each was written and approved (zen_strip precedent), ending with `5af3040` (Step 3 revision, home page only). The ledger and debug log went into those commits too, the same deviation from the planning-commit rule as zen_strip. The branch was local only until the maintainer asked for the PR on 2026-11-21; it is now pushed. Base: `./v3 test --smoke ZenStripTest BoardListTest`, 484 run, 484 passed.

## Study snapshots

- 2026-11-21 UTC: **Approved Step 3** by the maintainer, after the home-page-only revision.
- 2026-11-21 UTC, Step 4 entry: **0.35** projected total, reaffirmed from Step 3. Claude, single exposed rater. Scope v1. Stage originals copied from Step 3 (not new ratings): Stage 1 0.10, Stage 2 0.05, Stage 3 0.15, Stage 4 0.10, Stage 5 0.05.

*— Claude · 2026-11-21 UTC · Model: claude-fable-5-2*

## Stage 1 - Top-scored meme first

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.10**

- Changes:
  - `ReadModel/ZenStrip::load()` orders by `threads.score_total` descending, then newest, and each row carries `score_total`. The approved-tag rule and the limit of twelve are unchanged.
- Verification:
  - `./v3 test ZenStripTest`: 5 run, 5 passed. New `testTheHighestScoreLeadsAndATieGoesToTheNewest` covers: equal scores keep newest first; a higher score leads; a tie goes to the newest; a negative score sorts last. The full suite was not run.
  - Local import of zenmemes.com (`./v3 import-instance zenmemes.com --with-approvals`, then the maintainer's two root keys and claude's key seeded on this local copy only): the query returns the six seeds. "Every commit is a stone" and "Leave it open" have a score of 1, the other four 0. The tie goes to the newer, so **"Every commit is a stone" is the featured meme on today's data**.
- Notes:
  - `score_total` is the number `BoardPageController::compareTop()` sorts by, so the module and Top sort agree.
  - Not done as planned: liking a meme on the local copy and reloading. A like needs a signed record from an approved key, which wasn't set up here. In its place: the imported likes from production already show in `score_total` and decide the order above, and the board renders the module live from that column on each request. The maintainer's first like on production after deploy is the end-to-end check.
  - The local copy needed claude's key seeded by hand: `--with-approvals` plus the two suggested ilyag roots left it pending.
- Estimate details (extended mode only): original scope as Step 3 Stage 1; uncertainty low; no revisions.

*— Claude · 2026-11-21 UTC · Model: claude-fable-5-2*

## Stage 2 - Home page only

**Original estimate: 0.05**

**Retrospective stage-work assessment: 0.05**

- Changes:
  - The board route in `Application.php` passes `zenHome`: true only when the path is `/` and the request names no view and no sort.
  - `BoardPageController::board()` and `boardList()` take `bool $zenHome = false` and hand it to `zenStrip()`, which still also requires page 1, the flag and the profile's tags.
- Verification:
  - `./v3 test BoardListTest`: 15 run, 15 passed. `testZenStripOpensTheFirstPageOnly` became `testZenMemesOpenTheHomePageOnly`: present on `/`; absent on `/threads/`, `/?view=all`, `/?view=liked`, `/?sort=top`, `/threads/?view=all&sort=newest` and page 2; absent with the flag off. The image-card test now renders `/`. The full suite was not run.
  - Fallback case: the test board has no liked threads, so `/` falls back from Liked to All, and the module still shows. The decision comes from what the request asked for, as planned.
- Notes:
  - `zenHome` defaults to false, so any other caller of `board()` (QDB sections) shows no module.
- Estimate details (extended mode only): original scope as Step 3 Stage 2 after the home-page-only revision; uncertainty low; no revisions.

*— Claude · 2026-11-21 UTC · Model: claude-fable-5-2*

## Stage 3 - One featured meme and a row that opens All

**Original estimate: 0.15**

**Retrospective stage-work assessment: 0.15**

- Changes:
  - `templates/partials/zen_strip.php`: the first thread is the featured card (whole image with the tile and original in `srcset`, or its title in large type), linking to its thread. The others sit inside one link to the All page, named "All Zen memes": 48px square thumbnails from the `src`/`srcset` `ListThumbnails` already gives list rows, with empty alt text, and a tiny text tile for a text meme. The All paragraph is gone. One Zen meme means no row.
  - `site.css`: the grid rules are replaced. The module is at most 24rem wide and centred; the row is one 3rem line that cuts off what doesn't fit.
- Verification:
  - `./v3 test BoardListTest ZenStripTest`: 20 run, 20 passed. `testZenMemesOpenTheHomePageOnly` now also covers: no row and no All link with one meme; a better-liked second meme takes the top spot; the row is one link to /tags/zen+meme holding the other meme; the module has exactly two links. The full suite was not run.
  - Local copy, Gloss, at 380x667 and 1280x900: "Every commit is a stone" featured, five thumbnails in one row, no sideways overflow. At 380px the module is 402px tall and the first thread row starts 809px down, about 1.2 screens, against roughly three before.
  - The row's link opens /tags/zen+meme (checked in the markup).
- Notes:
  - On a local dev server, `/` with no query shows the Local development page, and `/threads/` no longer shows the module. To see it locally, open `/?home=1`: any query other than view or sort keeps the home page.
  - The **High risk** from Step 3 stands: the row opens a tag page without pictures until the gallery is built.
- Estimate details (extended mode only): original scope as Step 3 Stage 3; uncertainty low; no revisions.

*— Claude · 2026-11-21 UTC · Model: claude-fable-5-2*

## Stage 4 - Dimmed images in dark themes

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.10**

- Changes:
  - `site.css`: a new theme variable `--media-dim`, 1 by default. The module's images use it as their brightness.
  - Every place that declares a dark colour scheme sets `--media-dim: 0.85`: the system-dark block in `site.css`, and `theme-dark`, `theme-gloss` (Gloss Dark), `theme-midnight`, `theme-arena`, `theme-console`, `theme-forge`, `theme-thermal`, `theme-vapor`, `theme-zalgo`, `theme-chouse` and `theme-mitrapclub`.
- Verification:
  - Listed before editing: `grep "color-scheme: dark" public/assets/*.css` gave 14 places in 13 files. `forte.css` (the standalone paned reader, which never shows the module) was left unchanged.
  - Local copy at 380x667 and 1280x900 in Gloss, Gloss Dark, Bubblegum, Ochre, Word97, Dark and Midnight: no sideways overflow in any; the row is one 48px line of five thumbnails in all. Computed brightness: 1 in Gloss, Bubblegum, Ochre and Word97; 0.85 in Gloss Dark, Dark and Midnight. Gloss Dark and Bubblegum screenshots viewed.
  - First thread at 380px: 809px down in Gloss and Gloss Dark, 785 in Dark, 750 in Ochre, 929 in Bubblegum (all within 1.5 screens of 667px); 1068 in Word97 and 1076 in Midnight, about 1.6 screens, because those themes' headers are taller.
  - `./v3 test --smoke BoardListTest ZenStripTest ThemeRegistryTest Word97ThemeCoverageTest`: 486 run, 486 passed. The full suite was not run.
  - Not rerun: JavaScript off. The module is plain HTML and CSS, as before. `tests/theme_contrast_audit.py` was not run; it checks text colours, and the module's text styles didn't change.
- Notes:
  - No theme needed its own layout fix.
  - A new dark theme must set `--media-dim` itself. CSS can't read the colour scheme into a number, so there is no single place to set it.
  - The variable is general, but only the module uses it in this cycle. Dimming other images is out of scope.
- Estimate details (extended mode only): original scope as Step 3 Stage 4; uncertainty was medium on the number of dark themes (twelve files, more than the three named); no revisions.

*— Claude · 2026-11-21 UTC · Model: claude-fable-5-2*

## Stage 5 - Docs, phone evidence and final review

**Original estimate: 0.05**

**Retrospective stage-work assessment: 0.05**

- Changes:
  - `docs/concepts/zen_strip.md` describes the featured meme, the single-link row, home only, the local `/?home=1` note and `--media-dim`. `visitor_intro.md` says the module is on the home page.
  - `FeatureFlagRegistry`: the Zen strip flag's description matches the new module.
  - Ledger closeout in `effort_ledger/zen_hero.md`.
- Verification:
  - `./v3 test --smoke BoardListTest ZenStripTest FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest PlatformDocsCatalogTest`: 491 run, 491 passed. The full suite was not run.
  - iPhone SE evidence: 380x667 screenshots of the home page in Gloss and Gloss Dark on the local copy (Stages 3 and 4). **Pending:** the maintainer's own check on the phone after deploy.
  - Final diff review against Step 2: 19 files outside `docs/`, 139 lines added and 57 removed. Every core requirement and success criterion is met on the local copy except the two below. No dependency change, no schema change, no new flag, no new partial.
- Notes:
  - Push and PR: done on 2026-11-21 once the maintainer asked for the PR. **Not done:** the replies in the request thread and the gallery task thread.
  - **Not verified:** a live like changing the featured meme (see Stage 1), and anything on production.
  - Success criterion "first thread within 1.5 screens at 380px" holds in Gloss, the default, and in Gloss Dark, Dark, Ochre and Bubblegum; Word97 and Midnight come to about 1.6.
- Estimate details (extended mode only): original scope as Step 3 Stage 5; uncertainty low; no revisions.

## Follow-up - Featured meme as wide as the column

- Request (maintainer, 2026-11-21, after Stage 5 and before the PR): "Please make the large meme full width as the rest of the column." Disposition: done on this branch as a follow-up commit; a styling change inside the approved module.
- Changes:
  - `site.css`: the module is no longer capped at 24rem and centred; the featured meme spans the content column, and the thumbnail row starts at the column's left edge.
  - `partials/zen_strip.php`: the image's `sizes` hint is now `(min-width: 48rem) 45rem, 100vw`, so a desktop loads the original rather than the 480px copy.
- Verification:
  - `./v3 test BoardListTest ZenStripTest`: 20 run, 20 passed. The full suite was not run.
  - Local copy, 1280x900, Gloss: the image is 710px wide in the 712px column and loads the original. No sideways overflow at 380px or 1280px in Gloss, Gloss Dark, Bubblegum, Ochre and Word97.
- Notes:
  - Phones are unchanged (the meme already filled the screen's width).
  - **Desktop gets taller:** the module is 766px in Gloss, up from 438px, and the first thread row starts 1,217px down a 900px-tall window, about 1.35 screens (889px before). Word97 is the tallest at 1,484px.
  - This replaces the 24rem figures in Stages 3 and 4 above.

*— Claude · 2026-11-21 UTC · Model: claude-fable-5-2*

## Closeout

- Retrospective scope assessment: **0.35**. Delivered as planned, plus the home-page-only revision, minus the push and thread replies. Claude, single exposed rater. Maintainer comfortable-fit judgment: pending. Measured time unknown.
- Checks: stage tests passed; smoke set passed (491 of 491); full suite not run; theme screenshots passed; JavaScript-off not rerun; live like blocked (no approved key set up locally); production pending; branch pushed and PR opened on request.
- Enhancement requests: none added. The gallery for All remains the proposed next cycle.

*— Claude · 2026-11-21 UTC · Model: claude-fable-5-2*
