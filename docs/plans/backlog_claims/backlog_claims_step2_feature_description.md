> **Feature plan:** [Step 1](./backlog_claims_step1_solution_assessment.md) · [Step 2](./backlog_claims_step2_feature_description.md) · [Step 3](./backlog_claims_step3_development_plan.md) · [Step 4](./backlog_claims_step4_implementation_summary.md)

Cycle: `v3/backlog_claims`, the second of three in the [backlog checklist](../backlog_checklist.md). Branch: a new one from `origin/main` after Step 3 is approved.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/backlog_claims`; host ledger [backlog_claims.md](../effort_ledger/backlog_claims.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.40**

## Problem

Starting a backlog item records nothing. Two sessions can build the same item and find out only when the second opens a pull request, because the one guard today, a search for a branch or PR, cannot see work that has not been pushed.

## Approved direction

Step 1 was approved on 2026-11-23 ("approved step 1"). No option was named and no default was changed, so this follows Option A with every Step 1 default: a claim is a `claim:` line in a signed reply in one standing thread; it ends by release, by the item no longer being startable, or by going stale after 3 days; a guest's claim is shown and not counted; a claim from an unapproved key waits; two claims both stay and the earliest counted one holds; the person who presses Start holds the claim; the site names one claims thread. Choices made in this step that Step 1 did not put to the maintainer are marked **(default)**.

Concepts touched: [backlog view](../../concepts/backlog_view.md) (changed: Start claims; rows show a holder; a Hide claimed filter). Proposed: *work claim*. [Pledge campaign](../../concepts/pledge_campaign.md) (used unchanged: the pattern of a line in a signed reply, read when shown). [Guestbook](../../concepts/guestbook.md) (used unchanged: the pattern of one thread named in the site profile). Feedback: [PF-20261123-F01](../../feedback/items/PF-20261123-F01.txt) names the claim; Step 4 allocates a separate item for this cycle **(default)**, so F01 can close on its own Done when.

## User stories

- As the maintainer, I want Start to claim what I picked, so that nobody else starts the same items while my agent works.
- As the maintainer, I want each row to show who holds it and to hide held rows, so that I pick from what is free.
- As an agent, I want to see whether an item is held before I build, and to claim and release from the command line, so that I do not duplicate work or block others after I stop.
- As a second person who wants a held item, I want to be told who holds it and since when, and still be able to go ahead, so that a forgotten claim does not block me.
- As a reader of the site, I want claims to be ordinary signed posts in one thread, so that I can see who started what without a special tool.

## Core requirements

- **The claim.** A reply in the site's claims thread whose first line is `claim:` followed by one or several item IDs. Anything after that line is a free note, such as a branch name. The line is read when the backlog is shown; nothing else is stored.
- **Who counts.** A claim counts when its post is unretracted and signed by an approved key. From a key not yet approved it reads "waiting for approval" and starts counting when the key is approved. With no name it reads "Claimed by a guest · not counted". IDs that match no open item are ignored and the post stays as written.
- **Holder.** The earliest counted, unended claim holds the item. Later counted claims show as "also claimed by NAME".
- **Ending.** A claim ends when its holder or an operator replies to it with `release`, or retracts it; when the site's checkout shows the item closed or `blocked`; or, as a label only, when it is more than 3 days old and the item has not been touched since: "stale". A `release` reply may name IDs to release some items of a claim and keep the rest **(default)**.
- **Start claims.** With a claims thread named and a signed-in viewer, pressing Start posts the claim as that viewer and then shows the start page, which says "Claimed by NAME" above the prompt. The prompt is shown whether or not the claim was posted; if it was not, the page says so and offers to try again. A viewer with no key gets the prompt and no claim **(default)**; visitors do not leave guest claims by pressing a button.
- **Start warns, never refuses.** If a picked item is already held, the start page names the holder and the date beside it and still shows the prompt.
- **The page.** Each row shows its holder, "also claimed" names, and "stale" when it applies. A "Hide claimed" filter, a readable word in the address, hides rows with a counted, unstale claim. A holder sees Release on their own rows **(default)**.
- **Agents.** `/backlog.txt` gains the holder lines for each item. A plain-text address lists the claims in force for given IDs. `/backlog/start.txt` names held items the way it names left-out ones and still returns the prompt. `./v3 feedback claim` and `./v3 feedback release` post the lines to a named site through Quill; an agent can also post the line itself as an ordinary reply.
- **Procedure.** `docs/agents/backlog_to_pr.md` §2 gains: read the claims for your items first; if another holder has a counted, unstale claim, stop and report; otherwise claim before building and release if you stop short of a PR. A guest or stale claim is a reason to look for the branch or PR, not to stop. The branch and PR search stays.
- **No thread, no claims.** A site with no claims thread named behaves exactly as today.

## Delivery scope

- Work type: application change: the claim and release lines and their reader; the claims thread setting in the site profile; the claim on Start; holder display, Release and the Hide claimed filter on `/backlog`; holder lines in the text routes and a claims address; `./v3 feedback claim` and `release`; the procedure; tests and the browser check; the concept entry *work claim*; a feedback item; the backlog checklist.
- Also in scope, small: `/backlog/start.txt` and `/backlog.txt` stop being held by browsers for two days, since they now report holders.
- Out of scope: claiming parked plans (`backlog_parked`); a claim record of its own (Step 1 Option C); showing a Claimed mark from an `#issue` thread on the row (the Step 1 suggestion; dropped here to keep one mechanism, and listed below); notifying a holder that a claim went stale; changing an item's workflow from the page; a list fresher than the last deploy.

