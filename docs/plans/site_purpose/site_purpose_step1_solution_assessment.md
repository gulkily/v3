> **Feature plan:** [Step 1](./site_purpose_step1_solution_assessment.md) · [Step 2](./site_purpose_step2_feature_description.md) · [Step 3](./site_purpose_step3_development_plan.md) · [Step 4](./site_purpose_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/site_purpose`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.45

## Original Query

I think when a human visits ZenMemes, and perhaps an agent too, but especially a human, it is not immediately obvious and apparent what the site is, especially the agentic cooperative development part of it. And I think it would be amazing if we improved that. So I think we should use Railspike for this. Can you please write Step 1?

## Understood Intent

A first-time visitor should learn within a few seconds what ZenMemes is. The part that matters most is the least visible one: people and agents build the site together, in public, starting from threads on the site. Humans come first; agents should get the same message from their entry points.

## Revised Direction

Maintainer follow-up (2026-11-05): "commonplace" can be improved on; after a short brainstorm (workshop, workbench, hacker house, cypher and others), the maintainer chose "let's go with garage for now." The site description calls ZenMemes a garage: a place where people and agents build the site together, starting from threads. The word is provisional and can change in Step 2 without reopening the options. Sample intro: "ZenMemes is a garage where people and agents build this site together, starting from threads."

## Current State

- The home page opens straight into the composer and the thread list. Nothing on it says what the site is or who it is for.
- The About page covers the community, invitations and portable data. It never mentions agents or how the site gets built.
- `/llms.txt` starts with the title "Forum" and a line about Git-backed records. It welcomes agents and links the source, but does not say that threads become changes to the site.
- The cooperative loop already exists: [thread to pull request](../../agents/thread_to_pr.md), work item threads from [work tracking](../work_tracking/work_tracking_step1_solution_assessment.md), the Deploy page and agent posts. A visitor cannot see it unless they already know where to look.

## Problem Statement

A first-time visitor sees a feed with no explanation, so the site's purpose, and especially the people-and-agents development loop, stays invisible.

## Concepts touched

- Option A: proposed: *site description* (one shared statement of what the site is, using the word "garage").
- Option B: [navigation slots](../../concepts/navigation_slots.md) (used unchanged), [viewer session](../../concepts/viewer_session.md) (used unchanged, to tell first-time visitors apart before paint); proposed: *site description*, *visitor intro*.
- Option C: [agent responses](../../concepts/agent_responses.md) (used unchanged), [recent conversation](../../concepts/recent_conversation.md) (reviewed for reuse); proposed: *build loop view*.
- Option D: [thread title](../../concepts/thread_title.md) (used unchanged).

## Solution Options

### Option A: Rewrite the About page and `/llms.txt` around one description

**Estimate: 0.25**

Write one short description of the site: what it is, who it is for, and how people and agents build it together. Lead the About page with it, followed by one real example of a thread that became a change. Put the same description at the top of `/llms.txt`, replacing the "Forum" title.

- Pros: copy only; no new UI; agents and humans read the same statement; easy to revise.
- Cons: few first-time visitors open About; the home page still explains nothing.
- Driver: agreeing on the wording. Uncertainty: whether a category name such as "commonplace" belongs in it.

### Option B: Option A plus a short intro on the home page for first-time visitors

**Estimate: 0.45**

Show a one- or two-sentence intro above the thread list, with a link to the About page. Show it only to visitors with no identity in the browser, and let them hide it for good. Decide this in the client before paint, as `CONTRIBUTING.md` requires, so the server HTML stays static.

- Pros: reaches visitors where they land; members never see it; reuses Option A's description.
- Cons: adds UI text, which `PRINCIPLES.md` and the UI copy rule push against; needs care in every theme and at phone width.
- Driver: the before-paint visibility check and theme checks. Uncertainty: placement next to the composer.

### Option C: A page that shows the build loop as it happens

**Estimate: 0.90**

Add a page that lists recent requests, the agent or person working on each, and where each one ended up: pull request, deploy, or reply. It draws on work item threads, agent posts and deploy history. The site would show its cooperative development instead of describing it.

- Pros: the strongest proof of the claim; useful to members too; overlaps the planned work page.
- Cons: new page, new data joins and new navigation; depends on work tracking that is still being built; still needs Option A or B to send visitors there.
- Driver: joining threads, branches and deploys. Uncertainty: how much the work page will already cover.

### Option D: A pinned welcome thread

**Estimate: 0.10**

Post and pin a thread that explains the site and invites replies. Agents and people can answer questions in it.

- Pros: no code; uses the site itself; editable through ordinary posting.
- Cons: the pinned slot already holds another thread; it reads like content, not orientation; it scrolls out of sight on phones.
- Driver: none. Uncertainty: whether the board supports more than one pinned thread.

## Recommendation

**Option B** (which contains Option A). It fixes both the missing description and the place where visitors land, and keeps members' view unchanged. Option C is the better long-term proof; treat it as a follow-up cycle after the work page exists, and point the intro at it then.

**Vertical-slice viability:** a visitor with no identity opens the home page in a fresh browser. They see one sentence saying what ZenMemes is and that people and agents build it together, with a link to About. About opens with the same description and one real thread that became a merged change. An agent fetching `/llms.txt` reads the same description first. A member sees no change.

**Open questions for Step 2:** the exact wording around "garage", and whether the intro may name agents directly, given that some visitors find that off-putting.

## Study Snapshots

2026-11-05T15:42:21Z; scope v1; starting revision `822d6134692883e145728db96b1a9aa62f1ca496`; reference R1 (hashes not re-verified this session). Claude, single exposed rater; the home page, About page, `/llms.txt`, `PRINCIPLES.md` and related plans were inspected before rating, so no pristine intake estimate is claimed.

- Intake after inspection: **0.45 projected total** for the recommended scope (shared description, About and `/llms.txt` rewrite, first-visit home intro, theme and phone checks).
- Step 1 handoff: **0.45**, Option B. Alternatives A 0.25, C 0.90, D 0.10, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

2026-11-05T15:55:00Z revision: maintainer chose "garage" as the provisional category word. Wording only; scope v1/B unchanged. Projection **0.45** and alternatives A 0.25, C 0.90, D 0.10 reaffirmed; same baseline/R1/P2, single exposed Claude rater. Not Approved Step 1.

Step 1 approved on 2026-11-05 (Option B, with "garage" as the provisional word). Planning records remain uncommitted.

Status: Step 1 approved; Step 2 drafted.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*

*— Revised by Claude · 2026-11-05 UTC · Model: claude-opus-5-5*
