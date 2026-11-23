> **Feature plan:** [Step 1](./backlog_view_step1_solution_assessment.md) · [Step 2](./backlog_view_step2_feature_description.md) · [Step 3](./backlog_view_step3_development_plan.md) · [Step 4](./backlog_view_step4_implementation_summary.md)

Cycle: `v3/backlog_view`. Related history: [work page](../work_page_step1_solution_assessment.md) (parked, code removed 2026-11-04) and [feedback work restart](../feedback_work_restart/feedback_work_restart_step1_solution_assessment.md), which made feedback items the authority.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/backlog_view`; host ledger [backlog_view.md](../effort_ledger/backlog_view.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.60 (revised; earlier 0.70, original 0.50)

## Original Query

"On zenmemes, how do I view the entire backlog?"

Then, after the reply "The work_tracking cycle was meant to fix this scatter with one export of all items. Want us to build a single backlog view that merges those tags?": "Yes, I want to see a view of things to work on, sortable, and allowing me to start work right away on any one item or possibly multiple items, please."

[Maintainer in session, 2026-11-23.]

## Understood Intent

One page lists the work that can be picked up. The maintainer sorts it, picks one item or several, and starts work from that page. This Step 1 reads "start work" as handing the picked items to an agent. That reading is the first open question.

## Problem

The backlog exists, but nothing shows it as a list to sort and act on, so picking the next thing to work on means reading files or tag pages.

What exists to build on:

- `docs/feedback/items/` holds 357 feedback items, 345 of them open. Each has a title, kind, workflow, created date and sources. The site's own checkout carries these files.
- `./v3 feedback index` writes `docs/feedback/README.md`, a fixed-order table. It cannot be sorted and offers no way to start.
- [Thread to pull request](../../agents/thread_to_pr.md) already tells an agent how to take one item or thread to an open PR.
- `#issue` threads carry Claim, Resolve and Close marks (`resources/tagsets.txt`).

What the list holds today:

- 285 items are `untriaged`, 3 are `ready`, 58 are `blocked`. Most blocked items wait on the maintainer's own review, deploy or try-out.
- 22 items have an `Estimate`. A sort by estimate leaves the rest at the end.
- Site threads tagged `#todo` (29), `#issue` (7) and the feature tags reach the list only after a triage run turns them into items.
- `docs/plans/backlog/` holds parked plans, 30 of them Step 1 assessments, indexed by its `README.md`. They are not feedback items.

Concepts touched: [feedback items](../../concepts/feedback_items.md) (changed: the `Impact` header). Proposed: *backlog view*; *work claim* in Options D and F. [Tools launcher](../../concepts/tools_launcher.md) (changed). [Tag browsing](../../concepts/tag_browsing.md) is not used: the list reads items, not tags. Feedback: no item yet; one is allocated in Step 4.

## Revised Direction

Maintainer refinement (2026-11-23), quoted:

- "this needs a human interface and an agent interface"
- "start work for human means copyable prompt that gives the agent as much velocity and saved effort as possible (right now we paste a zenmemes.com link to the thread, which i feel like costs a lot of agent effort just to read)"
- "it also means 'claim' so that there's no effort duplication"
- "several items at once: basically, sometimes items/requests are related, and it would be nice to say, do a one-shot or do a cycle that covers all of these together."
- "anyone can see the page"; "lives under tools is fine for now"
- "ideally we want to have at least five sorts: time entered, time touched, estimated effort (if any), estimated impact (if any), some kind of combined sort that would allow us to see the most salient items based on recency combined with highest impact/lowest effort (we may want to see the newest or oldest items with this sort also"
- "triage should be a filter"; "parked plans separately"
- "there should be something command line and api for this, but for me i want the gui"

Second refinement (2026-11-23), quoted:

- "impact: same decimal scale as estimate"
- "the prompt should be short and assume the agent will have a copy of the repo and just reference all the files that the agent can read and instruct the agent to begin the process and go all the way to PR, or as close as it can get"

This settles the page (v1 Option B) as the base and adds claims, an agent interface, an impact value and the parked plans. The open choice is now how much goes into one cycle.

## What the revised scope contains

**Backlog page** (`/backlog`, under Tools, readable by anyone, behind a feature flag):

- Five sorts, each with a newest-first and oldest-first direction: entered, touched, effort, impact, and Salient. Addresses stay readable, such as `/backlog?sort=salient`.
- Salient ranks high impact and low effort first, then favours recent items. Items with no effort or impact value get a middle value, so they are not buried.
- Filters by workflow, including `untriaged`, and by kind. "Waiting on you" shows blocked items.
- Items gain an optional `Impact` header in [the item record](../../specs/feedback_item_record_v1.md), on the same decimal scale as `Estimate`.
- "Touched" is the date of the item's latest note, or its created date when it has none.

**Start**, for one item or several ticked together:

- The maintainer picks one-shot or staged cycle. Several items become one cycle that covers them all, with one branch and one PR.
- The prompt is short and assumes the agent has the repository. It names the files to read: each item's file, any related plan, and one new procedure, `docs/agents/backlog_to_pr.md`. It states the mode and tells the agent to begin and go to an open PR, or as close as it can get.
- The procedure makes the item file the brief. The agent reads the cited posts only when the item file says too little, and then adds what it learned to the item.
- Start also claims the items, so the page and other agents see who holds them.

**Agent interface**, same data and rules as the page:

- `/backlog.txt` lists items in `Key: value` form and takes the same sort and filter words.
- A start address returns the same prompt for given item IDs. A claim address takes and releases claims.
- `./v3 feedback list`, `start` and `claim` do the same from the command line.

