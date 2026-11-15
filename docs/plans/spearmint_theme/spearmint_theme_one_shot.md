Cycle: `v3/spearmint_theme`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/spearmint_theme` — [host ledger](../effort_estimation_trial.md#cycle-v3spearmint_theme); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.30**
Status: implemented and verified locally; next permitted action: push and PR (merge and deploy need the maintainer)

## Original request

In a Claude chat on 2026-11-14 the maintainer asked what else, beyond colour, a differently coloured sibling of Bubblegum should change. Of the ideas offered he picked Spearmint: mint and deep teal outlines, a coral pop, soft rounded shadows and a wavy divider. He then asked for it as a standalone theme ("Just make spearmint. For now, please.") rather than first splitting Bubblegum into shared candy structure plus per-flavour variables.

## Authorization

"Just make spearmint. For now, please." (2026-11-14, after the standalone version was described as its own stylesheet with Bubblegum untouched). That authorizes a one-shot cycle for the bounded outcome below, pushing a branch and opening a PR. Excluded: refactoring Bubblegum, other flavours, a nav icon set, offering Spearmint on chouse, qdb or MIT Rap Club, merging and deploying.

## Outcome and work type

Application change (one theme). Normal entry: the theme menu and cycle button on zenmemes. Outcome: choosing Spearmint shows a light mint theme with its own structure, not a recoloured Bubblegum:

- cool mint page with a faint leaf/dot texture; white cards with a deep-teal outline, rounded corners and a soft blurred shadow instead of Bubblegum's hard offset;
- a wavy teal divider under the header; a white wordmark with a soft teal glow and a small leaf mark;
- rounded buttons that lift on hover and squish on press (Bubblegum's are square and slide); pill nav links; coral for unread, pinned, focus and selection;
- the phone bottom tab bar and More panel readable at about 380px.

Recovery: a reader leaves through the theme menu as for any theme; the saved choice persists like other themes.

## Approach and risks

Copy the parts of `theme-bubblegum.css` that style shared chrome (cards, nav links, inputs, buttons, header, phone app nav, pinned marker, feedback boxes, selection) into a new `theme-spearmint.css`, rewritten for the Spearmint structure. Leave out Bubblegum-only garnish: window title bars, the pixel-art Tools control panel and the stats-chart styling.

- Risk: contrast of teal on mint and of coral accents. Mitigation: dark teal ink and outlines; coral only as a fill behind dark ink; check rendered screenshots.
- Risk: bare `button` rules restyling theme-menu rows. Mitigation: keep Bubblegum's `:not(.theme-swatch):not(.theme-menu__trigger):not(.theme-menu__option)` guard (theme guide rule).
- Risk: nav icons. Spearmint joins no icon set, so it gets the same text-only nav as Light, Sticker or Forge; `NavIconSetsTest` is unaffected.

## Reuse decisions

- Registry entry in `src/ForumRewrite/View/ThemeRegistry.php`; the stylesheet path, menu row, cycle button and pre-paint allow-list derive from it (`docs/runbooks/theme_development_guide.md`).
- Site offer: add `spearmint` to the zenmemes `permittedThemes` in `src/ForumRewrite/SiteProfileRegistry.php`.
- CSS: same variable contract and selectors as `public/assets/theme-bubblegum.css`; no new markup, scripts or components.
- Tests: update the allow-list string in `tests/LocalAppSmokeTest.php` and the unpaired menu layout in `tests/ThemeRegistryTest.php`.

## Completion conditions

Choose Spearmint from the theme menu on a local zenmemes instance; board, thread, compose and Tools pages render in the Spearmint look on desktop and phone widths; the theme menu rows still render representatively; switching away restores the other theme.

## Concepts touched

Theme (used unchanged; one new instance). No inventory change.

## Verification and recovery plan

- `./v3 test --smoke ThemeRegistryTest LocalAppSmokeTest NavIconSetsTest`; full suite not run (maintainer runs it).
- Screenshots at 1280px and 390px of the board, a thread, compose, Tools and the open theme menu, checked by eye for contrast, overflow and the menu rows.
- Not checked: physical phones, Firefox/WebKit, production.

## Starting revisions and branch

Host `main` at `0443804d0c01b3b82a873551131282d16756351b`; branch `feat/spearmint_theme`.

## Planning commit

`5073125` (confirmed with Git).

## Study history

- 2026-11-15T04:00Z intake: whole-cycle projection **0.30**, scope v1 as above, R1, P2, single exposed rater Claude (claude-opus-5-5). Exposure: the estimate was first given in chat from Bubblegum's CSS as seen in an earlier session, before this repository was inspected; no pristine intake.
- 2026-11-15T04:00Z execution entry: reaffirmed **0.30** after reading the theme guide, registry and Bubblegum's full stylesheet.

## Dated execution history

- 2026-11-15T04:00Z: no matching feedback item (searched `docs/feedback/items/` for spearmint and mint theme). The request came from chat, not the forum; no item created.
- 2026-11-15T04:20Z: implemented `public/assets/theme-spearmint.css`, the registry entry after Bubblegum and the zenmemes offer. Updated the allow-list string in `LocalAppSmokeTest` and the unpaired menu rows in `ThemeRegistryTest` (Spearmint takes the row beside Chouse; Engagify and QDB move down one).
- 2026-11-15T04:30Z: correction after screenshots: zenmemes uses the app header, which Bubblegum's `.site-header:not(.site-header--app)::after` divider skips, so the wave was missing. Added the wave as a border-image on `.site-header--app` from 640px, replacing site.css's straight rule. That rule loads after theme stylesheets, so the selector carries an `html` type selector to outrank it.
- 2026-11-15T04:35Z: correction after screenshots: Tools tiles and thread lists were still square with thin borders; gave them the teal 2px outline and rounded corners (thread lists keep only the card frame when inside a card).

## Study closeout

Intake 0.30; execution entry reaffirmed 0.30; **retrospective 0.30** (the header-specificity and tile corrections were ordinary correction within the estimate). Single exposed rater Claude (claude-opus-5-5), R1/P2. Maintainer acceptance pending; no accepted-count increment.

## Outcome

Delivered: Spearmint is in zenmemes's theme menu and cycle button after Bubblegum. Mint leaf-and-dot page, white rounded cards with deep-teal outlines and soft blurred shadows, a wavy teal line under the header (full width on the app header), rounded buttons that lift on hover and squish on press, pill nav links, rounded Tools tiles and thread lists, coral for unread, pinned, focus and selection, a leaf after the wordmark. Spearmint uses the plain text nav (no icon set). Not offered on chouse, qdb or MIT Rap Club (excluded by authorization).

## Verification

- `./v3 test --smoke ThemeRegistryTest LocalAppSmokeTest NavIconSetsTest`: 466 run, 466 passed. Rerun after CSS corrections: `./v3 test ThemeRegistryTest NavIconSetsTest` 21 passed.
- `node tests/browser/tools_launcher.mjs <dir> spearmint,bubblegum` (Chromium 1243): pass, no overflow at 1280px and 390px, Bubblegum icons unchanged.
- Ad hoc screenshots (private-message browser fixture, Chromium) of board, thread list, a thread with a reply, compose, the phone More panel and the open theme menu at 1280px and 390px: theme active, no page errors, no horizontal overflow; checked by eye for contrast and layout. The Spearmint menu row renders representatively; other rows unchanged.
- Full suite not run (maintainer runs it). Not checked: Firefox, WebKit, physical phones, production.

## Final review

Diff: one new stylesheet, one registry line, one profile list, two test expectations. No markup, scripts or shared CSS changed; no duplication beyond the per-theme selectors every theme carries. Theme-menu `:not(...)` guard kept on bare button rules.

## Commit status

Planning commit `5073125`; completion commit follows on `feat/spearmint_theme`. Merge and deploy are not covered by this cycle's authority.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
