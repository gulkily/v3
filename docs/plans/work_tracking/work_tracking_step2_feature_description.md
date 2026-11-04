> **Feature plan:** [Step 1](./work_tracking_step1_solution_assessment.md) · [Step 2](./work_tracking_step2_feature_description.md) · [Step 3](./work_tracking_step3_development_plan.md) · [Step 4](./work_tracking_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/work_tracking`; [host ledger](../effort_estimation_trial.md#cycle-v3work_tracking). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 1 Option A with Revision 1 approved; Step 2 proposed; implementation unapproved.

**Cycle estimate: 0.95**

## Problem

Nothing shows who is working on what, so the same issue gets fixed twice, and finished work stays listed as open across `todo.txt`, site threads, feedback summaries and parked plans.

## User Stories

- As an agent about to start a fix, I want to search existing work items and claim one so that I never redo work another session has started.
- As the maintainer, I want to keep jotting ideas in `todo.txt` and on the site so that capturing work stays effortless.
- As the maintainer, I want each item's status visible on its site thread and in a repository file so that I can see what is open, active and done from the site, GitHub or grep.
- As a site member who reported an issue, I want its thread to show when it is fixed so that I know the report was handled.

## Core Requirements

- **One status per item.** A work item is a site thread with exactly one current status: `open`, `active`, `done` or `dropped`. Changing status replaces the visible status; earlier statuses remain in the thread's history.
- **Claim before starting.** Claiming sets `active` with a reply naming the session or branch. An `active` item with no activity for a set period shows as stale and can be reclaimed. `AGENTS.md` requires searching and claiming before any non-trivial fix. When the site is unreachable, the agent records the claim locally and posts it on reconnect.
- **Fast lookup.** One unsigned request returns matching items with status, claim and thread link as plain text, searchable by words and status.
- **Repository export.** A scheduled job writes a generated Markdown file into the v3 repository: a "generated; do not edit" header, then one line per item in stable order. The file is never authoritative for claims.
- **Intake and post-back.** A sweep turns new inbox entries (`todo.txt` lines, request threads) into items without duplicating ones already imported, keeping its own record of imported `todo.txt` lines rather than editing that file. Status updates post to the item thread when a commit referencing the item lands, and a batch sweep catches anything missed.

## Delivery Scope and Completion Boundary

Work type: application change, plus `AGENTS.md`, touched reference docs, this cycle's records and study-ledger bookkeeping. No new dependencies. Out of scope: reconciling all existing sources (follow-up cycle), private work items, Railspike upstream changes, and broader site redesign.

Completion boundary: on zenmemes.com, a session searches, claims an item and starts work; a second session searching the same issue sees the claim and stops. When the fix lands, the thread shows `done` with the commit, and the next export shows the same. Release condition: the ten-item pilot (including the issue fixed twice on 2026-11-03) runs end to end on production.

Known environment: agents work from cloud workspaces and local checkouts and reach zenmemes.com over HTTPS; the maintainer deploys by SSH, often from a phone. If the site is unreachable, the agent's claim attempt fails visibly, the claim is recorded locally, and a retry posts it; success is the claim appearing on the item thread and in the lookup. Record production verification as done or explicitly not done.

## Shared Component Inventory

- **Thread labels and tags** (`ThreadLabelRecord`, `LabelRemoval`, `/api/apply_thread_tag`): reuse for status; extend only if replacing one status label cannot be expressed today.
- **Tag browsing** (`/tags/<tag>`) and **search** (`/search`, `/api/get_thread`, `/api/list_index`): reuse for human browsing; the plain-text lookup extends an existing read path rather than adding a parallel index.
- **Agent posting** (one-request publish via `frm.py`, prepared flow, zenmemes-posting skill): reuse for claims and post-back.
- **Internal task queue** and **content snapshot export**: reuse the scheduling and snapshot patterns for the export where they fit.
- **Railspike workflow threads** (`#railspike`, `WorkflowService`): related but separate. Items may link to a Railspike thread; this cycle does not merge the two.

## Risks

- **Claim latency or site outage blocks work.** Validate one-request claim timing first; the local-claim fallback mitigates outages.
- **The export cannot reach the code repository.** Site records live in a separate content repository on the server, which may lack push access. Settle whether the server pushes or a scheduled job fetches the published file and commits it, before dependent stages.
- **Status labels are additive.** Several status labels could show at once. Inspect label removal and ownership before Step 3.
- **Agents skip the check.** Mitigate with the `AGENTS.md` rule and item references in commit messages; count misses during the pilot.

## Simple User Flow

1. An idea lands in `todo.txt` or a site thread.
2. A sweep creates an `open` item thread linking the source.
3. An agent searches before a fix, finds the item and claims it.
4. Another session searches, sees `active` and moves on.
5. The fix lands; the thread shows `done` with the commit, and the export updates.

## Success Criteria

- In the pilot, a second session finds an existing claim and does not start duplicate work.
- All ten pilot items appear in the lookup and the export with correct status.
- A landed fix shows `done` on its thread by the next batch sweep at the latest.
- Re-running the sweep creates no duplicate items.
- A claim made while the site is unreachable appears after reconnect.

## Concepts Touched

[Tag browsing](../../concepts/tag_browsing.md) used, possibly changed for status filtering; [internal task queue](../../concepts/internal_task_queue.md) and [content snapshot export](../../concepts/content_snapshot_export.md) reviewed for reuse; proposed: *work item status* (new inventory entry at implementation).

## Study Checkpoint

2026-11-04T00:46:46Z; scope v2/A, original baseline `561f7dd1113c3021ba0d7d1b28361865c40de2e2`, R1/P2; frozen hashes verified earlier this session, unchanged. User explicitly approved Step 1. Whole-cycle **0.95 reaffirmed** after inspecting labels, search, agent API, task queue and Railspike thread integration. The separate content repository raises export uncertainty but does not change scope. Claude, single exposed rater; pristine intake missing. No implementation, measured labor or accepted outcome. Await Approved Step 2. Planning uncommitted.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
