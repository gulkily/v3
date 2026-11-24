Cycle: `v3/tools_developer_group`; Mode: one-shot; Record/index: [plans index](../README.md). Tracked in the [backlog checklist](../backlog_checklist.md) under "Later".

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/tools_developer_group`; host ledger [tools_developer_group.md](../effort_ledger/tools_developer_group.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.10**

Status: planned; next permitted action: implementation.

Feedback item: none matches (searched `docs/feedback/items/` for the launcher's groups and a developer section). Not allocating one for a one-shot the maintainer asked for directly.

Original request (maintainer, 2026-11-24): "perhaps tools should have a developer section? what would go into it?" Claude proposed a Developer group holding Backlog and Platform Docs, with the operator tools and Agent Prompts left where they are, and asked whether Platform Docs should move too. Reply: "great. let's make the modification to tools page".

Authorization: that reply. Platform Docs moves as proposed; the question about it was not answered separately. Exclusions: no tool added, renamed or removed; no change to what any tool does or who may use it; the close button and the cleanup of finished items asked for in the same message are separate work. Merging and deploying are the maintainer's.

Bounded outcome and work type: application change (the Tools launcher's grouping).
1. `ToolsPageSupport::groups()` gains `developer` ("Developer") between Community and Operator.
2. Platform Docs and Backlog move from Reading to Developer, in that order. Reading keeps Activity and Forte.
3. The Developer heading has a glyph in each icon set: pixel art (Word97, Chicago, Midnight), smooth lines (Gloss, Gloss Dark, Engagify, Spearmint) and cute pixel art (Bubblegum). A pair of angle brackets around a slash.
4. The Tools bar gains one divider. Nothing else on any tool page changes.

Approach and reuse decisions:
- The registry is the one list the launcher and the bar build from; the change is two `group` values, their position in the list (tools are listed group by group) and one entry in `groups()`. No new component.
- The template already skips a group with no tools. Platform Docs has no flag, so Developer always has at least it; with the Backlog flag off the group shows Platform Docs alone. Platform Docs is listed first so the bar's divider sits on a tool that is always there.
- Tool icons are keyed to `href`, so the two moved tools keep theirs. Only the heading glyph is new, added beside the other four heading rules in `public/assets/theme-nav-icons.css`.

Risks:
- **A fifth heading on the launcher.** Impact: the card grows by one row and may no longer fit 1280×720 in some theme. Validation: `tests/browser/tools_launcher.mjs` checks every tool in view at 1280×720 in every permitted theme on both sites.
- **A heading with no glyph in one icon set.** Impact: a bare heading among four with icons. Validation: the same check asserts a glyph on each heading in each icon theme; look at one screenshot per set.

Completion conditions: Tools shows five groups in order, Developer holding Platform Docs and, with the Backlog flag on, Backlog; the bar shows the same order with one more divider; every icon theme draws the Developer glyph. Deploy with Deploy now; no schema or flag change.

Concepts touched: [Tools launcher](../../concepts/tools_launcher.md) (changed: five groups), [theme navigation icons](../../concepts/theme_navigation_icons.md) (changed: one more heading glyph), [backlog view](../../concepts/backlog_view.md) (wording only if it names the group).

Verification and recovery plan: `./v3 test --smoke ToolsRegistryTest NavIconSetsTest UsageNamesTest`; `node tests/browser/tools_launcher.mjs`; `git diff --check`. Full suite not run. Recovery: moving the two `group` values back restores the old launcher.

Starting revisions and branch: `feat/tools_developer_group` from `origin/main` `b4a4eec4e` (the #395 merge).

Planning commit: pending.

Study history:
- 2026-11-24 UTC, intake and execution entry: **0.10**. Recorded after reading the registry, the template, the icon stylesheet and the tests, so not a clean intake. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

Enhancement requests:
- A close button for finished backlog items (maintainer, same message). Not in this cycle; its own cycle, pending his choice of approach.
- The backlog holding many finished items (maintainer, same message). Not in this cycle; findings reported in the session.

Attribution: Claude · 2026-11-24 UTC · Model: claude-fable-5-2
