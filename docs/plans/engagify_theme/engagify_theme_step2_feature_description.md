> **Feature plan:** [Step 1](./engagify_theme_step1_solution_assessment.md) · [Step 2](./engagify_theme_step2_feature_description.md) · [Step 3](./engagify_theme_step3_development_plan.md) · [Step 4](./engagify_theme_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/engagify_theme`; [host ledger](../effort_estimation_trial.md#cycle-v3engagify_theme). Standing P2 enrollment. No parent cycle; `v3/theme_directive` (planned on branch `plan/engagify_theme`) depends on this cycle.

Extended estimates: enabled

Cycle estimate: 0.80 (revised; Step 1 0.75)

## Revised Direction

After approving Step 2, the maintainer reversed Step 1 Revision 4 (2026-11-07): "can we please make it still selectable in the theme selector for now, ignore what i said earlier about making it only page-selectable". Engagify is an ordinary selectable theme again, and no page-only state is built. Page themes stay. This revision needs a fresh Approved Step 2.

## Problem

Themes are stylesheets that apply site-wide, so no theme can show controls a viewer clicks, and no page can choose its own theme. The anti-cookie notice thread cannot show the cookie banner joke it describes.

Concepts touched: proposed "page theme" (created: a page that shows a named theme by default); proposed "theme script" (created: code that runs only while its theme is active); [theme navigation icons](../../concepts/theme_navigation_icons.md) (used unchanged). This cycle adds both proposed entries to `docs/concepts/`.

No matching feedback item in `docs/feedback/items/`. [PF-20261104-B08](../../feedback/items/PF-20261104-B08.txt) concerns the notice's wording and stays separate.

## User stories

- As a reader of the cookie notice thread, I want it buried under fake nags, so that the joke lands.
- As that reader, I want one obvious Exit, so that I can read the thread normally once I get the joke.
- As a returning reader, I want the thread to remember that I left, so that it does not nag me again.
- As a theme author, I want to ship a script with my theme, so that it can do more than recolor the page.
- As an operator, I want to give a page its own theme, so that a page can have its own look without changing anyone's saved theme.
- As a curious reader, I want to pick Engagify from the theme menu, so that I can try it anywhere.

## Core requirements

- **Theme scripts.** A theme in `ThemeRegistry.php` may declare a script. The script runs only while its theme is active, and stops and removes what it added when the theme changes.
- **Selectable theme.** Engagify appears in the theme menu and the cycle button like any other theme, and a viewer can save it.
- **Page themes.** The `zenmemes` site profile lists page themes. The first entry gives the thread "Our cookie notice (there isn't one)", `thread-20261102152258-25e82c97`, the Engagify theme. On that page, the page theme beats the site default and the viewer's saved theme.
- **Engagify nags.** Core nags are the rate-us prompt from the mockup, a cookie banner, a newsletter popup with a guilt-trip decline, an "Open in app" bar and a fake notification prompt. Each nag can be dismissed, and every nag offers Exit. The six stretch nags from Step 1 Revision 2 are built only if Step 3 fits them.
- **Exit.** On a page theme's page, Exit or any menu choice turns the page theme off for that page in that browser, and the viewer's own theme shows. Where the viewer chose Engagify themselves, Exit restores the theme they had before it. Both happen without a reload.

Delayed nags break "pages must look final on first paint" on purpose. Step 1 approval covers this exception, which applies only while Engagify is active.

## Delivery scope

- Work type: application change.
- Files: `ThemeRegistry.php`, `SiteProfileRegistry.php`, `TemplateRenderer.php`, `layout.php` and `standalone_layout.php`, `theme_toggle.js`, new `theme-engagify.css` and `theme-engagify.js`, tests, the theme development guide and two concept entries.
- Out of scope: the `theme:` directive (its own cycle), page themes on other sites, the remaining nags beyond stretch, and changes to the cookie notice's wording.

## Completion boundary

- Normal entry: a reader opens the cookie notice thread from the board, a link or a search result.
- End-to-end outcome: the thread shows Engagify with its nags; Exit returns the reader's own theme; a revisit stays on the reader's theme.
- Recovery: if the theme script fails to load, the page still shows the thread with Engagify colors and a visible Exit. Clearing browser storage brings the nags back, which is acceptable. With JavaScript off, the thread shows the site default theme and no nags.
- Release condition: smoke and changed tests pass; the thread is checked by hand at desktop and phone width, from a fresh browser and from one with a saved theme; baked thread pages are rebuilt.

## Risks

- **Baked thread pages.** Thread pages are served from prebuilt files. Impact: a page theme added later does not appear until a rebuild, as the theme guide warns. Earliest validation: build the cookie thread's file and check that it carries its page theme. Mitigation: page themes change only with a deploy, which already rebuilds; document the rebuild in the guide.
- **Theme switching without a reload.** A theme script must stop cleanly when the viewer switches. Impact: leftover nags or timers on another theme. Earliest validation: switch from Engagify to Word 97 and back on the cookie thread. Mitigation: one start and one stop entry point per theme script, called by `theme_toggle.js`.
- **Covering real controls.** Overlays sit over the thread and the composer. Impact: a reader cannot reach Reply or the theme menu. Earliest validation: tab through the page at 380px with every nag open. Mitigation: Exit sits first in each nag's tab order, and the theme menu stays reachable above the overlays.

## Shared component inventory

- `ThemeRegistry.php`: add Engagify as an ordinary entry; extend each entry with an optional script.
- `SiteProfileRegistry.php`: extend each profile with a page theme list, beside `defaultTheme`; add Engagify to the `zenmemes` permitted themes.
- `TemplateRenderer.php` and `layout.php`: extend the pre-paint script to apply a page theme and honor a remembered Exit; reuse the stylesheet swap.
- `standalone_layout.php`: inspect; it chooses a theme separately.
- `theme_toggle.js`: extend to start and stop theme scripts, to record Exit on a menu choice, and to remember the theme before Engagify; reuse its stylesheet loading.
- `templates/partials/theme_menu.php`: reuse unchanged; it lists every permitted theme.
- `StaticArtifactBuilder.php`: reuse unchanged; it bakes whatever the layout renders.
- New: `theme-engagify.css` and `theme-engagify.js`, following the theme guide's file pattern.

## Simple user flow

1. A reader opens the cookie notice thread.
2. A cookie banner and an "Open in app" bar appear; a newsletter popup follows after a few seconds.
3. The reader declines the newsletter, and the rate-us prompt asks whether they enjoy ZenMemes.
4. The reader selects Exit.
5. The thread switches to the reader's own theme and stays that way on the next visit.

## Success criteria

- Engagify appears in the `zenmemes` theme menu, and choosing it applies it on every page.
- Exit after choosing Engagify restores the previous theme.
- A fresh browser on the cookie thread shows Engagify; every other page shows the reader's usual theme.
- Exit, and a menu choice, each switch the thread to the reader's theme without a reload, and a revisit stays switched.
- Every core nag can be dismissed and reached by keyboard at 380px.
- Switching away leaves no Engagify element or timer on the page.

*— Claude · 2026-11-07 UTC · Model: claude-opus-5-5*
*— Revised by Claude · 2026-11-07 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-07T11:54:11Z, `railspike-effort` / `v3/engagify_theme`, Step 2 handoff. Step 1 explicitly approved with Revisions 1–4. Scope v4 unchanged. Starting revision `f343b6269a3b786e4ebe1ab383096f2109461933`; reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Projected total 0.75 → **0.80**, from new understanding, not changed scope: a menu choice must also count as Exit, `standalone_layout.php` chooses themes separately, and baked thread pages carry the page theme. No implementation, measured labor or accepted outcome.

Revised 2026-11-07T11:56:40Z, Step 2 revision. Scope v5: Engagify selectable again, Step 1 Revision 4 reversed by the maintainer after Step 2 approval. Same starting revision, R1, P2 and single exposed Claude rater. Projected total **0.80** reaffirmed: dropping the page-only state is offset by Exit restoring a previous saved theme and by smoke tests that list every theme. No implementation, measured labor or accepted outcome.

Step 2 approved again by the maintainer on 2026-11-07 ("approved step 2") after the revision.

## Enhancement Requests

Carried from Step 1: page themes declared inside a thread, moved to `v3/theme_directive` (planned on branch `plan/engagify_theme`), approval pending there. Stretch nags remain conditional on Step 3. The CSS-only alternative remains declined.
- Keep Engagify selectable in the theme menu (reverses Step 1 Revision 4). Disposition: changed scope after Step 2 approval; Step 2 revised; awaiting renewed Approved Step 2.
