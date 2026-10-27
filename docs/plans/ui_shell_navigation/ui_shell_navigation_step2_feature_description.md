> **Feature plan:** [Step 1](./ui_shell_navigation_step1_solution_assessment.md) · [Step 2](./ui_shell_navigation_step2_feature_description.md) · [Step 3](./ui_shell_navigation_step3_development_plan.md) · [Step 4](./ui_shell_navigation_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_shell_navigation` ([ledger](../effort_estimation_trial.md#cycle-v3ui_shell_navigation)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.85**

Step 1 approved 2026-10-27: Option B, the new shell as a ZenMemes choice of the `navigation` presentation slot ([mockup](../ui_redesign/mockups/shell.html)).

## Problem

ZenMemes puts seven destinations and the theme controls in one header row, which costs two rows on a phone before any content and has no single place to start a post.

## User stories

- As a reader on a phone, I want the page content first and navigation at my thumb so that I can read without scrolling past the header.
- As a member, I want one New post button on every page so that I can start a thread from anywhere.
- As an approved member, I want Messages in the main navigation with its unread indicator so that I notice new messages.
- As an occasional user, I want Tools, About, Invite and the theme choice in one account menu so that the main bar stays simple and nothing disappears.
- As the operator of another site, I want my site's header unchanged so that this redesign doesn't alter it.

## Core requirements

- ZenMemes renders the new shell on every page: a top bar with Board, Users and Messages (when permitted), one New post and an account menu; on phones, a bottom tab bar (Board, Users, New post, Messages or About, You) instead of the header navigation row.
- Every destination reachable today stays reachable for the same viewer, including the members-only Lobby/Profile/Account set, approval-dependent Messages and Invite, Tools, About, Account, theme choice with the LCD palette, and the thread density toggle where it appears today. Without JavaScript, every destination stays reachable.
- The other site profiles render today's shell unchanged; ZenMemes can switch back through its profile.
- Existing navigation behavior keeps working: the Messages unread indicator, live navigation refresh from the feature-flags page, invite navigation, saved theme choice, and keyboard and screen-reader use of the account menu.
- The 13 themes ZenMemes permits, plus Auto, render the new shell legibly on desktop and phone, and the icon themes (Word97, Chicago, Midnight) show icons for the new navigation surfaces.

## Delivery scope

- Work type: application change.
- Includes the shell templates and navigation items, the `navigation` slot and ZenMemes profile setting, shared and theme stylesheets including `theme-nav-icons.css`, scripts that read or refresh navigation, tests, and the copy record in `docs/plans/ui_copy_audit_checklist.md`, plus this cycle's plans and effort ledger bookkeeping.
- Excludes other screens' redesigns (Board, Thread and later cycles), the Tools page itself, and the Ma theme.

## Completion boundary

- Normal entry: any ZenMemes page, on phone or desktop, as a guest, unapproved member, approved member, or in members-only mode.
- End-to-end outcome: the viewer reaches every destination they could before, starts a post from New post, and changes the theme from the account menu.
- Recovery: if navigation fails to refresh live, a reload shows the correct state; without JavaScript, destinations remain plain links; the operator can return ZenMemes to the current shell through its profile.
- Release condition: verification across viewer states, themes and viewports passes, the other sites' headers are unchanged, and existing tests pass.

## Risks

- **Themes break on new markup.** Impact: unreadable or hidden navigation in some themes. Earliest validation: render the shell skeleton in all 13 themes plus Auto before building the menus. Mitigation: reuse today's class names, link addresses and theme variables.
- **Scripts assume today's single nav.** Impact: unread indicator or live refresh stops working. Earliest validation: trace `feature_flags.js`, `private_message_unread.js`, `auth_navigation.js` and `offline_reader.js` against the new structure before Step 3. Mitigation: keep their data attributes and one canonical source of navigation items.
- **The tab bar collides with fixed page elements.** Impact: covered reply dock, composer or Word97 status bar on phones. Earliest validation: check thread and compose pages on a phone viewport early. Mitigation: reserve bottom space; split the tab bar into a follow-up cycle if needed (Step 1).
- **Two shells drift.** Impact: a destination added later appears in only one. Mitigation: both shells render from the same navigation items.

## Shared component inventory

- `navItems()` / `navigationState()` (`TemplateRenderer`): canonical source of destinations per viewer. **Extend** with grouping (main bar or account menu) for the new shell; keep the current output for other sites.
- `partials/nav.php` and `site.css` `.nav` / `.nav-link`: **reuse** for the main bar links.
- `partials/theme_menu.php` with `theme_toggle.js`: **reuse** inside the account menu.
- `partials/thread_density_toggle.php`: **reuse**, kept reachable.
- `PresentationSlotRegistry` `navigation` slot and the ZenMemes site profile: **extend** with an `app` choice.
- `feature_flags.js`, `private_message_unread.js`, `auth_navigation.js`, invite navigation and `offline_reader.js`: **reuse**; adjust only where they assume today's header.
- `theme-nav-icons.css`: **extend** for the tab bar and account menu.
- Account menu, phone tab bar and New post button: **new**; no existing component renders them.
- `partials/tools_nav.php`: unchanged (Tools is cycle 9).

## User flow

1. A visitor opens any ZenMemes page on a phone and sees the content first, with the tab bar at the bottom.
2. They tap New post and reach the thread composer.
3. They tap You, and the account menu lists Profile, Account, Invite, Tools, About and the theme choice for their viewer state.
4. On desktop, the same destinations appear as the top bar plus the account menu.
5. They choose a theme in the menu; it applies immediately and is remembered.

## Success criteria

- On phones, no ZenMemes page shows a navigation row above its title, and every page shows the tab bar.
- For each viewer state (guest, unapproved, approved, members-only), every destination listed today is reachable, with and without JavaScript.
- The Chouse, QDB and MIT Rap Club headers render the same as before.
- All 13 permitted themes plus Auto pass a desktop and phone visual check; the three icon themes show icons on the new surfaces.
- The unread indicator, live navigation refresh, invite navigation and theme persistence work; existing navigation tests pass and new ones cover the slot and the item grouping.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Approval and study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 2 handoff | 2026-10-27 | 0.85 (reaffirmed) | v1 / R1 / P2, baseline `83fba59802ce` | Claude, single exposed rater; additionally inspected the scripts that read navigation | Requirements match the approved Option B; no scope change |

Earlier snapshots: [Step 1 assessment](./ui_shell_navigation_step1_solution_assessment.md#effort-study).