**Parked plans**: the plans in `docs/plans/backlog/` appear in their own list, apart from the items, with a "continue this cycle" prompt.

## Solution Options (revised)

### Option D — Everything in one cycle

**Estimate: 1.15** (revised; earlier 1.25)
Main driver: the claim needs a home that the page, agents and the command line all read and write. Uncertainty: high; that design is new, and the page depends on it.

One cycle builds the page, the prompt, claims, the agent interface and the parked plans list.

- Pro: Start does everything from its first release.
- Con: larger than the pledge cycle that grew past its plan. The claim design would hold back the page.
- Con: one PR to review by merging, with many moving parts.

### Option E — Three cycles, the view first

**Estimate: 0.60** (revised; earlier 0.70. This cycle only; the next two are projected at 0.40 and 0.15 and are not part of this figure)
Main driver: the page with five sorts and filters, checked in each theme and on a phone. Uncertainty: medium; a compact row for 345 items on an iPhone SE.

This cycle, `backlog_view`: the page, the five sorts, the filters, the `Impact` header, Start with the short prompt for one or several items, the `backlog_to_pr.md` procedure, `/backlog.txt`, the start address and `./v3 feedback list` and `start`. Next, `backlog_claims`: Start claims, the page shows holders, agents claim and release. Then `backlog_parked`: the parked plans list.

- Pro: the page and the fast prompt are usable after the first merge.
- Pro: the claim gets its own Step 1. Our lean is a `claim:` line in a signed post, as pledges use `pledge:` lines, so claims need no new record type.
- Con: until the second cycle, Start does not claim. The prompt tells the agent to check open branches and PRs first, as it does today.

### Option F — Two cycles: view with a simple claim, then the rest

**Estimate: 0.85** (revised; earlier 0.95)
Main driver: Option E's first cycle plus a claim kept in a small server file, not signed. Uncertainty: medium; an unsigned claim store sits outside the content files.

As Option E, but the first cycle also records claims in a server-side file through the claim address. The second cycle adds the parked plans.

- Pro: Start claims from the first release.
- Con: a claim the server alone vouches for goes against "signatures are the authority". It would likely be rebuilt as a signed record later.

### Earlier options (v1, kept for the record)

- **Option A — Sorted lists from the command line. Estimate: 0.15.** Now part of every revised option.
- **Option B — Backlog page on the site. Estimate: 0.50.** The base of the revised options; its scope grew with five sorts, the impact value, the full prompt and the agent interface.
- **Option C — Backlog page with claims. Estimate: 0.80.** Replaced by Options D to F.

## Recommendation

Option E. The page, the sorts and the short prompt are where the daily saving is, and they need no new design. Claims are the hard part, and they are better designed against a page that exists. The three cycles can run back to back.

Vertical slice (this cycle): on the phone, open Tools → Backlog. Tap Salient. Filter to `untriaged`. Tick two related bugs, tap Start and choose one-shot. The next page shows a prompt of a few lines naming both item files and the procedure. Tap Copy and paste it into Claude. The agent reads the two files and starts, without fetching a thread. An agent gets the same list from `/backlog.txt?sort=salient`.

## Open questions for the maintainer

- **Thin item files.** Many untriaged items hold one or two sentences. Default: the agent reads the cited posts when the item says too little. Alternative: a triage pass first copies the needed detail into each item.
- **An agent with no copy of the repository.** Default: the prompt gives the repository address to clone. Chat agents without a shell are out of scope for Start.
- **Editing effort and impact from the page.** Default: not in this cycle; values change in the item files. Alternative: a later cycle adds it.
- **How fresh the list is.** Default: the items as of the last deploy. The header Deploy prompt keeps that short.

## Enhancement requests

- **Request:** Start claims the items. **Disposition:** next cycle `backlog_claims` under Option E; in this cycle under D or F. **Approval status:** not approved.
- **Request:** parked plans listed separately. **Disposition:** cycle `backlog_parked` under Option E. **Approval status:** not approved.
- **Request (agent's suggestion):** fill in `Estimate` and `Impact` for more items so the sorts are useful; 22 items have an estimate today. **Disposition:** a triage task, outside this cycle. **Approval status:** not approved.

## Study snapshots and approval


- 2026-11-23 UTC, intake: 0.50 projected total for the recommended scope. No pristine intake; the rater inspected the feedback items, the parked work page plan and the tagsets before rating. Claude, single exposed rater; R1 hashes not re-verified this session; baseline `origin/main` `8a5aa40`.
- Step 1 handoff: 0.50 for Option B (alternatives A 0.15, C 0.80; not summed). No implementation.

- 2026-11-23 UTC revision: the maintainer added an agent interface, a full prompt, claims, combined items, five sorts with an impact value, and parked plans. Scope v2. Projection **0.70** for Option E's first cycle (D 1.25, F 0.95; not summed); v1 values 0.50, 0.15 and 0.80 preserved. Same baseline, R1 and P2; single exposed Claude rater. A refinement, not Approved Step 1.

- 2026-11-23 UTC second revision: `Impact` uses the `Estimate` scale; the prompt is short and points at repository files, so cited posts are no longer read into it. Scope v3. Projection **0.60** for Option E's first cycle (D 1.15, F 0.85; not summed); 0.70 and 0.50 preserved. Same rater and references. Not Approved Step 1.

- 2026-11-23 UTC: **Approved Step 1** by the maintainer ("approved step 1"). No option named; Option E taken as recommended, with the defaults for the open questions. Recorded in [Step 2](./backlog_view_step2_feature_description.md#approved-direction).

Status: Step 1 approved.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*

*— Revised by Claude · 2026-11-23 UTC · Model: claude-fable-5-2*
