# MIT Rap Club theme: Underground taken from 20% to 80% club (record began as a chalkboard rebuild of the dark theme)

Cycle: `v3/mitrapclub_chalkboard_theme`; Mode: one-shot; Record/index: this file, [plans index](../README.md). Related: [Underground theme](../underground_theme/underground_theme_one_shot.md), [de-genericize checklist](../mitrapclub_theme_and_features_checklist.md) ("Extend the theme" items).

Debug mode: enabled (project marker `.railspike_debug_on`); [debug log](./mitrapclub_chalkboard_theme_debug_log.md)

Effort study: `railspike-effort`, protocol P2, reference R1; [cycle ledger](../effort_ledger/mitrapclub_chalkboard_theme.md)

Extended estimates: disabled

**Cycle estimate: 0.30 (intake)**

Status: implemented and verified locally; PR open; next permitted action: maintainer review.

## Original request

2026-11-25, in chat: "so the design of mitrapclub theme is about 20% mit rap club, can you please take it to 80%"

Feedback items: none match (searched `docs/feedback/items/` for rap club, rapclub, mitrapclub).

## Authorization

A direct request to redesign one named theme, with a clear boundary (the `mitrapclub` stylesheet). Mode: one-shot, because the outcome is bounded and the maintainer reviews by merging. Authorizes a branch and an open PR. Excluded: the Underground theme, the profile's default theme, new fonts or other third-party assets, board hero / "what's next" callout markup, merge and deployment.

## Outcome and work type

Application change, CSS only. Today `theme-mitrapclub.css` is a dark palette with an amber accent and a few selector tweaks; nothing on screen says MIT Rap Club. Rebuild it around the club's own references:

- the chalk of cypherposium.com (chalk-drawn cube, Polaroid captioned "mit rap club", lowercase "what is rap?");
- the lowercase type and ultramarine of the "underground concert" flyer;
- MIT's lecture-hall chalkboard and the club's "rhymes studied as hard as they get spit": scansion marks, rhyme-scheme letters and rhyme arcs chalked in the margins.

Concretely: slate chalkboard page with chalk dust and eraser smudges; chalk-annotation margins (stress marks, `a a b b`, rhyme arcs, a chalk cube); lowercase chalk wordmark with a scribbled underline; chalk-outlined cards; nav active state as a chalk circle; Polaroid frames for images (Gallery is the club's home page); chalk-blue links, cardinal-red accents; swatch redrawn.

Allowed paths: `public/assets/theme-mitrapclub.css`, and after the scope addition below `public/assets/tool-details.css` (`.switch`) and `public/assets/theme-underground.css` (switch exclusion), theme tests if they assert on it, this folder, the plans index, the checklist and the ledger.

## Approach and risks

- Type with system fonts only (dependency policy): chalk display stack "Chalkboard SE", "Chalkduster", "Segoe Print", "Bradley Hand", then `ui-rounded`. Body in `ui-rounded`/system sans for reading. Impact: Linux/Android get the rounded fallback; accepted.
- Annotations as inline SVG data URIs (hand-drawn paths, no text glyphs where possible, so they render without fonts) in fixed page layers, shown only where the page has margins (≥ 1100px). Risk: clutter. Mitigation: low-alpha chalk, cards near-solid.
- Polaroid frames on gallery tiles and post images: check real markup first; must not crop or break media embeds.
- Contrast: chalk ink, chalk blue and red on slate must pass AA; compute.
- No animation; print and forced colours drop the texture.

## Reuse decisions

Keep the existing variable block, swatch and scoped `:root[data-theme="mitrapclub"]` pattern from this file and `theme-underground.css` (grain data URI, fixed `body::after` layer, lowercase chrome, wordmark selectors `.site-header > .eyebrow` / `.site-brand .eyebrow`). Registry entry and counterpart pairing (`mitrapclub` ↔ `underground`) unchanged; no script, no flag.

## Completion conditions

Selecting "MIT Rap Club" from the theme menu on the `mitrapclub` profile paints the chalkboard look at first paint on board, thread, gallery and about; readable at 380px and 1280px; focus ring visible; other themes unchanged. Release condition: an open PR.

Concepts touched: [page theme](../../concepts/page_theme.md), used unchanged.

## Verification and recovery plan

`./v3 test --smoke ThemeRegistryTest ThemeCatalogPageTest PreferencesPageTest`; local server with `FORUM_SITE_ID=mitrapclub`, system Chrome screenshots at 1280px and 380px with `mitrapclub` selected. Full suite not run. Recovery: revert the stylesheet; nothing else changes.

Starting revisions and branch: `origin/main` `1b244371c`; branch `feat/mitrapclub-chalkboard-theme`.

Planning commit: `ccccbfaa2` (record, debug log and index only).

## Dated execution history

- 2026-11-25: record written; planning commit `ccccbfaa2`.
- 2026-11-25: stylesheet rebuilt. Margin notes and wordmark marks are inline SVG data URIs with a chalk filter (displacement plus noise holes); text in them uses the chalk font stack. Polaroid frames on `.gallery-tile` and `.post-image__original`.
- 2026-11-25, correction after first render: chalk dust too dense (alpha lowered); margin notes too faint (0.5 → 0.7); the default `.nav-link.is-active` ring showed around the chalk circle (box-shadow cleared).
- 2026-11-25, scope addition (user, mid-task): "also if it's in scope for this, please improve the design of the toggles … (the on position is hard to see)", screenshot from Underground's feature flags page. Accepted. Cause: the feature-flag switch is a `button[type=submit]`, and Underground's submit rule painted the track blue in both states, leaving only a dark green knob to tell them apart. Changes: Underground and MIT Rap Club submit rules exclude `.switch`; base `.switch` on state is now a solid `--status-ok` track with a page-coloured knob, off keeps the empty track with an `--ink-soft` knob (was `--line`, faint); knob motion respects reduced motion.
- 2026-11-25: debugging used a local-only profile edit (`brandedStylesheet` → `mitrapclub`) to render the theme by default; reverted before commit.

- 2026-11-25, **scope correction (user)**: "can you please look at the existing theme", then, asked which theme was meant, chose **Underground**: the theme the club site opens in is the one to take from 20% to 80% club. The chalkboard rebuild of the dark `mitrapclub` theme was a misreading; `theme-mitrapclub.css` restored to `origin/main` (the chalkboard version stays in commit `acd58b3ef` for reference). Switch fix kept.
- 2026-11-25: Underground additions, in the flyer's ink and type: margin notes (cypherposium cube, "mit rap club", scansion and stress marks, `a a / b b` rhyme scheme, "what is rap?", mic, waveform, cypher circle, "1 bar = 4 beats") as a CSS mask so the ink follows `--ug-blue` in light and dark; cube before the wordmark; Polaroid frames for gallery tiles and post images; events as a dashed ticket stub.
- 2026-11-25, discovery: `body` is `isolation: isolate`, so a `:root::after` layer at `z-index: -1` paints under body's own layers and was invisible. The notes live on `.shell::before` instead (fixed, `z-index: -1`, inside body's layer, under the multiply halftone).
- 2026-11-25, scope addition (user): "for the gallery, can you get rid of the replies counts and only show a title if there is a title (currently all the titles are \"Image\")". Accepted. `RecentPageController::gallery()` drops the reply-count query and gives each tile a `caption`: the thread's title, empty when the post is only a picture (the `ThreadTitle` "Image" stand-in with no subject) or the thread is unavailable. `gallery.php` renders the caption only when present, with no meta line. Applies to every site's gallery. Underground keeps a thick Polaroid bottom on uncaptioned tiles. `GalleryPageTest` updated: no reply counts, and an image-only post gets no caption.
- 2026-11-25: user asked for the PR ("you can pr the theme update please").

