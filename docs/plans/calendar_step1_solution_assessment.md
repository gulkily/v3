> **Feature plan:** [Step 1](./calendar_step1_solution_assessment.md) · [Step 2](./calendar_step2_feature_description.md) · [Step 3](./calendar_step3_development_plan.md) · [Step 4](./calendar_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/calendar`; [host ledger](./effort_estimation_trial.md). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.65

Work item: [thread-20261104140509-96d1a44620809fe0e869364bdb42ca53](https://zenmemes.com/threads/thread-20261104140509-96d1a44620809fe0e869364bdb42ca53)

## Original Query

I want to write a basic calendar feature for keeping track of my couch surfing appointments. Basically I will just mark days as available or claimed. Let's talk about this feature before writing Step 1.

1. People can claim a day in the UI (this is satisfying) and I can approve the claim.
2. Unmarked is available by default, up to 60 days ahead.
3. It should be under their username, and they should be able to write a comment. A claim is basically a regular comment with a special data field.
4. The calendar is approved users only; it will be accessible via multi-use invite link.
5. `/calendar` for now.
6. A month grid where clicking a day goes to a page where you can submit a form.

Just a little bit of clarification: I am the resource, not the couches. There is one me and multiple couches.

## Understood Intent

- The maintainer is the one travelling and being hosted; approved members are the people with couches. A member looks at a month grid of the next 60 days, picks a free day, and claims it: an offer to host the maintainer that night, with a comment. The maintainer approves one claim per day; the day then shows as claimed. The first message framed it as self-marking; the numbered answers replaced that with member claims plus approval.
- Revision 1 (2026-11-04, after Step 1 approval): the first draft of this section had the roles reversed (maintainer as host offering a couch to members). The clarification above corrects it. The data model, options, recommendation and estimate are unchanged: one resource, members claim days, the maintainer approves. Step 2 uses the corrected roles.
- Revision 2 (2026-11-04, during Step 2): the maintainer asked for a general calendar that they also use for couch stays. Option A already treats a claim as an event thread, so the recommendation stands; Step 2 v3 generalizes it to every event thread, with the couch flow as one use. The Step 1 estimate is historical; current scope and estimate are in Step 2.
- A claim is an ordinary signed post by the member, with the day as structured data, so it appears under their username and can be discussed like any post.
- Access follows the existing approval model: only approved members see or use the page, and new guests arrive through an existing multi-use invitation link ([invitation lifecycle](../concepts/invitation_lifecycle.md), already capped and shared).
- Not in scope: calendar export or sync, time-of-day slots, multi-night bookings as one object, notifications.

## Problem Statement

Approved members have no way to see which days the couch is free and ask for one, and the host has no way to record who is coming.

## Concepts touched

- All options: [viewer session](../concepts/viewer_session.md) (used unchanged for the approved-member check), [invitation lifecycle](../concepts/invitation_lifecycle.md) (used unchanged; the multi-use link already exists), [shared composer](../concepts/shared_composer.md) (used; Options A and B post the claim through it), proposed *calendar day state* (new: how a day's state is derived and shown).
- Option A: proposed *post event metadata* (existing `Event-Date` root header and event block, undocumented as a concept), [tag browsing](../concepts/tag_browsing.md) (used unchanged: claims also list under `/tags/calendar`), [work item status](../concepts/work_item_status.md) (pattern reused for the host's approval line).
- Option B: [work item status](../concepts/work_item_status.md) (pattern reused for both claim and approval lines).
- Option C: [reaction record](../concepts/reaction_record.md) (sibling pattern for a new signed record family), [read model rebuild](../concepts/read_model_rebuild.md) (changed: new tables).

## Solution Options

### Option A: Each claim is an event thread; the calendar reads them

**Estimate: 0.65**

A claim is a new thread tagged `calendar` whose root carries the existing `Event-Date` header, posted through the shared composer from a per-day page. The member's comment is the post body, under their username. The host approves or declines by replying in that thread with a line such as `claim: approved`, read the way `status:` lines are read for work items. `/calendar` renders a month grid for the next 60 days from the read model's existing `event_date` column plus the approval line: no claim is available, a pending claim is requested, an approved claim is claimed. Clicking a day opens `/calendar/<date>` with the day's claims and the claim form.

- Pros: no new record format or schema column; `Event-Date` parsing, storage, rendering, signing, drafts and Outbox already exist; every claim is a normal thread, so the host and guest can talk in it; rebuilds cannot disagree with the posts.
- Cons: depends on the event-support feature flag being on; the approval line needs a definition of who the host is; two claims for one day are possible and the host picks one; the month grid and per-day page are new templates to verify across themes and phone widths.
- Driver: two new pages plus derived day state and the host approval directive. Uncertainty: route-level approved-member gating if the instance is otherwise public (0.60 to 0.75).

### Option B: One calendar thread with directive replies

**Estimate: 0.45**

A single `#calendar` thread holds everything. A member claims by replying with a line such as `claim: 2026-11-10` and their comment; the host replies `approve: 2026-11-10 <username>`. `/calendar` parses the thread into the grid; the per-day page shows that day's lines and a reply form with the line prefilled.

- Pros: smallest change; mirrors work tracking exactly; one thread to read.
- Cons: replies cannot carry `Event-Date` (root-only header), so the date lives only in body text and depends on parsing; naming the approved member inside a directive is fragile; all stays share one thread, so conversation about one visit is mixed with every other; harder for agents and exports.
- Driver: directive parsing and the grid page. Uncertainty: low.

### Option C: Dedicated calendar records

**Estimate: 1.00**

A new signed record family under `records/calendar/` with claim and approval records, a spec, parser, read-model tables, API routes for prepare/finalize, and import/export and rebuild parity, following the reaction record pattern.

- Pros: explicit states and one claim per day enforced at write time; clean data for other clients.
- Cons: new spec, schema change, signing routes, replay, rebuild, archive and import work; conflicts with "schema changes where existing fields will do" in `PRINCIPLES.md`; most verification.
- Driver: a new canonical record family end to end. Uncertainty: moderate; likely a split discussion.

## Recommendation

**Option A.** The requested shape, "a regular comment with a special data field", already exists as a root post with `Event-Date`. Reusing it keeps the record plain text in Git with no new format, and each stay gets its own thread for the host and guest to talk in. The host's approval is a reply directive, the same mechanism work items already use, so pages stay static on first paint and rebuilds derive the same state. Option B's single thread saves little and loses the per-day conversation and the structured date. Option C is the right shape only if the calendar later needs guarantees the post model cannot give.

**Vertical-slice viability:** an approved member opens `/calendar`, sees the next 60 days with today's month first, taps a free day, writes "you can have my couch, I'm home from 7" and sends the claim. The day now shows requested with their username. The maintainer opens that thread, replies `claim: approved`, and the day shows claimed on the grid. A member who is not approved is sent to the Lobby as today. A failed send keeps the draft, as the composer already does.

**Open questions for Step 2:**

- Who counts as the host: the instance's configured identity, any operator, or the thread's first approver. Candidate: a site-content setting naming one identity, falling back to operators.
- Whether the event-support flag must simply be on for this instance, or the calendar turns it on for `calendar` threads only.
- Whether `/calendar` needs its own approved-member gate because the instance is public, or inherits the site-wide members-only flag.
- Whether a day with a declined claim returns to available (proposed: yes).
- Whether `/calendar` gets a navigation entry now (which needs icons in the Word97, Chicago and Midnight themes) or stays link-only for the first cycle (proposed: link-only).

## Enhancement Requests

None recorded.

## Study Snapshots

2026-11-04T14:10:00Z; scope v1; starting revision `f41f656936e740809242a5d8e1c646068afddc28`; reference R1, all three frozen source hashes verified this session. Claude, single exposed rater; the event headers, work-status directive, invitation and composer code were inspected before rating, so no pristine pre-inspection estimate is claimed.

- Intake after inspection: **0.65 projected total** for Option A (grid page, per-day page with prefilled composer, derived day state, host approval directive, approved-member access, theme and phone verification, docs).
- Step 1 handoff: **0.65**. Alternatives B 0.45, C 1.00, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

Status: Step 1 Option A approved 2026-11-04; Revision 1 corrects roles only, no scope or estimate change. Planning uncommitted.

*— Claude · 2026-11-04 UTC · Model: claude-fable-5-1*

*— Revised by Claude · 2026-11-04 UTC · Model: claude-fable-5-1 (Revision 1: roles corrected)*
