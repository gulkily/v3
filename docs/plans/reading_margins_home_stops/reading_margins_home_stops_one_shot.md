# Reading margins: home stops, more stops, a drawn ruler, and the header follows

Cycle: `v3/reading_margins_home_stops`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/reading_margins_home_stops`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.30**

Status: plan written; next permitted action: planning commit, then implementation.

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

Planning commit: pending.

## Dated execution history

- 2026-11-15: `AGENTS.md`, `RAILSPIKE.md`, the one-shot workflow and the shared implementation standards read after cloning `main`. No matching item in `docs/feedback/items/`. The existing browser test was run on `main` first (passes, 18 themes) and its screenshots reviewed: the app header already spans up to 1180px, wider than the column, which is why outcome 4 leaves the normal layout alone.

## Study history

- Intake, 2026-11-15: **0.30**, scope v1, recorded before implementation, after reading the ruler files, the header CSS and the baseline screenshots (not pristine). Baseline `ed22dae`; R1 hashes not re-verified. Single exposed rater Claude (claude-fable-5-2).

*Claude (claude-fable-5-2), 2026-11-15 UTC*
