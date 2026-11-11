> **Feature plan:** [Step 1](./work_tracking_step1_solution_assessment.md) · [Step 2](./work_tracking_step2_feature_description.md) · [Step 3](./work_tracking_step3_development_plan.md) · [Step 4](./work_tracking_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/work_tracking`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.95 (revised; original 0.85)

## Original Query

On zenmemes/v3, we are starting to lose track of all the work we have backlogged and also that we have completed. For example, earlier today, I noticed that I fixed the same issue twice in slightly different ways and then had to merge the fixes. The work is also spread across conversations, todo.txt, and threads and comments on the site. How do we regain control of this?

There's also some Step 1 documents we've committed in hopes of working on them later.

There are also some documents collating lists of issues that we collected on the site.

Okay, go ahead and write it up, please.

## Understood Intent

Direction settled with the maintainer in a follow-up voice discussion (2026-11-03):

- Only **claims and status** (open, active, done, dropped) need one home. Ideas may arrive anywhere and stay loose, as long as nothing is lost and nobody starts work someone else has claimed.
- `todo.txt` stays the maintainer's quick scratchpad and acts as an inbox, as do site threads, chats, the [meta feedback summaries](../zenmemes_meta_feedback_2026-10-30_summary.md), [scraped site requests](../zenmemes_scraped_feature_requests_2026-10-15.txt) (formerly `todo-scraped.txt`) and parked Step 1 assessments. Sweeps pull inbox items into the claim/status home.
- Status goes back to each item's site thread both automatically (when work lands) and in batches (during site sweeps).
- Claiming work must not require committing to the v3 repository; parallel sessions cannot see each other's unpulled commits, and the friction is too high.
- This step decides where claims and status live. The one-time reconciliation of existing sources (about 364 cited site links, 48 Step 1 assessments with no later step, both todo files) is a separate follow-up cycle that uses the result.

## Revised Direction

Maintainer follow-up (2026-11-03): “What if the site is the source of truth and where all the updates are held, but it also exports regularly a dump of all the stuff to a file in the repo and keeps this file updated? Also, if we could do a quick look into how other sites are doing it right now. In particular, I was thinking about Lobsters and Tildes.”

## Prior Art

- **Lobsters:** issues live on GitHub; ideas are discussed first on the site's meta tag, in chat or in an issue. Its contributing guide asks people *not* to comment to claim issues, because claimed issues that go unfinished can stall for years, and asks anyone abandoning work to say so.
- **Tildes:** a public GitLab tracker was declared the definitive list of planned work and who is on it; a site thread was used to find items missing from the tracker, and the maintainer copied them over by hand (2018; current practice not verified).
- Neither dogfoods its own site as the tracker; both keep the site as the discussion and intake channel. The Lobsters warning applies here: claims need to expire or be visibly stale.

## Problem Statement

Nothing shows who is working on what, so the same issue gets fixed twice and finished work stays listed as open across several unsynchronized sources.

## Concepts touched

- Option A: [tag browsing](../../concepts/tag_browsing.md) (used, possibly changed for status filters), [thread title](../../concepts/thread_title.md) (used unchanged), [agent responses](../../concepts/agent_responses.md) (used unchanged); proposed: *work item status*.
- Option B: proposed: *work claim record*; [internal task queue](../../concepts/internal_task_queue.md) (reviewed for reuse, likely unchanged).
- Option C: none in v3; proposed: *work item file* (tracker branch or repository).

## Solution Options

### Option A: ZenMemes threads as the claim and status home, exported to the repo

**Estimate: 0.95** (revised; original 0.85)

Each work item is a site thread whose root links its sources. Status is a tag or label (`open`, `active`, `done`, `dropped`), and claiming is a short reply naming the session or branch plus the `active` status. Before a fix, an agent searches item threads; when the site is unreachable it records the claim locally and posts it on reconnect.

Revision 1: the site is the source of truth, and a scheduled export writes every item's status, claim and source links to one plain-text file in the repository, reusing existing snapshot-export pieces where they fit. The file gives agents a greppable copy and git history of status changes; the claim check before starting work still reads the live site, because the file may lag. Claims with no activity for a set period show as stale.

- Pros: dogfoods the site; claims sit beside discussion; tag browsing already lists threads by tag; doubles as the v3 work log the maintainer wants on the site; feedback already starts there.
- Cons: claiming depends on site availability and signed posting speed; latest-status-wins semantics and a quick agent-facing lookup may need small additions; items are public unless a private mode is used.
- Revision pros: offline and greppable status, history in git, a fallback reading path when the site is down.
- Revision cons: export commits add noise to `main` unless kept on a dedicated branch or batched; a stale file can mislead an agent that skips the live check.
- Driver: status semantics, the agent lookup and the export job. Uncertainty: how much tag/label behavior already fits; where export commits land.

### Option B: Small claims endpoint on the v3 server

**Estimate: 0.70**

A separate claim/status record kept outside forum content, read and written through a `./v3 work` command or a minimal HTTP endpoint. Item threads remain optional links for discussion.

- Pros: fast single lookup; simple status model; no forum-content conventions to enforce.
- Cons: new storage, API surface and authentication to maintain; same availability dependence as Option A without its dogfooding or discussion benefit; a second system beside the site.
- Driver: authentication and storage design.

### Option C: Separate tracker branch or repository

**Estimate: 0.45**

One small file per item on an orphan branch or in its own repository, claimed by pushing a one-line change.

- Pros: no server work; offline-friendly; plain, inspectable files.
- Cons: still a commit and push to claim, the friction the maintainer rejected; sessions must fetch to see claims; push races between parallel sessions; discussion lives elsewhere.
- Driver: push-race handling.

## Recommendation

**Option A with the export (Revision 1)**, with a local-claim fallback when the site is unreachable. It keeps claims next to the discussion and turns existing site features into the tracker instead of adding a parallel system.

Shared across options and in scope for Step 2: the agent rule to search and claim before starting (an `AGENTS.md` change), inbox sweeps, post-back on landing plus during batch sweeps, claim expiry, and the export's cadence and destination branch. Step 2 must settle whether sweeps may annotate `todo.txt` lines with item links (currently disallowed by `AGENTS.md`) or must keep a separate record of lines already imported.

**Vertical-slice viability:** an agent about to fix an issue searches item threads, finds an open item, claims it, and a second session searching the same issue sees the claim and stops. When the fix lands, the item thread shows `done` with the commit, and the next export shows the same. Pilot with about ten items drawn from today's sources, including the issue fixed twice today. Full reconciliation follows as its own cycle.

## Enhancement Requests

- The claim-before-start check could become a general Railspike feature for any host project. Disposition: noted for a separate Railspike cycle; not in this scope.
- The maintainer plans to amend Railspike to commit each step as it is approved. Disposition: separate Railspike work; this cycle does not depend on it.

## Study Snapshots

2026-11-03T23:03:34Z; scope v1; starting revision `561f7dd1113c3021ba0d7d1b28361865c40de2e2`; reference R1 (all three frozen source hashes verified). Claude, single exposed rater; repository layout, `todo.txt`, feedback summaries and plan inventory were inspected before enrollment, so no pristine pre-inspection estimate is claimed.

- Intake after inspection: **0.85 projected total** for the recommended scope (claim/status home, agent rule, inbox sweep, post-back, ten-item pilot). Reconciliation of existing sources is excluded.
- Step 1 handoff: **0.85**, Option A. Alternatives B 0.70 and C 0.45, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

2026-11-04T00:41:32Z revision: maintainer asked for a site-to-repo export and a look at Lobsters and Tildes. Scope v2/A adds the scheduled export file and claim staleness. Projection **0.95**; v1 0.85 preserved; B 0.70 and C 0.45 reaffirmed (export would not change them materially). Same baseline/R1/P2, single exposed Claude rater; prior hash verification carried forward. Direction refinement only; not Approved Step 1.

Step 1 approved on 2026-11-03 (Option A with Revision 1). Planning records remain uncommitted.

Status: Step 1 approved; Step 2 drafted.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

*— Revised by Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
