# Theme CSS Loading — Step 2: Feature Description

## Problem

Every page downloads the 94 KB combined stylesheet even though it uses one resolved theme. Theme-specific styling should load independently without delaying first render or making later theme changes perceptible.

## User Stories

- As a visitor, I want my selected theme prioritized on page load so that the site becomes usable with less unnecessary CSS.
- As a visitor using Auto/System, I want the correct light or dark result applied promptly so that the site follows my device preference without a flash.
- As a visitor changing themes, I want every available theme to apply immediately so that the selector remains responsive.
- As an operator, I want theme assets to retain the existing cache-busted delivery behavior so that releases do not serve stale styling.

## Core Requirements

- Keep shared styles separate from independently cacheable files for every explicit theme; preserve all current themes and their visual behavior.
- Prioritize the visitor's resolved active theme at startup, using a concrete-theme cookie only as a hint and client preference as the authority.
- When Auto/System resolves differently from a stored hint, correct the active theme before it is visibly applied and refresh the hint when practical.
- Load non-active theme files without competing with initial render, then keep them ready for immediate theme changes.
- Preserve accessible theme-menu behavior, no-JavaScript styling, fingerprinted assets, and rendered output across dynamic and static pages.

## Shared Component Inventory

- `ThemeRegistry`: reuse as the canonical set of theme names, labels, and modes; extend its asset metadata only if needed to keep the menu and loading behavior synchronized.
- `templates/partials/theme_menu.php`: reuse as the sole theme-selection UI; no new selector is needed.
- `public/assets/theme_toggle.js`: extend the canonical client preference, Auto/System resolution, and change handling; it remains the authority that reconciles a stale startup hint.
- `TemplateRenderer` and `templates/layout.php`: extend the shared page-head asset surface for all standard dynamic pages.
- `AssetFingerprint` and the static-artifact rendering path: reuse the canonical fingerprinted-asset delivery behavior so generated pages match dynamic pages.
- `public/assets/site.css`: refactor its shared and theme-specific styling into the new asset boundaries while retaining it as the shared-style entry point.

## User Flow

1. A visitor opens a page.
2. The page prioritizes the hinted active theme, and the client confirms the visitor's actual selection or Auto/System result.
3. Shared and confirmed active styling render while alternate themes load in the background.
4. The visitor opens the existing theme menu and selects another theme.
5. The chosen theme applies immediately; future navigations prioritize its resolved hint.

## Success Criteria

- Initial navigation requests shared CSS plus the visitor's active theme before alternate theme assets.
- Each explicit theme has its own fingerprinted stylesheet, and shared CSS no longer contains its theme-specific rules.
- Changing among every available theme after background loading produces no network-dependent visual delay or fallback flash.
- Auto/System responds to the browser's light/dark preference and corrects a stale cookie hint.
- Dynamic pages, static artifacts, and no-JavaScript rendering retain the current theme menu and valid styling.

Reply **Approved Step 2** to proceed to the development plan.