## Completion boundary

- Normal entry: Tools → Backlog on zenmemes.com, on a phone; and `./v3 feedback claim` in a checkout.
- End-to-end outcome: the maintainer ticks two items and presses Start; both rows read "Claimed by ilyag"; a second session that follows the procedure for one of them reads the claim and stops; the first agent opens its PR, and after the next deploy the rows leave the startable list.
- Recovery: a claim that cannot be posted (offline, signing fails, the site is busy) leaves the prompt usable and says the items are not claimed, with a way to try again. A wrong claim is released from the row. A holder who has gone away is covered by stale and by an operator's release. A claims thread that is named but missing shows no claims and no error.
- Release: merged, deployed, the claims thread created and named at zenmemes.com, the two-session test run against the live site, and the maintainer's own Start from his phone.

## Known user or environment

The maintainer uses zenmemes.com on an iPhone SE in Safari and the offline web app, and deploys with Deploy now. Entry: Tools → Backlog, Start. Conditions to handle: signing the claim in the phone's browser, where Safari can have dropped the stored key; and a 375 px row that now carries a holder. Next action unaided: when the claim is not posted the start page says so; he can still copy the prompt, and can sign in and press Claim again. Evidence: the browser check at 375 px with and without a key, and his own Start on the phone. A desktop pass alone does not satisfy this. Setting the claims thread means a line in the site profile, which is a change in the repository or on the server; Step 3 must say which and keep it to something he can do from the phone or have done in the PR.

## Risks

- **Start now depends on posting.** Start was a plain link to a page; a signed post can fail or be slow, and high-traffic mode can delay when a new reply is read. Impact: Start feels broken, or a claim shows late. Earliest validation: before any page work, post a claim line by hand in a test thread and time until a reader sees it, in normal and high-traffic mode. Mitigation: the prompt never waits on the claim; the page states the claim's result.
- **Claim posts add noise.** Each claim and release is a reply that can surface in Recent, Unread and the agent check-in, and the maintainer already finds agent posting volume high. Impact: the site reads as bookkeeping. Earliest validation: list every place a reply in the claims thread would appear, before Step 3. Mitigation: Step 3 decides, with that list, whether bare claim and release replies stay out of those places; the thread itself remains complete.
- **Agents without an approved key claim as guests.** Their claims do not count, so two guest sessions can still collide. Impact: the duplicate work this cycle is meant to stop continues for exactly the agents that start cold. Earliest validation: the two-session test run once with keyed agents and once with a guest. Mitigation: the procedure treats a guest claim as a reason to look for the branch or PR; the row shows it.
- **Reading a thread on every backlog view.** The thread grows with every start. Impact: a slow backlog page. Earliest validation: measure the page with several hundred claim posts before markup. Mitigation: only unended claims on open items matter; Step 3 sets how the read is bounded.

## Shared component inventory

