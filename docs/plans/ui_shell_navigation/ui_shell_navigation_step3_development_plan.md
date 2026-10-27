> **Feature plan:** [Step 1](./ui_shell_navigation_step1_solution_assessment.md) · [Step 2](./ui_shell_navigation_step2_feature_description.md) · [Step 3](./ui_shell_navigation_step3_development_plan.md) · [Step 4](./ui_shell_navigation_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_shell_navigation` ([ledger](../effort_estimation_trial.md#cycle-v3ui_shell_navigation)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.90**

Step 2 approved 2026-10-27. Mockup: [shell.html](../ui_redesign/mockups/shell.html).

## Completion Contract

- Normal entry: any ZenMemes page, phone or desktop, as a guest, unapproved member, approved member, or in members-only mode.
- End-to-end outcome: the viewer reaches every destination available today, starts a post from New post, and changes the theme from the account menu; phones show the tab bar and no header navigation row.
- Required recovery: a failed live navigation refresh keeps the reload message; without JavaScript, the account menu opens and every destination is a plain link; the operator returns ZenMemes to today's shell with one profile setting.
- Deployment/external verification: rebuild baked artifacts (`php scripts/build_static_artifacts.php`, after removing a stale `public/index.html`) so thread, profile and tag routes get the new shell; production needs the same rebuild. No production deploy is part of this cycle.
- Release condition: the viewer-state matrix, 13 permitted themes plus Auto, icon themes, other-site headers and existing PHP and browser tests all pass.

## Key Risks

- **High risk (usability):** a destination becomes unreachable for some viewer state. Early validation: a PHP test of grouped items for each viewer state in Stage 1. Mitigation: grouping comes from the existing `navItems()` list; nothing is dropped, only placed.
- **High risk (rollback):** the switch-back path fails. Early validation: Stage 1 tests render ZenMemes with both `forum` and `app`. Mitigation: today's partials stay untouched for `forum`.
- Scripts assume one `nav.nav` with one Messages link and a unique status ID. Early validation: traced before planning (`feature_flags.js`, `private_message_unread.js`). Mitigation: **one** nav element, placed by CSS in the top bar on desktop and at the bottom on phones; no duplicated links or IDs.
- Themes key on `.site-header`, `.nav-link` and link addresses. Early validation: skeleton render in all 13 permitted themes plus Auto in Stage 3. Mitigation: keep those classes and addresses; style new parts with theme variables.
- The fixed tab bar covers the reply dock, composer or Word97 status bar. Early validation: phone checks on board, thread and compose in Stage 4. Mitigation: reserve bottom space; split the tab bar to a follow-up if it can't be resolved in Stage 4.

## Stage 1

**Estimate: 0.15**

- Goal: ZenMemes can select the new shell, and navigation items carry their placement.
- Dependencies: none.
- Expected changes: an `app` choice in the `navigation` presentation slot, selected in the ZenMemes profile; `navItems()` adds a placement (main bar, account menu) and a New post entry for `app` only; `layout.php` picks the shell partial by slot; `forum` and `qdb` output unchanged.
- Verification approach: PHP tests for slot validation, placements per viewer state (guest, unapproved, approved, members-only, Tools hidden), and byte-identical headers for Chouse, QDB and MIT Rap Club; existing navigation tests pass.
- Estimate details: driver: four viewer states and the members-only branch; uncertainty: low.
- Risks or open questions: covered by the two high risks above.
- Canonical components/API contracts touched: `PresentationSlotRegistry`, `SiteProfileRegistry` (ZenMemes), `TemplateRenderer::navItems()`, `navigationState()` (unchanged output contract).

## Stage 2

**Estimate: 0.12**

- Goal: the new shell's markup and account menu work on every ZenMemes page, with and without JavaScript.
- Dependencies: Stage 1.
- Expected changes: new shell partial with wordmark, the single nav (Board, Users, Messages when permitted), New post, and an account menu on a native disclosure holding Profile, Account, Invite, Tools, About, the theme menu with LCD palette, and the density toggle where shown.
- Verification approach: render tests for each viewer state; keyboard open/close and focus order; JavaScript disabled reachability of every destination.
- Estimate details: driver: account menu accessibility and hosting the existing theme popover; uncertainty: medium.
- Risks or open questions:
  - Impact: theme popover clipped or mis-positioned inside the menu.
  - Early warning / validation: open the theme popover inside the menu before styling.
  - Mitigation: render theme options inline in the menu instead of a nested popover.
- Canonical components/API contracts touched: `partials/nav.php`, `partials/theme_menu.php`, `partials/thread_density_toggle.php`.

## Stage 3

**Estimate: 0.10**

- Goal: the desktop top bar looks like the mockup without layout shift.
- Dependencies: Stage 2.
- Expected changes: top bar and account menu styles in the critical part of `site.css`, using theme variables and today's `.site-header` / `.nav-link` classes.
- Verification approach: desktop screenshots in Light and Dark; skeleton render in all 13 permitted themes plus Auto (the early theme check); no shift of primary actions while loading.
- Estimate details: driver: critical-CSS placement; uncertainty: low.
- Risks or open questions: themes that rebuild the header (Word97, Chicago) are expected to need Stage 6 fixes; record them rather than fix here.
- Canonical components/API contracts touched: `site.css` critical section.

## Stage 4

**Estimate: 0.20**

- Goal: on phones the same nav becomes the bottom tab bar.
- Dependencies: Stage 3.
- Expected changes: phone layout places the nav at the bottom with Board, Users, New post, Messages or About, and You (opens the account menu); header navigation row hidden; bottom space reserved; safe-area padding.
- Verification approach: 390px screenshots of board, thread, compose, account and tools for each viewer state; reply dock, composer, app-version banner and Word97 status bar not covered; landscape check.
- Estimate details: driver: interaction with fixed page elements; uncertainty: medium-high.
- Risks or open questions:
  - Impact: covered controls on thread or compose pages.
  - Early warning / validation: thread and compose checked first.
  - Mitigation: page-specific bottom offset; otherwise split the tab bar into a follow-up per Step 1.
- Canonical components/API contracts touched: the single nav from Stage 2; `site.css`.

## Stage 5

**Estimate: 0.15**

- Goal: every navigation script works with the new shell.
- Dependencies: Stages 2–4.
- Expected changes: adjust only what assumes today's header in `feature_flags.js` (live refresh), `private_message_unread.js`, `auth_navigation.js`, invite navigation and `theme_toggle.js`; update affected browser tests (`nav_unread_outline`, `nav_active_theme_styles`, `theme_cycle`, `lcd_palettes`).
- Verification approach: live refresh from the feature-flags page, unread count and its unavailable state, invite navigation, theme select, cycle and persistence; those browser tests and the feature-flag PHP tests pass.
- Estimate details: driver: live refresh inserting Messages into the nav; uncertainty: low after the trace.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: `/api/feature_flags/navigation` (unchanged contract), `data-private-message-unread`, `data-invite-navigation`.

## Stage 6

**Estimate: 0.15**

- Goal: all 13 permitted themes plus Auto render the shell correctly.
- Dependencies: Stages 2–5.
- Expected changes: fixes in the affected theme stylesheets.
- Verification approach: theme guide sweep (board, thread, tools, users, about) at desktop and about 380px, headed Chrome where scrollbars matter; theme popover leak check; keyboard focus.
- Estimate details: driver: 14 visual passes; uncertainty: medium.
- Risks or open questions: Word97 and Chicago restyle the header heavily and may need the most work.
- Canonical components/API contracts touched: theme stylesheets.

## Stage 7

**Estimate: 0.08**

- Goal: icon themes show icons on the new surfaces.
- Dependencies: Stage 6.
- Expected changes: `theme-nav-icons.css` entries for the tab bar, New post and account-menu destinations in Word97, Chicago and Midnight.
- Verification approach: visual check of each icon in each icon theme, desktop and phone (`AGENTS.md` navigation-icon rule).
- Estimate details: driver: three themes times the new destinations; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: `theme-nav-icons.css`.

## Stage 8

**Estimate: 0.10**

- Goal: acceptance and closeout.
- Dependencies: Stages 1–7.
- Expected changes: baked artifacts rebuilt; copy changes recorded in `docs/plans/ui_copy_audit_checklist.md`; Step 4 summary closeout and effort ledger update.
- Verification approach: viewer-state matrix by JavaScript on/off by phone/desktop; other-site headers unchanged; `php tests/run.php` and the navigation browser tests; switch ZenMemes back to `forum` and confirm today's shell.
- Estimate details: driver: matrix breadth; uncertainty: low.
- Risks or open questions: a full-suite run may be long; report partial runs honestly.
- Canonical components/API contracts touched: none new.

## Execution and study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 3 handoff | 2026-10-27 | 0.90 | v1 / R1 / P2, baseline `83fba59802ce` | Claude, single exposed rater; additionally traced the nav scripts, browser tests and static artifact rebuild | New understanding, not scope change: browser tests to update and the baked-artifact rebuild add verification work |

Stage estimates are stage work only and are not summed; the cycle estimate is judged independently. Earlier snapshots: [Step 1](./ui_shell_navigation_step1_solution_assessment.md#effort-study), [Step 2](./ui_shell_navigation_step2_feature_description.md#approval-and-study-checkpoint).

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*
