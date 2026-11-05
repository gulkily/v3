> **Feature plan:** [Step 1](./adaptive_nav_step1_solution_assessment.md) · [Step 2](./adaptive_nav_step2_feature_description.md) · [Step 3](./adaptive_nav_step3_development_plan.md) · [Step 4](./adaptive_nav_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/adaptive_nav`; [host ledger](../effort_estimation_trial.md#cycle-v3adaptive_nav). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.85

Approved direction: Step 1 Option B, approved 2026-11-05 ("Approved step 1").

## Problem

On desktop, Search, Calendar, About and Tools hide in the You menu, where nobody looks for them. On phones, six tabs crowd the bottom bar.

## User stories

- As a desktop reader, I want every main destination in the top bar so that I never hunt through the You menu.
- As a phone reader, I want a short tab bar so that each tab is easy to read and tap.
- As a regular phone reader, I want my most-used destination to earn a tab so that I reach it in one tap.
- As a phone reader who pins a destination, I want it to stay put so that my thumb learns where it is.

## Core requirements

- Desktop top bar: Board, Recent, Calendar (when event support is on), Users, Messages (members), Search, About and Tools. The You menu keeps Profile, Account, Invite and the theme controls.
- Phone tab bar: Board, Calendar (when on), New post, personal slots and More. The bar never shows more than five tabs. More opens a full-screen list of every other destination and the theme controls.
- A slot fills itself after a viewer picks the same destination from More a few times. It changes only rarely afterwards. A pinned destination always keeps its slot.
- The server sends the same HTML to every viewer. The browser places slots before first paint, with no layout shift afterwards.
- Phone readers still see the Messages unread indicator when Messages has no tab.

## Delivery scope

Work type: application change. It covers the app navigation layout only; the qdb and forum headers stay as they are.

## Completion boundary

- Normal entry: a reader opens any ZenMemes page on a phone or desktop.
- End-to-end outcome: desktop shows every main destination in the bar. A phone shows at most five tabs. After a few visits to one destination through More, that destination gets a tab and keeps it across visits.
- Recovery: a reader can unpin a slot from More. Cleared browser storage returns the default bar, and every destination stays reachable through More.
- Release condition: browser checks at phone and desktop widths, in the default theme and the three icon themes, show no layout shift after first paint.

## Known user or environment

The maintainer reads ZenMemes on an iPhone. Ordinary entry is the site in mobile Safari. Mismatch: the tab bar is crammed today. Next action: open the deployed site and use More a few times. Success: a calm bar of five tabs or fewer, then a slot that appears and stays. Evidence: the maintainer's own check on that iPhone. Headless browser checks at phone width do not replace it.

## Risks

- **Slots shift the bar after paint.** Impact: tabs jump under the reader's thumb. Earliest validation: extend the existing pre-paint browser check before building slot rules. Mitigation: the server renders every slottable tab hidden; the head script reveals the chosen ones.
- **Feature-flag changes rebuild only the main nav.** Impact: slots vanish until the next page load. Earliest validation: replay the flag toggle with a filled slot. Mitigation: rerun slot placement after the nav refresh.
- **Hidden Messages loses its unread signal.** Impact: members miss private messages on phones. Earliest validation: an unread browser check with Messages out of the bar. Mitigation: show the indicator on More.
- **The cycle grows beyond one day.** Impact: a half-built bar ships. Mitigation: Step 3 orders the fixed layout first, so slots can split into a follow-up cycle.

## Shared component inventory

- Destination list and placement, `navItems()` and the app placement step in `src/ForumRewrite/View/TemplateRenderer.php`: **extend** with desktop, phone and slot placement. It stays the one source of destinations.
- Main nav and app header, `templates/partials/nav.php` and `templates/partials/app_header.php`: **extend**.
- You menu, a native disclosure closed by `public/assets/account_menu.js`: **reuse** as More on phones. A separate More page would duplicate it.
- Head pre-paint script in `templates/layout.php`, with parity in `templates/standalone_layout.php`: **extend** to place slots, as it already does for member-only items.
- Member approval memory, `public/assets/member_approval.js`: **reuse** so member-only destinations never fill a slot for other viewers.
- App shell and tab bar styles in `public/assets/site.css`, and `public/assets/tab_bar_scroll.js`: **extend** the styles; the scroll behavior stays unchanged.
- Icon glyphs, `public/assets/theme-nav-icons.css`: **extend** with More and the new bar placements.
- Browser checks `viewer_nav_prepaint.mjs`, `tab_bar_scroll.mjs`, `nav_active_theme_styles.mjs` and `AppNavigationPlacementTest.php`: **extend**.

## Simple user flow

1. A reader opens ZenMemes on a phone and sees Board, Calendar, New post and More.
2. They open More and choose Recent; it opens as before.
3. After a few such visits, Recent appears as a tab, before first paint on every later page.
4. They pin Users from More; Users takes the second slot and stays.
5. On desktop, the same reader sees every main destination in the top bar.

## Success criteria

- No ZenMemes page shows more than five phone tabs, at 375px wide, in any theme.
- Desktop shows Search, Calendar, About and Tools without opening a menu.
- A slotted tab appears from first paint, with zero layout shift in the pre-paint browser check.
- A filled slot keeps its destination across 20 later More visits to other destinations within one day.
- The maintainer confirms the bar on his iPhone.

## Concepts touched

- [Theme navigation icons](../../concepts/theme_navigation_icons.md): changed (More glyph and new placements).
- [Calendar](../../concepts/calendar.md): used unchanged (its flag decides the Calendar tab).
- [Tools launcher](../../concepts/tools_launcher.md): used unchanged (Tools moves to the desktop bar only).
- *Navigation slots*: proposed new entry describing slot filling, pinning and pre-paint placement.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-05T04:50:00Z, `railspike-effort` / `v3/adaptive_nav`, Step 2 handoff. Scope v2: v1 plus the Messages indicator on More, slot repair after flag refresh and the iPhone acceptance check. Same starting revision `a24e16df`, R1/P2; R1 hashes not re-verified. Single exposed Claude rater, now also exposed to the pre-paint script, member approval memory, tab bar styles and nav browser tests. Projected total **0.85** (Step 1: 0.80, retained above). Reason: new understanding of the code, not added features. No implementation, measured labor or accepted outcome.

## Enhancement Requests

None yet.
