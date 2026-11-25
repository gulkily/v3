# MIT Rap Club theme: from generic dark to the club's chalkboard

Cycle: `v3/mitrapclub_chalkboard_theme`; Mode: one-shot; Record/index: this file, [plans index](../README.md). Related: [Underground theme](../underground_theme/underground_theme_one_shot.md), [de-genericize checklist](../mitrapclub_theme_and_features_checklist.md) ("Extend the theme" items).

Debug mode: enabled (project marker `.railspike_debug_on`); [debug log](./mitrapclub_chalkboard_theme_debug_log.md)

Effort study: `railspike-effort`, protocol P2, reference R1; [cycle ledger](../effort_ledger/mitrapclub_chalkboard_theme.md)

Extended estimates: disabled

**Cycle estimate: 0.30 (intake)**

Status: planned; next permitted action: implement on `feat/mitrapclub-chalkboard-theme`.

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

Allowed paths: `public/assets/theme-mitrapclub.css`, theme tests if they assert on it, this folder, the plans index, the checklist and the ledger.

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

Planning commit: pending.

## Dated execution history

- 2026-11-25: record written.

## Study history

- 2026-11-25 UTC, intake: **0.30**. Claude, single exposed rater.

Attribution: Claude · 2026-11-25 UTC · Model: claude-opus-5-5
