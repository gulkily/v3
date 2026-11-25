Cycle: `v3/tools_history_reading`; Mode: one-shot; Record/index: [plans index](../README.md). Follows item 1 of the [browsing tree checklist](../browsing_tree_checklist.md) ([nav_tree](../nav_tree/nav_tree_step4_implementation_summary.md#after-step-4---merge-with-main-for-the-pr), merged in PR #420).

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/tools_history_reading`; host ledger [tools_history_reading.md](../effort_ledger/tools_history_reading.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.08**

Status: planned; next permitted action: implementation.

Feedback item: none matches (searched `docs/feedback/items/` for the launcher, its groups and History).

Original request (maintainer, 2026-11-25): "Open from #420: tests/browser/tools_launcher.mjs fails its 1280×720 check when every flagged tool is listed (24 now). … This is my layout decision. Lay out the options (regroup the launcher, accept scrolling and widen the test's exclusions, or move History) and wait for my choice before changing anything. Run it as a one-shot unless I say otherwise." Claude measured three fixes; the maintainer chose "Move History to Reading".

Authorization: that choice, 2026-11-25, as a one-shot. Exclusions: no tool added, renamed or removed; no spacing, column or theme change; the test's list of excluded themes stays as it is. Merging and deploying are the maintainer's.

Bounded outcome and work type: application change (the Tools launcher's grouping).
1. History moves from Your device to Reading, after Forte. Reading holds Activity, Forte and History; Your device drops from seven tools to six.
2. With every flagged tool listed, the last launcher button at 1280×720 ends at or above 720 px in every theme the check covers.

Approach and reuse decisions:
- The registry in `ToolsPageSupport::registry()` is the one list the launcher and the Tools bar build from. The change is History's `group` value and its place in the list (tools are listed group by group). No new component.
- History goes last in Reading, so the bar's group divider stays on Activity, which has no flag.
- Tool icons are keyed to `href`, so History keeps its icon in every set; Reading's heading glyph already exists.

Risks:
- **Almost no room left.** Measured before the change, with History moved: default themes 712 px, Sticker 719, Underground Dark 720. Impact: the next tool added anywhere breaks the check again. Mitigation: record the margins in the test comment and the concept entry so the next cycle sees them.
- **History reads as device data, not reading.** It never leaves the browser, which is what Your device groups. Impact: a reader looking under Your device finds it one heading down. Accepted by the maintainer's choice; the History page itself is unchanged.

Completion conditions: Tools shows History under Reading; `tests/browser/tools_launcher.mjs` passes on zenmemes and chouse with every flagged tool on. Deploy with Deploy now; no schema or flag change.

Concepts touched: [Tools launcher](../../concepts/tools_launcher.md) (changed: History's group, fit measurements), [visit tree](../../concepts/visit_tree.md) (used unchanged; it says only "Tools → History").

Verification and recovery plan: `./v3 test --smoke ToolsRegistryTest NavIconSetsTest VisitHistoryPageTest`; `node tests/browser/tools_launcher.mjs` on zenmemes and with `FORUM_SITE_ID=chouse`; `git diff --check`. Full suite not run. Recovery: setting History's group back to `device` restores the old launcher.

Starting revisions and branch: `feat/tools_history_reading` from `origin/main` `8f8730e17` (the #435 merge); the branch's first commit closes out nav_tree's checklist and ledger entries.

Planning commit: pending.

Study history:
- 2026-11-25 UTC, intake and execution entry: **0.08**. Recorded after measuring the three fixes, so not a clean intake. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.
