Cycle: `v3/preferences_page`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/preferences_page` — [host ledger](../effort_estimation_trial.md#cycle-v3preferences_page); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.15**
Status: planned; next permitted action: implement on `feat/preferences_page`

## Original request

Thread https://zenmemes.com/threads/thread-20261114195441-c17a855d by the maintainer (ilyag), 2026-11-14, tagged #meta: "i think it is about time we had a client-side preferences page?" Body: "please link any existing requests for this here". New feedback item PF-20261114-F01.

Related earlier requests found on the site and in `docs/feedback/items/` (to be linked in the thread reply):

- https://zenmemes.com/threads/thread-20261110165717-b0ca1d92 — "speaking of user/client preferences, we need those." (no item)
- https://zenmemes.com/threads/thread-20261104212209-15935596 — localStorage browser/editor, split from the "You" button wishlist (PF-20261104-F18)
- https://zenmemes.com/threads/thread-20261104211858-d516eef0 — remember expanded Advanced sections (PF-20261104-F16)
- https://zenmemes.com/threads/thread-20261103082445-475cead1 — the "You" button wishlist, items c, f and i (F16, F18, PF-20261104-F22 sign-in debug display, off by default)
- https://zenmemes.com/threads/thread-20261013203624-dc0a5e78 — a toggle for all the AI features (no item; site-level more than browser-level)
- https://zenmemes.com/threads/thread-20260817033404-edc710e7 and https://zenmemes.com/threads/thread-20260826015827-4bf5903a — compact view should also affect the title bar and menu buttons (no items)

## Authorization

The maintainer, in a Claude session on 2026-11-14, pasted the thread link and wrote: "please act on this, pr allowed". Read under `docs/agents/thread_to_pr.md` as the go-ahead for a one-shot cycle on the bounded outcome below, pushing the branch, opening a PR and replying in the thread. Merging and deploying are not covered.

## Outcome and work type

Application change. Normal entry: Tools → Preferences (`/tools/preferences/`), in the "Your device" group of the Tools launcher and Tools bar.

Outcome: one page that lists the settings this browser already keeps, shows each one's current value from first paint, and lets the visitor change or reset it there:

- Theme (every theme this site permits, Auto included), applied through the theme menu's own selection path; link to the /themes catalog.
- Thread view (Comfortable / Compact), while the thread density flag is on.
- Reading margins: whether a custom column is set, with Reset, while the reading margins flag is on.
- Phone tabs: forget the learned tabs and go back to the starting ones.
- Page themes: how many pages this browser has left a page theme on, with Show them again.
- Welcome note: hidden or shown for this browser, with Show again.
- Usage log recording state, linking to the Usage log, while the usage reports flag is on.
- Reset all: clears every key above and nothing else (keys, drafts, outbox and message caches stay).

Excluded (follow-ups): a general localStorage browser/editor (F18), remembering expanded sections (F16), a sign-in debug display (F22), LCD palette choice on this page (it stays in the theme menu), server-side or per-account preferences, and a link from the You menu.

## Approach and risks

The page is server-rendered static HTML; a small inline script right after the controls reads storage and sets each control and value before first paint (as `visitor_intro.php` and the calendar do), so the page looks final on first paint. A page script (`preferences.js`) handles changes.

Considered: putting the page in the You (account) menu as a new navigation destination. That changes the menu the maintainer already wants to rethink and adds a destination to the phone More panel. A Tools entry in "Your device" sits beside Outbox, Bookmarklets and Usage Log, the other per-browser tools, and needs only the Tools icon set work. Chosen: Tools.

Risk: a second theme control on the page repeats the header's theme menu. Accepted, since the page's purpose is one place for all of them; it goes through `window.forumSelectTheme` so storage, page-theme exits and stylesheet loading stay in one code path.

Risk: reading margins state lives inside `reading_margins.js`'s closure; clearing storage from outside would leave its `order` stale. Mitigation: expose a reset function from `reading_margins.js` and call it.

## Reuse decisions

- Route and page: `ToolsPageController` (like `bookmarklets()`), `ToolsPageSupport::registry()` for the launcher and Tools bar, `partials/tools_nav_card.php`.
- Theme list: `ThemeRegistry::permitted(SiteProfileRegistry::active()['permittedThemes'])`, the list the theme menu uses.
- Theme change: `window.forumSelectTheme` from `theme_toggle.js` (already used by `theme_catalog.js`).
- Storage keys: `window.forumBrowserRuntime` (`themeStorageKey`, `threadDensityStorageKey`, `readingMarginsStorageKey`); `forum_nav_slots` and `window.__forumNavSlots.defaults` from `nav_slots.js`/layout; `forum_visitor_intro_hidden`; `forum_usage_log`.
- Reading margins reset: extend `reading_margins.js` with a global reset that calls its own `apply(null)`.
- Layout and controls: existing `card`, `button-row`, `account-key-secondary-button`, `meta` classes; a small preferences stylesheet only for the row layout if the existing classes don't cover it.
- Icons: new glyphs in all three sets of `public/assets/theme-nav-icons.css` (launcher button and Tools bar), as `NavIconSetsTest` requires.

## Concepts touched

None.

## Verification and recovery plan

- `LocalAppSmokeTest`: `/tools/preferences/` renders with the rows; the launcher and Tools bar list it.
- `NavIconSetsTest` for the new icons.
- A browser test (`tests/browser/preferences.mjs`): controls reflect stored values on first paint; changing theme and thread view saves; each reset clears only its key; Reset all leaves identity keys alone.
- Screenshots in the default theme (Gloss on ZenMemes), Word97 and Bubblegum at 390px and 1280px, if Chrome runs here.
- Smoke set plus changed tests only; full suite not run.

## Starting revisions and branch

Host `main` at `a4fe61e5744881cc54d042d741dc9c5ef48e866e`; branch `feat/preferences_page`.

## Planning commit

Pending (this record cannot hold the hash of the commit that first contains it).

## Study history

- 2026-11-14T19:58Z intake: whole-cycle projection **0.15**, scope v1 as above, R1, P2, single exposed rater Claude (claude-opus-5-5). Exposure: rated after reading `theme_toggle.js`, `thread_density_toggle.js`, `nav_slots.js`, `reading_margins.js`, `ToolsPageSupport` and the usage log page; no pristine intake.
- 2026-11-14T19:58Z execution entry: 0.15 reaffirmed.

## Dated execution history

- 2026-11-14T19:58Z: no existing item for the thread; created PF-20261114-F01. Related items F16, F18 and F22 are left untriaged as follow-ups.