## Verification (superseded by the Underground pass below)

- `./v3 test --smoke ThemeRegistryTest ThemeCatalogPageTest PreferencesPageTest`: 499 run, 499 passed. `./v3 test --smoke FeatureFlagChangePageTest`: 495 run, 495 passed. Full suite not run.
- Local server, `FORUM_SITE_ID=mitrapclub` with zenmemes.com content imported, system Chrome: `/threads/` at 1280px and 380px, `/gallery/` at 1280px; margin notes appear only at ≥ 1180px.
- Switches: on and off captured on `/tools/feature-flags/` in Underground, Underground Dark, MIT Rap Club, Light, Dark, Gloss, Bubblegum and Spearmint; states are distinct in all. Bubblegum keeps its own button styling (unchanged by this cycle).
- Contrast (WCAG): chalk on card 12.18, chalk blue on card 6.13, cardinal red on card 5.94, soft chalk on card 7.43, submit text 13.91, Polaroid title 13.13, Polaroid meta 5.53, placeholder 5.69.
- Limitation: this box has no chalk fonts, so screenshots show the system sans fallback. iPhone and Mac get Chalkboard SE, Windows Segoe Print; Android and Linux the rounded or system sans.
- Recovery: revert the three stylesheets; nothing else changed.

## Verification (Underground pass)

- `./v3 test --smoke ThemeRegistryTest ThemeCatalogPageTest PreferencesPageTest FeatureFlagChangePageTest`: 501 run, 501 passed. Full suite not run.
- Local `mitrapclub` server (default theme Underground, no profile edits): `/threads/` in Underground and Underground Dark at 1280px, `/gallery/` at 1280px and 380px. Notes show at ≥ 1180px only; on phones only the cube and Polaroids change.
- `./v3 test --smoke GalleryPageTest`: 496 run, 496 passed (earlier run with LocalAppSmokeTest and ImageServingTest passed apart from a fixture error in the new test case, since fixed). Gallery screenshot at 1280px: captions only on titled tiles.
- Limitation: the margin notes' blue fleck (existing halftone at 80% of the viewport height) can overlap the cypher circle depending on window height; left as print overlap.

## Outcome (superseded)

Delivered: the MIT Rap Club theme rebuilt around the club's chalk, lowercase name, cube, rhyme-scheme and cypher notes, and Polaroid images; clearer feature-flag switches site-wide, with Underground's on/off bug fixed. Not done: board hero / "what's next" callout, self-hosted chalk font (needs dependency approval), club favicon art; the `mitrapclub` profile still defaults to Underground.

## Outcome

Delivered: gallery tiles without reply counts, captioned only with real titles (all sites). Underground (the club site's default) carries the club's own marks: cube wordmark, margin notes in flyer ink, Polaroid images, ticket-stub events. Feature-flag switches have a clear on state site-wide, and Underground no longer paints both states blue. The dark `mitrapclub` theme is unchanged from `origin/main`. Not done: board hero / "what's next" callout, self-hosted display font, club favicon art.

## Study history

- 2026-11-25 UTC, intake: **0.30**. Claude, single exposed rater.
- 2026-11-25 UTC, revision: scope grew by the switch fix (user-requested addition, small); assessment **0.35**.
- 2026-11-25 UTC, closeout: target theme corrected to Underground (rework); assessment **0.45**. Maintainer judgment pending; measured time unknown.

Attribution: Claude · 2026-11-25 UTC · Model: claude-opus-5-5
