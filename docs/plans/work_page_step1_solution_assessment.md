> **Feature plan:** [Step 1](./work_page_step1_solution_assessment.md) · [Step 2](./work_page_step2_feature_description.md) · [Step 3](./work_page_step3_development_plan.md) · [Step 4](./work_page_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/work_page`; [host ledger](./effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/work_tracking`.

Extended estimates: enabled

Cycle estimate: 0.55

## Original Query

So right now the web UI for this is just looking at the tags/work page?

Yes, please. Thank you for all your help.

## Understood Intent

- The second message accepts the proposal to make a proper work page a work item and write its Step 1.
- Today, after [work tracking](./work_tracking/work_tracking_step4_implementation_summary.md) shipped: `/tags/work` lists items as ordinary threads with no status; each item's thread page shows one status line; `/work.txt` and `/work.md` carry status, claimant and note but are plain text; status changes only by typing a `status:` reply.
- Wanted: one page where people see every item's status at a glance and approved members can change status without typing a directive. Same data and rules as `/work.txt` (latest `status:` line from an approved member wins).
- Committed and pushed before approval so the linked work item can point to it, following the maintainer's handling of the previous parked plan. That does not approve it.

## Problem Statement

People can only see work status by opening each thread or reading plain text, and can only change it by typing a directive.

## Concepts touched

- All options: [work item status](../concepts/work_item_status.md) (used; possibly changed for display rules), [tag browsing](../concepts/tag_browsing.md) (Options A, C).
- Option B: proposed *work page*.
- Status controls: [shared composer](../concepts/shared_composer.md) (used to post the `status:` reply).

## Solution Options

### Option A: Status on the existing tag page

**Estimate: 0.30**

When the tag is `work`, `/tags/work` shows each item's status badge, claimant and stale flag on its card, plus status filter links. Thread pages get status buttons for approved members that post the `status:` reply through the normal composer.

- Pros: no new route; people already know `/tags/work`; smallest change.
- Cons: tag pages order by last activity, not by status; special-casing one tag inside generic tag browsing; filters live in a page built for something else.
- Driver: adding per-tag presentation to tag browsing. Uncertainty: low.

### Option B: Dedicated `/work` page

**Estimate: 0.55**

A new `/work` page lists items grouped or filtered by status (open, active, stale, done, dropped), one row each with title, claimant, note and last activity, and a search box using the same matching as `/work.txt`. Thread pages get the same status buttons as in Option A. Navigation gets a Work entry (with icon mappings in the icon themes).

- Pros: built for the job; reads the same `WorkItems` data as `/work.txt`; room for later views (by claimant, by source).
- Cons: new page, navigation entry and theme icons to maintain; more verification across themes and phone widths.
- Driver: new page plus navigation and icon themes. Uncertainty: phone layout of a dense list.

### Option C: Status buttons only

**Estimate: 0.20**

Keep `/tags/work` as the list; add only the status buttons on thread pages.

- Pros: fixes the typing problem quickly.
- Cons: leaves the at-a-glance view unsolved, which is the main complaint.

## Recommendation

**Option B.** The list view is the main gap, and a page built for it avoids bending generic tag browsing around one tag. Status buttons post an ordinary signed `status:` reply, so the data model and `/work.txt` stay unchanged. Pages stay static on first paint: viewer-specific controls are decided in the client, per `AGENTS.md`.

**Vertical-slice viability:** a visitor opens `/work`, sees open and active items with claimants, filters to `stale`, and opens one. An approved member taps "Claim" or "Done" on the thread; the reply posts, and `/work` and `/work.txt` show the new status. A pending member sees why their tap had no effect. Verify on phone and desktop across themes.

**Open questions for Step 2:** whether done and dropped items are hidden by default; whether the page needs members-only gating like other content (it should follow the instance's existing rule).

## Enhancement Requests

- `Work-Ref:` trailer for commits related to an item without completing it (raised 2026-11-04). Disposition: separate small item, not this page.

## Study Snapshots

2026-11-04T02:13:36Z; scope v1; starting revision `78d0261b1a729339dbeb8e042e7f463bf7c3ca78`; reference R1 (hashes verified earlier on 2026-11-04 in this session, unchanged). Claude, single exposed rater; templates and the shipped work-tracking code were known before rating, so no pristine pre-inspection estimate is claimed.

- Intake after inspection: **0.55 projected total** for Option B (page, filters, status buttons, navigation and icons, theme/phone verification).
- Step 1 handoff: **0.55**. Alternatives A 0.30, C 0.20, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

Status: Step 1 drafted for review; awaiting Approved Step 1. Committed and pushed so the work item can link to it.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
