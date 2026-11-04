> **Feature plan:** [Step 1](./calendar_step1_solution_assessment.md) · [Step 2](./calendar_step2_feature_description.md) · [Step 3](./calendar_step3_development_plan.md) · [Step 4](./calendar_step4_implementation_summary.md) · Addendum (this file)

# Calendar: conversation notes for the implementing agent

Written 2026-11-04 at the maintainer's request, from the planning conversation. The approved documents win where they are more precise; this file records how the decisions were reached and what was said that the documents do not repeat.

## Who is who

- The maintainer travels and is hosted by other people. They are the instance's root-approved operator. "I am the resource, not the couches. There is one me and multiple couches."
- Members with a couch are hosts. A couch offer is an event they post on the nights they can host. The maintainer confirms one offer per night; that is a convention, not something the code enforces.
- The instance is members-only. New hosts arrive through an API-issued capped invitation link that already exists. Nothing in this cycle changes access.

## How the design got here, in order

1. First idea: the maintainer marks days available or claimed by hand.
2. Changed to: members claim a day in the UI and the maintainer approves. Unmarked days are available by default, 60 days ahead. A claim is "a regular comment with a special data field", under the member's username, with a comment.
3. The special data field already exists: thread roots carry `Event-Date`, `Event-Time`, `Event-Location` and `Event-Link`, stored in the read model and rendered by the event block, behind the event-support flag. Replies cannot carry them. Option A in Step 1 was chosen on that basis.
4. Roles were corrected (see above). `Event-Location` became the place of the couch.
5. Two questions from the maintainer: how to record something arranged offline, and how to mark a range in one post. Answers adopted: the operator's own post with the directive line counts immediately; an additive inclusive `Event-End-Date` header covers a span, approved or declined whole, never partially.
6. The maintainer then asked for a general calendar they would also use for couch stays. The design became a view over all event threads with month navigation; the directive was renamed from `claim:` to `event: confirmed` / `event: declined`; declined events leave the grid but stay in their thread; the route-level gate was dropped because the instance's own rule already applies; the 60-day horizon became the default view rather than a limit.
7. Last addition: per-tag calendars at `/calendar/<tag>`, with day pages that preset the tag on the composer, so `/calendar/couches` is the link hosts get and posting from it tags the event `couches`. Combining several tags in one view is out of scope.

## Decisions that are easy to get wrong

- Only an active root-approved operator's `event:` line counts, in a reply or in the root. The author of the event never confirms their own event. This differs from the title directive, which lets the root author retitle.
- State is derived at read time from the posts, latest valid line wins, quoted lines never count. Nothing is stored that a rebuild could disagree with. This also differs from the title directive, which records an authored subject record at write time.
- A span is one thread. The grid lists it on every day from `Event-Date` to `Event-End-Date` inclusive. An absent end date means one day.
- A day with any unconfirmed event is "open" (for couches: offered). With a confirmed event it is "confirmed". With none it is empty. Declined events are hidden on the grid only.
- The grid renders identical markup for every viewer. Pages must look final on first paint. No viewer-specific server HTML.
- The calendar needs the event-support flag on. With it off, operators see the flag link and nothing else.
- No navigation entry and no theme icons this cycle. The calendar is reached by its link.
- The post record spec does not yet document any of the event headers. Stage 1 adds that section for all five.

## What was deliberately left out

Notifications, time-of-day ordering within a day, recurring events, calendar export or sync, partial confirmation of a span, write-time exclusivity for a night, tag combinations, the 60-day limit as an enforced rule, navigation entry and icons.

## Environment and process facts

- Mockups: `docs/plans/calendar_mockups.html`, uncommitted on purpose; open it in a browser. The maintainer could not open the claude.ai artifact link, so deliverables for review are written as local files in this project.
- Plans for Steps 1–3 were committed and pushed to `main` before Step 3 approval at the maintainer's request. That does not approve Step 3 or implementation; wait for `Approved Step 3`, then branch and make the planning commit per Railspike.
- While the plans were being written, `main` received commits that removed work tracking (`WorkItems`, `status:` directive, `./v3 work`) and introduced feedback items in `docs/feedback/items/` with `Feedback: PF-...` commit trailers. No item matches the calendar. PF-20261104-B10 (composer event disclosure cannot be collapsed) is adjacent and is replayed in Stage 2.
- A `#work` thread for this feature exists on zenmemes.com (`thread-20261104140509-96d1a44620809fe0e869364bdb42ca53`) from before that removal; it tracks nothing now.
- The effort ledger `docs/plans/effort_estimation_trial.md` conflicts on nearly every merge because every cycle prepends to the same file. Keep this cycle's ledger writes minimal and expect to resolve by keeping both sides.
- Precedent to copy for the new header: `docs/plans/event_time/event_time_step4_implementation_summary.md` lists every file the `Event-Time` header touched.
- Tests: run the smoke set plus the tests you add (`./v3 test --smoke FooTest`); do not run the full suite; say so in the summary.

## How the maintainer likes to work

- Talk through the feature before writing Step 1. Keep questions to the ones that change the design, give a recommendation, and let them decide.
- Mockups help; keep them local and simple, and update them when the plan changes.
- Short, direct answers. When they ask "what do you think", give an opinion and a reason.

*— Claude · 2026-11-04 UTC · Model: claude-fable-5-1*
