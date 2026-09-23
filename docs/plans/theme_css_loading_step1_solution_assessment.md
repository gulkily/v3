# Theme CSS Loading — Step 1: Solution Assessment

## Problem Statement

The 94 KB `site.css` ships every theme on every page; split theme rules into per-theme files, prioritize the visitor's resolved theme, and load the others in the background so switching remains immediate.

## Option A: Client-resolved active stylesheet with background theme loading

Keep shared and critical rules in `site.css`, move each explicit theme's variables, overrides, and menu-swatch rules into a fingerprinted `theme-<name>.css`, and have the existing head theme resolver request the resolved active file first.

Implementation: expose a fingerprinted theme-file manifest to the inline resolver, append the active `<link rel="stylesheet" fetchpriority="high">` before first paint, then append the remaining scoped stylesheets after initial parsing at low priority.

Pros:
- Matches the existing `localStorage`/system-preference selection before CSS begins loading, including `auto`.
- Delivers the smallest theme-specific initial payload and preserves instant switching once background requests complete.
- Keeps theme selection client-side; no new server-side state or request variance.

Cons:
- Requires careful source extraction so shared rules and the unscoped light fallback remain in the base stylesheet.
- A very early switch can precede completion of a background theme request unless the UI accounts for that short window.

## Option B: Server-selected active stylesheet backed by a theme cookie

Persist the selected theme in a cookie so the renderer can emit the active per-theme stylesheet normally, then load the other files after page load.

Implementation: update both the cookie and existing storage on each selection, use the cookie or site default to render one high-priority theme `<link>`, and let deferred client code add the remaining fingerprinted files.

Pros:
- Lets HTML declare the active stylesheet without a script creating the critical link.
- Works for first paint even when JavaScript is unavailable after a theme has been selected.

Cons:
- Duplicates theme persistence and must reconcile stale cookie, local storage, and `auto` system-preference behavior.
- Varies otherwise-cacheable HTML by preference and still cannot know a first visitor's local preference.

## Option C: Static links for every theme with non-blocking media/loading attributes

Split the files but emit all theme links in the layout, marking only the active one as render-blocking and relying on browser scheduling for the rest.

Implementation: retain a normal active stylesheet link while rendering the other scoped files with deferred media or preload-to-stylesheet attributes, changing attributes only when a theme is selected.

Pros:
- Keeps the asset list declarative in the template and requires little theme-toggle change.
- All files can be resident before a user switches themes.

Cons:
- Browser fetch priority for deferred stylesheet techniques is inconsistent and can still compete with critical assets.
- The layout still needs client-side logic to identify the locally stored active theme, making the apparent simplicity misleading.

## Recommendation

**Option A.** It directly uses the already-early client-side resolution, avoids a second persistence mechanism, and gives the active theme clear network priority while warming all scoped alternatives for instant later switches.

Reply **Approved Step 1** to proceed to the feature description.
