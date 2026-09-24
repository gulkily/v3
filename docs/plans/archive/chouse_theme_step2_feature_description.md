# Chouse Theme — Step 2: Feature Description

## Problem

chouse.club has no dedicated visual identity in this codebase yet — a
`FORUM_SITE_ID=chouse` instance currently renders with the same theme
options as zenmemes, none of which read as chouse.club's brand.

## User Stories

- As the chouse.club site owner, I want a theme that visually reads as
  chouse.club, not zenmemes wearing a different name, so the migrated
  site doesn't feel like an unfinished reskin.
- As a developer previewing locally, I want the chouse theme to apply
  automatically when `FORUM_SITE_ID=chouse`, so I don't have to pick it
  from the theme menu every session.
- As the site owner, I want the identity to avoid literally reproducing
  Red Bull's branding, colors, or logo, for legal reasons, while still
  evoking the "clubhouse" feel chouse.club currently has.
- As a zenmemes user, I want zero visual change to any existing theme
  from this work.

## Core Requirements

- New `ThemeRegistry` entry (`chouse`) built on the existing three-block
  CSS pattern documented in `docs/runbooks/theme_development_guide.md`.
- Original visual identity, distinct from Red Bull's actual branding —
  may evoke the "clubhouse" concept and a vertical wordmark, per the
  earlier hosting-plan notes, without copying source colors/assets.
- `SiteProfileRegistry`'s `chouse` entry's `defaultTheme` changes from
  `auto` to `chouse`, so it becomes the default automatically (the
  multi-site-config feature already wires this end to end); zenmemes'
  entry and defaults are untouched.
- No change to any existing theme's appearance or to theme-menu behavior
  for zenmemes.
- Follows the theme guide's existing verification workflow (stale
  static-artifact traps, visual sweep, `tests/LocalAppSmokeTest.php`
  allow-list update).

## Shared Component Inventory

- `ThemeRegistry` is the existing canonical registry for every theme;
  this adds one entry to it rather than introducing a parallel mechanism.
- `public/assets/site.css`'s three-block pattern (variable block, swatch,
  scoped section) is the existing styling contract every theme follows —
  no new CSS architecture.
- `SiteProfileRegistry` (already shipped) is the existing hook for
  per-site default theme selection; this feature changes only chouse's
  data value, not the registry class.
- `tests/LocalAppSmokeTest.php`'s theme allow-list assertions are the
  existing coverage surface for new themes; extended, not duplicated.

## Simple User Flow

1. Operator/developer runs the app with `FORUM_SITE_ID=chouse`.
2. The page loads with the chouse theme applied by default — no stored
   preference needed.
3. The user can still switch to any other theme via the existing theme
   menu, same as on zenmemes.
4. zenmemes deployments and users see no change.

## Success Criteria

- A chouse-profile instance with no stored theme preference renders the
  new chouse theme, not the shared `auto` default it uses today.
- The chouse theme is selectable from the theme menu and is visually
  distinct at a glance from every other listed theme and from Red Bull's
  actual branding.
- Every existing theme renders unchanged after this change.
- `php tests/run.php` passes with the allow-list updated.