- `src/ForumRewrite/Support/PledgeDirective.php`, `WithdrawDirective.php` and `src/ForumRewrite/View/PledgeCampaign.php`: the pattern for a line in a reply, a bare reply that undoes it, and counting from the posts when shown, with the approved-key and no-name rules. The claim and release lines follow the same shape and the same counting rules; the pledge reader itself is not changed.
- `src/ForumRewrite/SiteProfileRegistry.php` (`guestbookThread`) and `GuestbookPageController::threadId()`: one thread named in the site profile, with a missing thread treated as empty. Reused for the claims thread.
- The guestbook's "Sign the book" return (`resolveComposeReplyReturnTo` in `ComposeAndAccountKeyController.php`) and the pledge buttons script (`public/assets/pledge_withdraw.js`): a button that posts a signed reply and returns to the page it came from. Reused for the claim on Start and for Release; no new posting path.
- `src/ForumRewrite/Feedback/FeedbackBacklog.php`, `BacklogText.php`, `BacklogPrompt.php` and `BacklogPageController.php`: the list, its words, the text form and the start page. Extended with the holder, the Hide claimed word and the claims address. The prompt's wording does not change.
- `templates/pages/backlog.php`, `backlog_start.php` and `public/assets/backlog.css`: extended; the row gains one line.
- `scripts/feedback.php`: gains `claim` and `release`. `public/quill.py`: used as is to post; whether its PHP and Perl ports need anything is a Step 3 check.
- Post retraction and the operator check used by pledges: reused for ending a claim.
- `docs/agents/backlog_to_pr.md`: edited in place.
- New: a claims reader that maps the thread's posts to holders per item. Nothing existing maps posts to feedback items.

## User flow

1. The maintainer opens Tools → Backlog. Three rows read "Claimed by claude". He taps Hide claimed and they go.
2. He ticks two bugs and presses Start. The start page reads "Claimed by ilyag" above the prompt. He copies the prompt into an agent.
3. Another session is given one of the two items. Following the procedure, it reads the claims, finds "ilyag, today", stops and says so.
4. The first agent opens its PR. The maintainer merges and deploys; both items are now `blocked` and leave the startable list. Nothing is posted to end the claim.
5. Another day he starts an item and changes his mind. He taps Release on the row, and the row is free again.
6. An item claimed four days ago with no change reads "Claimed by NAME · stale" and shows again under Hide claimed.

## Success criteria

- After Start by a signed-in, approved viewer, each picked row and its `/backlog.txt` block show that viewer as holder without a deploy.
- A `release` reply by the holder, or retracting the claim, frees the row; a `release` by another member who is not an operator does not.
- A claim with no name and a claim from an unapproved key each show their own wording and do not hide the row under Hide claimed.
- Two counted claims on one item show the earlier as holder and the later as "also claimed".
- A claim more than 3 days old on an untouched item reads "stale"; an item that is closed or `blocked` in the checkout shows no claim.
- With no key in the browser, with scripts off, and when the post fails, Start still shows the prompt and says the items are not claimed.
- With no claims thread named, every backlog page and text route gives the same bytes as before this cycle.
- Two-session test on the live site: the second session, given only the copied prompt, reports the holder and does not open a branch.
- No sideways overflow at 375 and 1280 px in every offered theme, with holder lines present.

## Enhancement requests

- **Request (agent's suggestion, Step 1):** show a Claimed mark from an `#issue` thread on the backlog row. **Disposition:** left out of this cycle; a second source of claims would need its own rules for ending and counting. **Approval status:** not approved.
- **Request (agent's suggestion, Step 1):** stop the two-day browser cache on the backlog text routes. **Disposition:** in this cycle's scope above. **Approval status:** approved with this step, if the step is approved.
- **Request (carried):** whether parked plans can be claimed. **Disposition:** cycle `backlog_parked`. **Approval status:** not approved.
- **Request (agent's suggestion):** tell a holder when their claim goes stale. **Disposition:** not planned. **Approval status:** not approved.

## Study snapshots and approval

- 2026-11-23 UTC: **Approved Step 1** by the maintainer ("approved step 1"); Option A, all defaults.
- 2026-11-23 UTC, Step 2 handoff: **0.40** reaffirmed. The noise and posting-from-Start risks could add work that Step 3 will size; not counted here. Scope v1. Claude, single exposed rater. Reference R1, hashes not re-verified this session. Baseline `origin/main` `cdc8abf46`.

- 2026-11-23 UTC: **Approved Step 2** by the maintainer ("approved step 2"). Defaults kept.

Status: Step 2 approved.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*
