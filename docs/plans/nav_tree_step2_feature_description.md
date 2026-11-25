> **Feature plan:** [Step 1](./nav_tree_step1_solution_assessment.md) · [Step 2](./nav_tree_step2_feature_description.md) · [Step 3](./nav_tree_step3_development_plan.md) · [Step 4](./nav_tree_step4_implementation_summary.md)

Debug mode: enabled ([log](./nav_tree_debug_log.md))

Effort study: `railspike-effort` / `v3/nav_tree`; [host ledger](./effort_ledger/nav_tree.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.65**

Epic: [browsing tree checklist](./browsing_tree_checklist.md), item 1 of 3. Approved direction: Step 1 Option B (per-tab cursors, a pane cursor, the referrer as fallback), approved 2026-11-25.

## Problem

The browser keeps history as a flat list, so a reader cannot see which page led to which. A reader who branched off to read several threads cannot get back to the page where the branches started.

## User stories

- As a reader, I want Tools → History to show the pages I opened as a tree, so that I can see where each branch of my reading started.
- As a reader, I want to click any page in that tree and go back to it, so that I can return to an earlier branch.
- As a reader on a wide screen with Beside on, I want pages I opened beside to sit under the page I opened them from, so that the pane's reading is part of the same tree.
- As a reader on a shared device, I want to clear my history, so that the next person cannot see where I went.
- As an operator, I want history behind a feature flag, so that I can turn it off on an instance or if it breaks.

## Core requirements

- A visit is recorded for each page change the reader makes: a page load, or a `pushState` that changes the address. Back and forward move to an existing visit and add none. `replaceState` updates the current visit's address. A reload adds none.
- Each visit records its address, title, time, parent and whether it was shown beside. Visits are append-only records with an ID, kept in this browser only. Nothing is sent to the server, and the usage log never reads them.
- Parents follow the Step 1 rules: the tab's current visit, the pane's rules for Beside, and the referrer for a tab with no current visit. Only top-level pages and the Beside pane record visits; other frames, such as the tour, record nothing.
- Tools → History lists the tree, newest branch first, with each visit's title, time and a link. The tree draws before first paint. Clear empties it. The History page itself is never recorded.
- History keeps the latest 2,000 visits and drops the oldest first. A visit whose parent was dropped becomes the top of its own branch.

## Delivery scope

- Work type: application change.
- Completion boundary: a reader turns history on (flag on), reads the site normally, opens Tools → History and sees the tree. Clicking a visit opens that page. Clear empties the tree and the next page starts a fresh one. With the flag off, nothing is recorded and Tools shows no History. Recovery: storage that is full, missing or unreadable leaves pages working and History empty, with no error shown on other pages. Release condition: the vertical slice from Step 1 passes in Chrome, Firefox and Safari, including Beside on desktop.
- Not in scope: richer tree views and bookmarks (`tree_history`), the filmstrip, syncing, drawers and in-page tabs as visits, and offline copies of the History page.

## Proposed decisions for confirmation

These were left to Step 2. Each is a proposal; change any before approving.

- **Flag:** off by default and changeable by the site, in the Experience group, like Open beside.
- **Placement:** Tools → History in the Tools "device" group beside Usage Log, with an icon in every navigation icon set.
- **Changing or signing out of a key keeps history.** History belongs to the browser, not the key; Clear covers shared devices. The nav-copy cache differs because it holds pages rendered for one key.
- **Visits are recorded offline too.** The offline reader's pages are page changes like any other, which keeps offline use close to online use.

## Risks

- **New tabs and `sessionStorage`.** Browsers differ in whether a tab opened from a link copies the opener's storage. Impact: wrong parents in new tabs. Earliest check: a quick test in all three browsers at the start of Step 4. Mitigation: a copied cursor is detected and replaced by the referrer rule.
- **Safari clears stored data after about seven days without a visit.** Impact: an iPhone reader loses history. Earliest check: none needed; it is known. Mitigation: accept it for this cycle, since history is device-only; note it on the History page only if readers ask.
- **Telling a restored pane from a reader's click.** Impact: duplicate pane visits on every main page. Earliest check: the first Beside test in Step 4. Mitigation: `open_beside.js` marks a restore, and the history script skips it.
- **Storage size.** Impact: 2,000 visits with titles could crowd the site's other local data. Earliest check: measure a full history early in Step 4. Mitigation: shorten stored titles, or lower the cap.

## Shared component inventory

- `public/assets/nav_slots.js` and the `forum_nav_slots` key: precedent for one local-storage key read before paint. Reuse the pattern; history gets its own key.
- `public/assets/usage_log.js`: page-view and back-forward capture to reuse in shape. Its records are not reused, because they strip IDs.
- `templates/pages/usage_log.php` and `public/assets/usage_log_page.js`: the Tools page that reads device data and offers Clear. History follows its layout and Clear control.
- `src/ForumRewrite/Tools/ToolsPageSupport.php`, Tools "device" group: where History is listed.
- `public/assets/open_beside.js`: extend with a small signal for restores and Open alone; the pane itself is unchanged.
- The paned board, activity and users readers, the offline reader and `platform_docs.js`: their `pushState` calls are captured unchanged.
- `public/assets/theme-nav-icons.css`: a History icon in every icon set.
- `FeatureFlagRegistry.php`: one new flag.

## Concepts touched

- Proposed *visit tree*: a new concept entry at Step 4.
- [Open beside](../concepts/open_beside.md): changed (restores and Open alone are signalled to history).
- [Tools launcher](../concepts/tools_launcher.md): changed (gains History).
- [Navigation slots](../concepts/navigation_slots.md), [usage reports](../concepts/usage_reports.md), [tour snapshot](../concepts/tour_snapshot.md): used unchanged.

## User flow

1. The operator turns on the history flag.
2. A reader opens the Board, then a thread, goes back, and opens a second thread.
3. On a wide screen with Beside on, the reader opens a third thread beside.
4. The reader opens Tools → History and sees the Board with three child threads, the third marked as beside.
5. The reader clicks the first thread in the tree, and it opens.
6. The reader presses Clear; the tree is empty, and the next page starts a new one.

## Success criteria

- The flow above produces exactly that tree in Chrome, Firefox and Safari.
- Back, forward, reloads, restored panes and early fetches add no visits.
- A second tab on Recent starts its own branch.
- With the flag off, no history key is written and Tools shows no History.
- Smoke tests and the new history tests pass.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Enhancement Requests

- Sync history across a username's devices. Disposition: deferred to a later cycle; append-only records keep it possible. Approval: noted by the maintainer.
- Count in-page changes, such as drawers and in-page tabs, as visits. Disposition: deferred; the maintainer wants to revisit it later. Approval: noted by the maintainer.
- Tree views and tree bookmarks (`tree_history`) and a filmstrip of recent screens (`filmstrip`). Disposition: separate cycles that build on this one.

## Study Snapshots

2026-11-25T07:10Z; scope v2; starting revision `2d7805c8b6d63b0f2ff6ecdd264ec257eaece23b`; reference R1, hashes not re-verified. Claude, single exposed rater, who saw the Step 1 scores.

- Step 2 handoff: **0.65 projected total**, reaffirmed. The proposed decisions add no work beyond Step 1's estimate: the 2,000-visit cap, Clear and the Tools placement were already assumed. Not hours or measured labor; no approval to advance.

Status: Step 2 drafted; awaiting Approved Step 2. Planning records stay on the planning branch `plan/nav_tree-step1`.
