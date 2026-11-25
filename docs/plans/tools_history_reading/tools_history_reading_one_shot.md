Cycle: `v3/tools_history_reading`; Mode: one-shot; Record/index: [plans index](../README.md). Follows item 1 of the [browsing tree checklist](../browsing_tree_checklist.md) ([nav_tree](../nav_tree/nav_tree_step4_implementation_summary.md#after-step-4---merge-with-main-for-the-pr), merged in PR #420).

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/tools_history_reading`; host ledger [tools_history_reading.md](../effort_ledger/tools_history_reading.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.08**

Status: implemented and committed; next permitted action: maintainer review (PR).

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

Planning commit: `0703719e3` (plan and index entry only, before any implementation).

Study history:
- 2026-11-25 UTC, intake and execution entry: **0.08**. Recorded after measuring the three fixes, so not a clean intake. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

Dated execution history:
- 2026-11-25: `ToolsPageSupport::registry()`: History's group goes from `device` to `reading`, listed after Forte. `ToolsRegistryTest` expects Activity, Forte, History in Reading.
- 2026-11-25, discovery: Underground Dark's last button ends at 720.4 px, not 720 (the pre-change measurement was rounded), so the check still failed by 0.4 px. Stopped and asked; the maintainer chose to skip Underground Dark like Underground, its light twin with the same layout. This changes the exclusion stated above ("the test's list of excluded themes stays as it is"); the authorization for it is that answer.
- 2026-11-25: `tests/browser/tools_launcher.mjs`: Underground Dark joins the skip list; the comment gives 24 tools and the margins left. `docs/concepts/tools_launcher.md`: History under Reading, the fit numbers, Underground Dark among the themes that scroll.

Study history, closeout:
- 2026-11-25 UTC: retrospective scope assessment **0.08**, as projected. The sub-pixel miss cost one question and a one-word test change. Claude, single exposed rater. Maintainer judgment pending; measured time unknown.

Outcome: delivered. With every flagged tool on, every theme the check covers fits a 1280×720 window: default themes 711.8 px, Sticker 718.8, LCD 665, Chicago 657. Little room is left; the next tool added to any three-column theme's launcher will likely need a regroup.

Verification:
- Passed: `./v3 test --smoke ToolsRegistryTest NavIconSetsTest VisitHistoryPageTest`: 498 run, 498 passed. Full suite not run.
- Passed: `node tests/browser/tools_launcher.mjs` on zenmemes (48 theme and width cases) and with `FORUM_SITE_ID=chouse` (36 cases), with every flagged tool on.
- Looked at: the Gloss launcher at 1280 px (History third in Reading, icon present).
- `git diff --check`: passed.
- Not checked: the live site; phones beyond the check's 390 px cases.
- Inapplicable: schema, read model, flags, new destinations.

Final review: the diff touches `ToolsPageSupport.php`, `ToolsRegistryTest.php`, `tools_launcher.mjs`, the Tools launcher concept entry and plan records. No tool added, renamed or removed; no dependency; Deploy now is enough.

Commit/merge/publication status: completion commit on `feat/tools_history_reading` (hash in the PR); not merged.

Debug: [debug log](./tools_history_reading_debug_log.md).

Enhancement requests: none.

Attribution: Claude · 2026-11-25 UTC · Model: claude-opus-5-5
