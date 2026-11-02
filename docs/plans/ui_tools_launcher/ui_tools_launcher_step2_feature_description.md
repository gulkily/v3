> **Feature plan:** [Step 1](./ui_tools_launcher_step1_solution_assessment.md) · [Step 2](./ui_tools_launcher_step2_feature_description.md) · [Step 3](./ui_tools_launcher_step3_development_plan.md) · [Step 4](./ui_tools_launcher_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_tools_launcher` — [ledger](../effort_estimation_trial.md#cycle-v3ui_tools_launcher) (standing P2 enrollment)
Extended estimates: enabled

**Cycle estimate: 0.45**

Direction: Step 1 Option A, approved 2026-10-31 ("approved step 1").

## Problem

/tools/ shows every tool twice in an order nobody would recognise, with long descriptions, and includes Account, which the main navigation already has.

## User stories

- As a visitor, I want to find a tool by what it's for so that I don't read through sixteen entries.
- As an operator, I want operator tools together so that I can see at a glance where site controls live.
- As someone inside a tool, I want the Tools bar to follow the same groups so that the layout I learned on /tools/ still applies.
- As a theme author, I want stable hooks on each tool and group so that later themes can style them without changing markup.

## Core requirements

- /tools/ lists each tool exactly once, in four groups: Your device (Outbox, Offline reading, Bookmarklets), Reading (Activity, Forte, Platform docs), Community (User access, Visitor statistics), Operator (Feature flags, System status, SQLite viewer, Backup, LLM exchanges, Fastmod, Operations).
- Each tool has a one-line description saying what it is for; "System State" becomes "System status". Account is no longer listed in Tools.
- Every tool page's Tools bar shows the same tools in the same group order, with the current tool marked.
- Each tool carries a stable `data-tone` key and each group heading a `data-shape` hook; server HTML is the same for every viewer.
- Existing rules stay: Backup is hidden when both backups are disabled; tools that refuse a viewer still refuse them.

## Delivery scope

Work type: application change (templates, the tool registry, Tools CSS, tests, and the UI copy checklist record).

## Completion boundary

- Normal entry: Tools in the main navigation, and the Tools bar inside any tool.
- End-to-end outcome: every tool is reachable once from /tools/ and from each tool's bar, on every site, in every permitted theme, on phone and desktop, with icons in Word97, Chicago and Midnight.
- Recovery: none new; a tool a viewer can't use keeps its existing refusal.
- Release condition: theme matrix checked with contact sheets, Bookmarklets page unchanged, affected tests updated and passing.

## Risks

- **Bookmarklets shares the launcher's classes, styles and icon rules.** Impact: regrouping could change the Bookmarklets page. Earliest validation: before changing shared styles, screenshot Bookmarklets in the four icon themes. Mitigation: new group styles apply only to the Tools page; Bookmarklets is checked in every stage that touches shared CSS.
- **Themes style the launcher buttons differently** (Word97, Midnight and QDB override them). Impact: overflow or broken tiles in some themes. Validation: render /tools/ in every permitted theme at 390px and 1280px early. Mitigation: keep the button class and links so theme rules and icons still match.
- **Tests and docs assume the current list, labels and order.** Impact: false failures, or stale "Tools → Account" guidance. Validation: search tests and docs before the first change. Mitigation: update each deliberately and record it.

## Shared component inventory

- `ToolsPageSupport::registry()` / `navOptions()`: the single tool list; extended with group, tone and short description (reuse).
- `templates/pages/tools.php`: the launcher; rebuilt as one grouped list (extend).
- `templates/partials/tools_nav.php`: the Tools bar on every tool page and in `TemplateRenderer::navigationState`; reordered by group (extend).
- `public/assets/tools.css` and the Word97, Midnight and QDB launcher rules: reused; group styles added for the Tools page only.
- `public/assets/theme-nav-icons.css`: icons keyed to tool links; reused unchanged (all tools already mapped).
- `templates/pages/bookmarklets.php` and `tools_bookmarklets.js`: share launcher classes; must stay unchanged.

## Concepts touched

- Tools launcher (proposed entry, created with this cycle): one registry builds the launcher and every Tools bar.
- Theme navigation icons (proposed entry, used unchanged): icons keyed to tool links in three themes.

## User flow

1. Open Tools from the main navigation.
2. Scan the four group headings, then pick a tool by its one-line description.
3. Inside the tool, use the Tools bar (same groups and order) to move to another tool.

## Success criteria

- /tools/ contains each of the 15 tools exactly once and no Account entry, on ZenMemes, Chouse, QDB and MIT Rap Club.
- Each tool's bar lists the same 15 tools in the same order, current one marked.
- No horizontal overflow and no page errors in every permitted theme at 390px and 1280px; icons visible in Word97, Chicago and Midnight; contact sheets reviewed.
- The Bookmarklets page matches its pre-change screenshots in the four icon themes.

*— Claude · 2026-10-31 UTC · Model: claude-opus-5-5*

## Study checkpoint

| Checkpoint | UTC | Scope | Estimate | Reason |
| --- | --- | --- | --- | --- |
| Step 2 handoff | 2026-10-31 | v1 / Option A | 0.45 | New understanding, not changed scope: the Tools bar on 15 tool pages follows the groups, and the launcher styles are shared with Bookmarklets, so more surfaces need checking. Step 1 0.40 retained. |

Same rater (Claude, claude-opus-5-5, single exposed), R1 (hashes verified 2026-10-31), P2, starting revision `41a6dbe9c`. No implementation, measurement or accepted outcome.

## Approval

Awaiting `Approved Step 2`.
