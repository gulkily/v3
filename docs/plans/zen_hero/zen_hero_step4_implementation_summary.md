> **Feature plan:** [Step 1](./zen_hero_step1_solution_assessment.md) · [Step 2](./zen_hero_step2_feature_description.md) · [Step 3](./zen_hero_step3_development_plan.md) · [Step 4](./zen_hero_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/zen_hero`; host ledger [zen_hero.md](../effort_ledger/zen_hero.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.35**

Branch `zen-hero`, based on `origin/main` `09a15f6`. Planning commits: Steps 1–3 were committed as each was written and approved (zen_strip precedent), ending with `5af3040` (Step 3 revision, home page only). The ledger and debug log went into those commits too, the same deviation from the planning-commit rule as zen_strip. The branch is local only: this session was refused push access to gulkily/v3. Base: `./v3 test --smoke ZenStripTest BoardListTest`, 484 run, 484 passed.

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
