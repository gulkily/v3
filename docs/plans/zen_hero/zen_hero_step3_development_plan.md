> **Feature plan:** [Step 1](./zen_hero_step1_solution_assessment.md) · [Step 2](./zen_hero_step2_feature_description.md) · [Step 3](./zen_hero_step3_development_plan.md) · Step 4

Debug mode: enabled

Effort study: `railspike-effort` / `v3/zen_hero`; host ledger [zen_hero.md](../effort_ledger/zen_hero.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.35**

Concepts touched: [Zen strip](../../concepts/zen_strip.md) (revised in Stage 5). [Tag browsing](../../concepts/tag_browsing.md) (unchanged). Feedback: none.

## Completion Contract

- Normal entry: a signed-out visitor opens zenmemes.com; a member likes a Zen meme.
- End-to-end outcome: the board's default first page shows the highest-scored Zen meme whole, then one row of tiny thumbnails of the others. The row is one link to /tags/zen+meme. There is no All link. Chosen views, sorts and later pages show no module. Dark themes dim the module's images.
- Recovery: `FORUM_ZEN_STRIP_ENABLED` off removes the module. Removing a tag or retracting a thread takes it out, as today.
- Deployment: no schema change and no extra command; Deploy now is enough. In high-traffic mode, cached pages keep the old module until their next refresh.
- Release: merged to main, deployed, and checked by the maintainer on his iPhone SE.

## Key Risks

- **High risk:** a row of pictures opens a page without pictures. Impact: the tap disappoints a first-time visitor. Early validation: tap through on a local copy in Stage 3. Mitigation: none here; the gallery is the proposed next cycle. The maintainer accepted Step 2 with this risk listed.
- A like doesn't change the featured meme. Impact: the front page ignores likes. Early validation: Stage 1 likes a meme on a local copy and reloads the signed-out board before any styling. Mitigation: the module reads the same thread score Top sort reads; if Top sort updates and the module doesn't, fix it in Stage 1.
- Themes restyle the row or the dimming. Impact: overflow at 380px, or washed-out images. Early validation: Stage 4 screenshots in five themes. Mitigation: the module keeps its own classes; the dimming setting defaults to no change.

## Stage 1

**Estimate: 0.10**

- Goal: the query returns Zen memes with the top-liked one first.
- Dependencies: none.
- Expected changes: `ZenStrip::load()` orders by thread score descending, then newest; each row carries its score. The approved-tag rule and the limit of twelve stay. No signature change beyond the added field.
- Verification approach: `./v3 test --smoke ZenStripTest`, with new cases: highest score leads; a tie goes to the newest; a negative score sorts last. On a local import of zenmemes.com, list the six seeds' scores, like one, and reload the signed-out board.
- Estimate details (extended mode only): driver: one order clause and the like-refresh check; uncertainty: low.
- Risks or open questions:
  - Impact: the like-refresh risk above.
  - Early warning / validation: the like-and-reload check.
  - Mitigation: fix here before Stage 3.
- Canonical components/API contracts touched: `ReadModel/ZenStrip.php` (extended), `threads.score_total` (read, as Top sort does), `ThreadKeysetPage::listedRootsSql()` (reused).

## Stage 2

**Estimate: 0.05**

- Goal: the module shows only on the board's default first page.
- Dependencies: none.
- Expected changes: `BoardPageController::zenStrip()` also requires that the request chose no view and no sort. Both callers (`board()` and `boardList()`) pass that. A tag filter already goes to a different page.
- Verification approach: `./v3 test --smoke BoardListTest`: present on `/` and `/threads/`; absent on `?view=all`, `?view=liked`, `?sort=top` and page 2. The existing first-page test, which uses `?view=all&sort=newest`, moves to the default address.
- Estimate details (extended mode only): driver: one condition and its tests; uncertainty: low.
- Risks or open questions:
  - Impact: the default view falls back from Liked to All when nothing is liked, and the module could vanish with it.
  - Early warning / validation: a test on a board with no liked threads.
  - Mitigation: decide from what the request asked for, not from the view the board fell back to.
- Canonical components/API contracts touched: `Http/BoardPageController.php` (`zenStrip()` extended), `BoardViewOptions` (read only).

## Stage 3

**Estimate: 0.15**

- Goal: the module is one featured meme and one thumbnail row that opens All.
- Dependencies: Stages 1 and 2.
- Expected changes: `partials/zen_strip.php` renders the first thread as the featured card (whole image with the existing `srcset`, or its title in large type), linking to its thread. The rest render inside a single link to `$allHref`: square thumbnails from the `src`/`srcset` that `ListThumbnails` already provides for list rows, and a small text tile for text memes. The link has an accessible name ("All Zen memes"). The All paragraph is removed. Styles: the row is one line, hides what doesn't fit, and never scrolls. With one Zen meme there is no row.
- Verification approach: `./v3 test --smoke BoardListTest ZenStripTest`: one featured card; the row is one link to /tags/zen+meme; no `zen-strip__more`; a lone meme shows no row. On the local copy at 380px and 1280px: no sideways overflow, first thread within 1.5 screens at 380px, tap the row.
- Estimate details (extended mode only): driver: partial, styles and the changed tests; uncertainty: low.
- Risks or open questions:
  - Impact: images inside one link lose their per-image meaning for screen readers.
  - Early warning / validation: read the markup; thumbnails get empty alt text and the link carries the name.
  - Mitigation: as stated; the All page names every meme.
- Canonical components/API contracts touched: `templates/partials/zen_strip.php` and `.zen-strip__*` in `public/assets/site.css` (changed in place), `Images/ListThumbnails` (reused, unchanged).

## Stage 4

**Estimate: 0.10**

- Goal: the module's images suit dark themes, and every theme holds the layout.
- Dependencies: Stage 3.
- Expected changes: one theme variable for image brightness in `site.css`, default no change, used by the module's images. Dark themes set it lower: the dark colour scheme in `site.css`, and each dark theme file found by the check below. Theme fixes only where a screenshot fails.
- Verification approach: screenshots at 380px and 1280px in Gloss, Gloss Dark, Bubblegum, Ochre and Word97; Gloss unchanged pixel for pixel in the featured image, Gloss Dark visibly dimmed; the board with JavaScript off; keyboard focus on the featured card and the row. `tests/theme_contrast_audit.py` if it covers the module.
- Estimate details (extended mode only): driver: number of dark themes; uncertainty: medium, since the list of dark themes is confirmed during the stage.
- Risks or open questions:
  - Impact: a dark theme is missed and still glares.
  - Early warning / validation: list every theme that declares a dark colour scheme before editing.
  - Mitigation: set the variable where the dark scheme is declared, not per theme, wherever that is possible.
- Canonical components/API contracts touched: theme variables in `public/assets/site.css` and `theme-*.css` (one new variable).

## Stage 5

**Estimate: 0.05**

- Goal: docs, the iPhone SE evidence and a final diff review.
- Dependencies: Stages 1–4.
- Expected changes: `docs/concepts/zen_strip.md` describes the featured meme, the row, home only and dimming; the flag's description; the Step 4 summary; a reply in the [request thread](https://zenmemes.com/threads/thread-20261121083631-65a3194c) and the [gallery task thread](https://zenmemes.com/threads/thread-20261120182535-0db81c46de8339600736c7ae1fec6e37) once the PR is open.
- Verification approach: 380px screenshots of the home page in Gloss and Gloss Dark as the iPhone SE evidence; the docs smoke test; review the whole diff against Step 2's requirements and success criteria. The full suite is not run.
- Estimate details (extended mode only): driver: writing; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `docs/concepts/` (inventory), `FeatureFlagRegistry.php` (description only).

## Study snapshots and approval

- 2026-11-21 UTC: **Approved Step 2** by the maintainer ("Approved. Step 2."), with the defaults as written.
- 2026-11-21 UTC, Step 3 handoff: **0.35** projected total, reaffirmed. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified this session. Baseline `origin/main` `09a15f6`. Awaiting `Approved Step 3`.

*— Claude · 2026-11-21 UTC · Model: claude-fable-5-2*
