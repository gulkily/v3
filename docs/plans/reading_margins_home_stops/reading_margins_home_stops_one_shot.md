# Reading margins: home stops, more stops, a drawn ruler, and the header follows

Cycle: `v3/reading_margins_home_stops`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/reading_margins_home_stops`; [cycle ledger](../effort_ledger/reading_margins_home_stops.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.30**

Status: implemented and verified locally; PR #263 open; next permitted action: maintainer review, merge and a try in his browser.

Predecessor: [`v3/reading_margins_nearest_edge`](../reading_margins_nearest_edge/reading_margins_nearest_edge_one_shot.md) (merged as #239).

## Original request

The maintainer, 2026-11-15: "For the margin controls, I'd like to make a couple of enhancement requests. First, I'd like there to be margins that match the default margins so that they're selected visibly and the user knows what's going on. I'd like for there to be a few more radio button options. And perhaps I would like to enhance the appearance of the whole feature. Oh, and also the nav bar should also obey the margins."

## Authorization

The request above is a direct instruction to build four enhancements to the merged ruler. Read as authority for a one-shot cycle on a new branch from `origin/main` through to an open PR. It does not authorize merging or deployment. The appearance request is hedged ("perhaps"), so that part stays modest and inside the ruler. Excluded: drag, a separate reset control, changes to which pages show the ruler, phones, the flag's default, and the header's layout in the site's normal column.

## Outcome and work type

Application change, behind the existing `FORUM_READING_MARGINS_ENABLED` flag.

1. **Home stops.** Two radios sit exactly on the normal column's edges and are checked whenever the reader has not chosen a column, so the ruler always shows two checked radios over the column's edges.
2. **More stops.** The row is a uniform grid anchored on the normal column: the column is split into fifths, and the same step continues outward on both sides as far as the window allows. At 1280px that is 8 radios (6 today); at 1440px, 10; at 1920px, 12. Stops that would fall outside the window are not shown.
3. **Appearance.** The row is drawn as a ruler: a hairline across the page through the radios, a thicker bar over the current column, radios tinted with the theme's active colour, unchecked ones quieter until the pointer is over the row, and a round dashed ring (instead of the square outline) on the edge a tie would move.
4. **Header.** When the reader has chosen a column, the site header (name, navigation, New post, You) takes the column's width and position, and its links wrap inside it. In the normal column the header keeps today's layout, which is designed to overhang the 760px column.

Unchanged: the nearer edge moves and edges never cross; a tie moves the edge clicked longer ago; clicking a checked radio returns to the normal column (a no-op when already there); the 20rem minimum; the pages; phones; the storage key; the Preferences row.

## Approach and risks

- Positions become multiples of one fifth of the normal column, counted from its left edge (home stops are 0 and 5; the rest run from -8 to 13). Everything is a percentage of the shell's content box, so no `vw` arithmetic is needed for the column, and stops line up with it in themes with other shell widths (680–860px).
- Storage keeps "older newer" in the same key, now as stop numbers. Nothing is stored for the normal column. Saved values from the old `vw` stops (5, 23, 41, 59, 77, 95) are not valid stop pairs in the new range, so they are ignored and the reader starts from the normal column. Accepted: the feature is a day old and resetting is one click.
- A column saved in a wide window can name a stop that does not fit a narrower one. Then the page uses the normal column in that window and leaves the saved value alone. The partial's pre-paint script decides this, so the first paint is final.
- The 20rem minimum can push a narrow column past the window edge near the sides. The column is clamped to stay 0.5rem inside the window.
- Risk: the header in a narrow column wraps to several rows. Early check: screenshots at 20rem and at one step wider than normal in every theme before finishing; fix inside the header rules for a chosen column only.
- Risk: the header jumps between "overhanging" (normal column) and "inside the column" (any chosen column). Accepted for this cycle and reported in the handoff, since changing the normal header is out of scope.
- Risk: framed-header themes (Word97, Midnight, Chicago, LCD) size the header with their own rules. Checked by the browser test in every theme.

## Reuse decisions

Extends the existing ruler in place: `templates/partials/reading_margins.php`, `public/assets/reading_margins.js`, `public/assets/reading-margins.css`, the pre-paint restore in `templates/layout.php`, the flag description in `FeatureFlagRegistry.php`, `tests/LocalAppSmokeTest.php` and `tests/browser/reading_margins.mjs`. The header rules reuse the column's custom properties; `site.css`'s header rules are overridden from `reading-margins.css` only under `:root[data-reading-margins]`, so sites with the flag off load nothing new. The hairline uses the header's existing `border-image` outset technique (`site.css`, app header) so it cannot cause sideways scrolling. Colours come from the themes' `--line` and `--active-line`. `preferences.js` keeps calling `window.forumResetReadingMargins`. No new components or files.

## Completion conditions

On a thread and the other ruler pages, in every permitted theme at desktop width: with nothing saved, the two home radios are checked and sit over the column's edges; stops are evenly spaced and none is outside the window; clicks follow the existing rules from the home pair; the bar and both checked radios match the column; the header's edges match the column's when a column is chosen and the header is unchanged otherwise; a saved column is final on first paint; a saved column that does not fit the window falls back to the normal column without being erased; no sideways scroll; phones unchanged.

Concepts touched: none.

## Verification and recovery plan

- `./v3 test --smoke LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnLayoutPagesWhenFlagEnabled FeatureFlagEvaluatorTest`. Full suite not run.
- `node tests/browser/reading_margins.mjs` rewritten for the new stops, across all permitted themes, at 1280px and one wider and one narrower desktop width, plus phone; screenshots reviewed for every theme.
- Reader recovery: clicking a checked radio, or Preferences → Reading margins → Reset, returns to the normal column.
- Not planned: Firefox, Safari, real devices, production. The maintainer tries it after merging.

Starting revisions and branch: `origin/main` `ed22dae`; branch `feat/reading_margins_home_stops`.

Planning commit: `1fa3578` (confirmed with Git before implementation).

## Dated execution history

- 2026-11-15: `AGENTS.md`, `RAILSPIKE.md`, the one-shot workflow and the shared implementation standards read after cloning `main`. No matching item in `docs/feedback/items/`. The existing browser test was run on `main` first (passes, 18 themes) and its screenshots reviewed: the app header already spans up to 1180px, wider than the column, which is why outcome 4 leaves the normal layout alone.

- 2026-11-15: Planning commit `1fa3578`; plan shared in session as a file; implementation followed.
- 2026-11-15: Implemented outcomes 1 and 2 as planned: stops -8 to 13 in fifths of the normal column, 0 and 5 checked in the server's HTML, stops outside the column rendered `hidden` and shown by the partial's pre-paint script when they fit the window (12px inside it). The column, the bar and the header share `--reading-width` and `--reading-offset`, set on `.shell` as percentages of its content box.
- 2026-11-15: Correction to the plan: stop fitting also reruns when the ruler's own width changes (a `ResizeObserver` in `reading_margins.js`), not only on window resize. Reason found in testing: a theme with another shell width, or a theme stylesheet that loads after the pre-paint script, changes where the stops fall.
- 2026-11-15: Outcome 3. First attempt kept the browser's radios with `accent-color`; the ring stayed square and unchecked radios nearly vanished in dark themes. Changed to radios drawn in CSS (`appearance: none`): small outlined circles, a larger filled ring for a column edge, a round dashed ring for the tie edge. `input.reading-margins__stop` outranks themes' general input bevels (Chicago); themes that square their inputs (Bubblegum, LCD, Spearmint) still do. Unchecked stops use `--ink-soft` so they show on dark pages. The planned "quieter until the pointer is over the row" was dropped: the drawn radios are already quiet.
- 2026-11-15: Outcome 4. With only width and offset applied, a narrow header squeezed the links into one column (screenshot review, as the plan's early check). Addition inside the same outcome: for a chosen column the app header is a size container; under 62rem the name, New post and the menu share the first row and the links wrap below. The container makes the header a stacking context, so it takes `z-index: 30`, the account menu panel's level. Above 62rem the header keeps one row.
- 2026-11-15: Browser test rewritten. Its first-paint check now measures the column in the ruler's units, because the old pixel comparison depended on a scrollbar appearing and on the theme stylesheet having loaded (the test sets the theme in localStorage only, so the stylesheet is swapped by script).
- 2026-11-15: No new requests during execution.

## Study history

- Intake, 2026-11-15: **0.30**, scope v1, recorded before implementation, after reading the ruler files, the header CSS and the baseline screenshots (not pristine). Baseline `ed22dae`; R1 hashes not re-verified. Single exposed rater Claude (claude-fable-5-2).

- Execution entry, 2026-11-15: **0.30** reaffirmed.
- Closeout, 2026-11-15: delivered scope v1 in full, with the corrections recorded above (refit on ruler width, drawn radios, two-row header). Retrospective scope assessment **0.35**. Maintainer comfortable-fit and acceptance pending.

## Outcome

- With nothing chosen, the radios on the normal column's edges are checked, and a bar on the ruler runs between them.
- Stops are a uniform grid of fifths of the normal column, continued outward as far as the window allows: 8 at 1280px in the 760px themes (6 before), more in wider windows and in Sticker's narrower column, 12 or more at 1920px.
- The ruler has a hairline across the page, a bar over the column, drawn radios and a round dashed ring for the tie edge.
- A chosen column moves the header with it; the header's links wrap inside the column. The normal column keeps the header's own, wider layout.
- A column saved in a wider window is not used in a window where its stops do not fit, and comes back when they do.
- A 20rem column next to the window's side stays inside the window.

Limitations and things for the maintainer to judge:

- Columns saved before this change (the old `vw` stops) are ignored; readers start from the normal column.
- The header changes between "wider than the column" in the normal column and "inside the column" for any chosen column, so one step wider than normal gives a two-row header at 1280px. Making the normal header obey the column too would change every page's default layout and was left out.
- The stops no longer sit at fixed fractions of the window; they are anchored on the normal column, so the outermost stop is up to one step short of the window's edge.
- A stop midway between the home stops does not exist (five steps), so no ring shows in the normal column.

## Verification

- `./v3 test --smoke LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnLayoutPagesWhenFlagEnabled FeatureFlagEvaluatorTest`: 468 of 468 passed. Full suite not run.
- `node tests/browser/reading_margins.mjs`: passed in all 18 permitted themes at 1280px on a thread and 13 other pages, plus 1920px, a resize to 1000px and back, 700px and phone. It checks home stops on the column, even spacing, stops inside the window, the click rules, the bar and header matching the column, header contents fitting, first paint, reset both ways, old and invalid saved values, the unfit saved column and no sideways scroll.
- Screenshots reviewed for every theme: normal column, a chosen column (2 to 6) and the 20rem column; also 1920px and 700px in the default theme.
- Not checked: Firefox, Safari, real devices, production, the account menu open inside a narrow header, and the non-app header (the fixture uses the app header; it gets only the width and offset).

## Final review

Diff limited to the files under reuse decisions plus this record, its index entry, the debug log and the ledger entry. No new files or components. Comments in the partial, script, stylesheet and `layout.php` describe the new stops; the flag description mentions the header. `git diff --check` clean.

## Commit/merge/publication status

Planning commit `1fa3578`; completion commit `0dfd5fa` (confirmed with Git); branch `feat/reading_margins_home_stops` pushed; PR #263 open against `main`; not merged or deployed.

Debug log: [reading_margins_home_stops_debug_log.md](./reading_margins_home_stops_debug_log.md)

*Claude (claude-fable-5-2), 2026-11-15 UTC*
