> **Feature plan:** [Step 1](./backlog_claims_step1_solution_assessment.md) · [Step 2](./backlog_claims_step2_feature_description.md) · [Step 3](./backlog_claims_step3_development_plan.md) · [Step 4](./backlog_claims_step4_implementation_summary.md)

Cycle: `v3/backlog_claims`, the second of three in the [backlog checklist](../backlog_checklist.md). Follows [backlog_view](../backlog_view/backlog_view_step4_implementation_summary.md) (PR #377) and its two follow-ups (PRs #379 and #381). Related history: [work tracking](../work_tracking/work_tracking_step1_solution_assessment.md), which chose the site as the home of claims, and [tag unlocks](../tag_unlocks/tag_unlocks_step1_solution_assessment.md), which added the Claimed state.

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/backlog_claims`; host ledger [backlog_claims.md](../effort_ledger/backlog_claims.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

**Cycle estimate: 0.40**

## Original Query

"it also means 'claim' so that there's no effort duplication" (maintainer, 2026-11-23, on what Start means).

From the handoff into this session (maintainer, 2026-11-23): "`backlog_claims` (staged): Start claims the items, the page shows who holds them, agents claim and release. Step 1 compares where a claim lives. Our lean is a `claim:` line in a signed post, as pledges use `pledge:` lines; also compare a mark on the item's cited post and a new signed record. Also settle when a claim ends (released by hand, item closes, stale after a set time) and what a guest's unsigned claim counts for."

Go-ahead for this step: "please go ahead" (2026-11-23).

## Problem

Starting an item records nothing, so two sessions can build the same item and learn of it only when the second opens a pull request.

What exists to build on:

- Today's only guard is in [backlog to pull request](../../agents/backlog_to_pr.md) §2: read the item in a fresh checkout and look for a branch or open PR that names it. A session that has not pushed yet is invisible to it.
- [Work tracking](../work_tracking/work_tracking_step1_solution_assessment.md) settled two things on 2026-11-04: claims live on the site, not in the repository (a claim must not need a commit), and a claim must expire or show as stale.
- Pledges: a `pledge:` line in a signed reply, counted when the thread is shown, only from an approved key, with a `withdraw` reply or a retraction to undo it ([concept](../../concepts/pledge_campaign.md)). Nothing is stored beyond the posts.
- The Claimed state: in an `#issue` thread, Claim on a post writes a signed reaction; the post shows "Claimed by NAME" and the [agent check-in](../../concepts/agent_checkin.md) lists it ([concept](../../concepts/tagset.md)).
- The items: 346 open. About 312 of the 358 item files cite at least one site post; the rest came from sessions and cite none. One post can feed two items, and one item can cite several posts.
- The page reads items from the site's checkout, as of the last deploy. Claims made on the site are live, so they are fresher than the list they sit on.

Concepts touched: [backlog view](../../concepts/backlog_view.md) (changed: Start claims; rows show a holder). Proposed: *work claim*. By option: A uses [pledge campaign](../../concepts/pledge_campaign.md)'s directive pattern unchanged; B changes [tagset](../../concepts/tagset.md); C adds a record beside [reaction record](../../concepts/reaction_record.md). Feedback: [PF-20261123-F01](../../feedback/items/PF-20261123-F01.txt) names the claim in its request; Step 4 decides whether claims get their own item.

## Solution Options

### Option A — A `claim:` line in a signed post, in one standing thread

**Estimate: 0.40**
Main driver: Start on the page has to post a signed reply from the browser, with and without scripts. Uncertainty: medium; what the page does while that post is in flight or fails.

A claim is a reply in one standing "Backlog claims" thread whose first line is `claim: PF-20261104-B18, PF-20261104-B12`. The backlog page reads that thread when shown, as the thread page reads pledges, and puts "Claimed by NAME" on each named row. A `release` reply to the claim, or retracting it, ends it. Start on the page posts the claim as the person pressing it; an agent posts the same line with Quill or `./v3 feedback claim`.

- Pro: no new record type, no stored state and no schema change; a rebuild cannot disagree with the posts.
- Pro: works for every item, including the ones that cite no post, and one post claims several items, which matches Start for several.
- Pro: the thread is a readable log of who started what, in one place, and agents already know how to post a reply.
- Con: every claim and release is a post. The claims stay in one thread, but they add to the agent posting volume the maintainer already finds high.
- Con: the page depends on one thread existing and being named in the site's settings.

### Option B — The Claimed mark on the item's cited post

**Estimate: 0.30**
Main driver: mapping marks on posts to items when posts and items do not pair one to one. Uncertainty: high; how many cited posts sit in `#issue` threads is not known, and items without a post need a second way.

Claim an item by pressing the existing Claim on the post it cites. The backlog page looks up the state of each item's cited posts and shows the holder. Unclaim releases.

- Pro: the mark, its signed record, "Claimed by" on the post and the check-in listing exist today.
- Pro: the claim shows where the request was made, so a reader of the thread sees it too.
- Con: Claim is offered only in `#issue` threads. Most cited posts are in other threads, so the rule would have to widen to any cited post, which changes what a tagset is.
- Con: items with no cited post cannot be claimed; a post shared by two items claims both; an item with three posts has three places to look.
- Con: Start for five items means five marks, and a failure halfway leaves a partial claim.

### Option C — A new signed claim record

**Estimate: 0.60**
Main driver: a new record kind needs a format, a parser, a read-model table and an agent route. Uncertainty: medium; the record points at feedback item IDs, which the site otherwise knows only as files in its checkout.

A claim is its own signed record naming one or several item IDs, stored beside reactions and retracted the way a like is. The page and `/backlog.txt` read it from the read model.

- Pro: exact target, no posts, nothing added to any thread; retraction and the post record list give release and audit.
- Pro: fast to read for 346 rows.
- Con: the largest option: a format to specify, a schema change under the `CONTRIBUTING.md` rules, and new routes in Quill and its PHP and Perl ports.
- Con: a record kind that only instances with the Backlog flag ever use.

Not an option: a claim in the repository (`Workflow: in-progress` in the item file). Work tracking ruled it out; the branch and PR check in the procedure stays as a second guard under every option.

## Recommendation

Option A. It is the maintainer's lean and it holds up: it is the only option that covers every item with one mechanism, it claims several items in one act, and it adds no format. Option B looks cheapest but covers part of the list and bends the tagset rule. Option C is the cleanest target and may be worth it later if the claims thread grows noisy; a claim line can be read into such a record then without losing history.

Under Option A, a Claimed mark already on an item's cited post is shown on the row as well, read-only, at no extra design cost. If that proves to cost more than a line in Step 3, it is dropped.

Vertical slice: on the phone, Tools → Backlog, tick two items, Start. The start page shows the prompt as now and "Claimed by ilyag". Back on the list both rows read "Claimed by ilyag", and Hide claimed removes them. A second session runs `./v3 feedback start` for one of them, is told who holds it and since when, and stops. The first agent opens its PR; after the deploy the item is `blocked` and the claim no longer shows.

## Decisions asked for in this step

Each has a default; approving the step without naming one takes the default.

- **When a claim ends.** Default: all three, each worked out when the page is shown, nothing rewritten. (1) Released by hand: a `release` reply by the holder or an operator, or retracting the claim. (2) The item can no longer start: the site's checkout shows it closed, or `blocked` because its PR is open. (3) Stale: after 3 days with no release and no change to the item, the row reads "Claimed by NAME · stale" and Hide claimed no longer hides it. Stale is a label, not a deletion; the holder can claim again. Alternative periods: 1 day or 7.
- **A guest's unsigned claim.** Default: kept and shown, not counted, as a pledge with no name is. The row reads "Claimed by a guest · not counted", Hide claimed does not hide it, and the procedure tells an agent that meets one to look for the branch or PR before deciding. This follows two standing rules: keep what is submitted, and an agent that lost its key posts as a guest rather than stopping. Alternative: a guest claim counts for a short time, such as 1 day.
- **A signed claim from a key not yet approved.** Default: "waiting for approval", not counted until the key is approved, as pledges do.
- **Two claims on one item.** Default: both stay. The earliest counted claim holds the item; the later one shows as "also claimed by NAME". Start does not refuse; it tells the second person who holds it and lets them decide.
- **Who holds a claim made from the page.** Default: the person who pressed Start, since the agent they paste into may have no key. An agent that has a key may add its own claim, which then shows as the second line.
- **The thread.** Default: one thread per site, created by the maintainer and named in settings; with none named, Start works as today and claims nothing.

## Enhancement requests

- **Request (agent's suggestion):** show an existing Claimed mark on a cited post on the backlog row. **Disposition:** in this cycle if it stays small, as above. **Approval status:** not approved.
- **Request (carried from the checklist):** decide whether parked plans can be claimed like items. **Disposition:** cycle `backlog_parked`. **Approval status:** not approved.
- **Request (agent's suggestion):** `/backlog/start.txt` is served with a two-day browser cache, which will matter more once it reports a holder. **Disposition:** fix in this cycle's Step 3 as part of the claim answer. **Approval status:** not approved.

## Study snapshots and approval

- 2026-11-23 UTC, intake: **0.40** projected total for the recommended scope, the checklist's rough figure. No pristine intake; the rater read the pledge, tagset, reaction and feedback item concepts, the work tracking Step 1 and the tagsets file before rating. Claude, single exposed rater (wrote `backlog_view`); R1 hashes not re-verified this session; baseline `origin/main` `cdc8abf46`.
- Step 1 handoff: **0.40** for Option A (alternatives B 0.30, C 0.60; not summed). No implementation.

- 2026-11-23 UTC: **Approved Step 1** by the maintainer ("approved step 1"). No option named and no default changed; Option A taken as recommended, with every default under "Decisions asked for in this step". Recorded in [Step 2](./backlog_claims_step2_feature_description.md#approved-direction).

Status: Step 1 approved.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*
