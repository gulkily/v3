> **Feature plan:** [Step 1](./backlog_claims_step1_solution_assessment.md) · [Step 2](./backlog_claims_step2_feature_description.md) · [Step 3](./backlog_claims_step3_development_plan.md) · [Step 4](./backlog_claims_step4_implementation_summary.md)

Cycle: `v3/backlog_claims`, the second of three; see the [backlog checklist](../backlog_checklist.md). Branch: `feat/backlog_claims` from `origin/main` `9f73088e2` (fetched 2026-11-23, after PRs #380 and #381 merged). Planning commit: `f0c1576c6`.

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/backlog_claims`; host ledger [backlog_claims.md](../effort_ledger/backlog_claims.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.45** (Step 4 entry, 2026-11-23 UTC: Step 3's projection reaffirmed unchanged; Claude, single exposed rater)

Stage estimates copied from Step 3 (not re-rated): Stage 1 0.10, Stage 2 0.08, Stage 3 0.10, Stage 4 0.05, Stage 5 0.08, Stage 6 0.04, Stage 7 0.07.

Decisions in force (Step 3 approved with every default): the claims thread may rise on the board; the maintainer creates the thread, and until he gives its ID the zenmemes profile names none; the address word is `claimed=hide`; Start opens the start page at once and the claim posts from it; a site-made prompt gains a `Claims:` line.

## Stage 1 - The claims reader

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.08**

- Changes:
  - New `src/ForumRewrite/Support/ClaimDirective.php` and `ReleaseDirective.php`: read `claim: ID, ID` and `release` or `release: ID` from a post's first line that is not blank; `body()` writes the line a button or command posts; `isBare()` tells a line-only post from one with a note.
  - New `src/ForumRewrite/Feedback/BacklogClaims.php`: `fromPosts(array $posts, array $items, int $now, ?callable $isOperator = null)` gives, per item ID, `holder`, `also`, `waiting` and `guests`; `isHeld()` is the one test for "a counted, unstale claim holds this"; `lines()` gives the row's words.
  - New `tests/BacklogClaimsTest.php`, registered in `tests/run.php`.
- Verification:
  - `./v3 test --smoke BacklogClaimsTest`: passed (see the stage commit for the count). Full suite not run.
  - The reader over 500 claim posts, 166 releases and 350 items: under 10 ms here; the test fails above 50 ms. No bound on the read is needed.
  - High-traffic mode, answered by reading `docs/concepts/high_traffic_mode.md` and `public/.htaccess`: the mode serves saved copies of `/`, `/threads/`, `/about/` and thread pages only. `/backlog` and its text routes always reach PHP and read the read model, which a new reply updates when it is written. So a posted claim is readable on the next backlog request with the mode on or off. A measured post-then-read on a local site is part of Stage 2's page test, which posts through the write service and reads the page in one run.
- Notes:
  - Reuse: the line readers follow `WithdrawDirective`; the counting rule (unretracted, approved key, counted attribution; no name is never counted) is `PledgeCampaign`'s. `PledgeCampaign` itself is unchanged.
  - Narrower than the pledge reader on purpose: only the first non-blank line is read, as Step 2 says, so a `claim:` in a quote or lower in a note is plain text.
  - Two rules the plan left to this stage. (1) A fresh claim holds ahead of a stale one whoever came first, and a person's newest claim on an item replaces their older one, so claiming again refreshes it. Without this, a stale holder would block a later starter's claim from showing as the holder. (2) A holder may release their own claim even if their key is not approved; taking back your own waiting claim needs no approval. A release with no name or an unverified signature does nothing.
  - Stale counts from the claim or from the end of the item's touched day, whichever is later.
- Estimate details: original scope as Step 3 Stage 1; uncertainty was the high-traffic read delay, settled by reading; no revisions.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*

## Stage 2 - Holders on the backlog

**Original estimate: 0.08**

**Retrospective stage-work assessment: 0.09**

- Changes:
  - `SiteProfileRegistry`: `backlogClaimsThread` (null for zenmemes until the thread exists), its accessor and the same ID check as `guestbookThread`.
  - `FeedbackBacklog`: the word `claimed` (`show`, the default, or `hide`). It is known on every site and hides nothing where no thread is named.
  - `BacklogPageController::claims()` reads the thread's replies with `ThreadRepository::replyPostsByThreadId()` and the operator test pledges use (`ApprovalPolicy::isActiveOperator`). It returns null when no thread is named, and no claims when the named thread is missing or retracted. The list and `/backlog.txt` leave held items out under `claimed=hide`.
  - `templates/pages/backlog.php`: one more line on a row that has claims. "Hide claimed" is one more word on the Show row, a switch whose active link turns it off; it is drawn only where a thread is named.
  - `BacklogText`: with a thread named, the header gains `Claimed:` and `Claims-Address:` and a claimed item's block gains `Claim-State` (`held`, `stale` or `none`), `Claimed-By`, `Claimed-Since`, `Claim-Post`, `Also-Claimed-By`, `Claims-Waiting-For-Approval` and `Guest-Claims`.
  - Tests: four new `BacklogPageTest` cases; `tests/Support/backlog_claims_browser_router.php`; `tests/browser/backlog.mjs` runs a second server whose profile names a thread.
- Verification:
  - `./v3 test --smoke BacklogPageTest BacklogClaimsTest FeedbackBacklogTest`: passed. Full suite not run.
  - No thread named, same bytes as before: six routes (`/backlog.txt` twice, `/backlog` twice, `/backlog/start`, `/backlog/start.txt`) rendered against the parity fixture before and after this stage and compared with `cmp`, asset fingerprints masked: identical. `./v3 feedback list` output identical.
  - Post, then read: the page test posts a claim through the write service and finds the holder on the next request, with nothing rebuilt.
  - `node tests/browser/backlog.mjs`: passed, 176 theme, width and script cases, now with holder lines on every list; no sideways overflow at 375 or 1280 px.
- Notes:
  - Seen at 375 px (Gloss): "Hide claimed" wraps the Show row onto a third line, so the link rows above the list go from five lines to six. It is one word on an existing row, as planned, but the rows were already noted as long.
  - `./v3 feedback list` prints no claim lines: a checkout does not know a site. `/backlog.txt` on a site with a thread therefore prints more than the command does; on a site without one they are still byte for byte the same.
  - `Claims-Address` is printed here and served from Stage 5.
- Estimate details: original scope as Step 3 Stage 2; uncertainty low; no revisions.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*

## Stage 3 - Start claims

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.12**

- Changes:
  - `templates/pages/backlog_start.php`: on a site with a claims thread each picked item says who holds it, with the date ("Claimed by ilyag since Nov 23"), or "Not claimed". A viewer the server recognises gets a Claim form for the picked items they have no claim on: a reply to the claims thread whose body is the claim line.
  - New `public/assets/backlog_claim.js`: signs and sends the form through the same prepare and finalize calls as `pledge_withdraw.js`. Pressing Start on the list leaves a one-time note in the tab; the start page, finding it, sends the form without a tap. After a claim it swaps in the server's fresh copy of the item list, so the prompt stays where it is. On failure the form says "Not claimed." with the reason and the button stays.
  - `templates/pages/backlog.php`: the Start handler leaves that note, only on a site with a claims thread.
  - `BacklogPageController::start()` takes the viewer the thread page would recognise (session, else identity hint), passed from `Application`; it loads the signing scripts only where a thread is named. `BacklogClaims::hasClaimBy()` and dates in `lines()`.
  - `public/assets/backlog.css`: the form, hidden when scripting is off.
- Verification:
  - `./v3 test --smoke BacklogPageTest BacklogClaimsTest FeedbackBacklogTest`: passed. Full suite not run.
  - `node tests/browser/backlog.mjs`: passed. New cases, all on a real signed post: Start by an approved member claims two items with one post while the prompt is already on screen; an item someone else holds shows the holder and the member as "Also claimed by"; a reload and a pasted start address post nothing; a blocked request shows "Not claimed." and a second press of Claim works; Start with the request blocked leaves the prompt and "Not claimed."; a visitor who never posted gets no form and posts nothing; with scripting off the recognised member's form is in the page and not shown.
  - No thread named: the six routes compared with the planning commit by `cmp`: identical except the page's `app-version` meta.
- Notes:
  - Known user's condition (Safari dropped the key): replayed as "a visitor who never posted" and as "the request fails". In both the prompt is usable and the page says the items are not claimed. Not replayed on a real iPhone.
  - Reuse: the pledge buttons' posting path. A second small script, not an extension of `pledge_withdraw.js`, because that one reloads the page and reads pledge forms.
  - The scripts-off case needed a browser started with scripting off; the test browser's per-page switch still reports scripting as on to CSS. The form is hidden by a `(scripting: none)` rule, as other script-only controls on the site are. A browser too old for that rule would show the button with scripts off, and pressing it would post a claim with no name, which is kept and not counted.
  - Who is recognised: the same test the pledge buttons use, including the identity hint. It only decides whether the form is drawn; the claim is a signed post either way. A recognised viewer whose key is not approved can claim, and the row then reads "waiting for approval", as Step 1 decided.
  - Seen in the check: a browser key with no chosen name is labelled "guest" by the site, so its claim reads "Claimed by guest", close to the wording for a claim with no name ("Claimed by a guest · not counted"). Not changed here.
- Estimate details: original scope as Step 3 Stage 3; uncertainty was signing on a page that had none; no revisions. The retrospective is higher for the viewer lookup and the scripts-off case.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*

## Stage 4 - Release

**Original estimate: 0.05**

**Retrospective stage-work assessment: 0.06**

- Changes:
  - `BacklogClaims::releasable()`: the claim post a viewer may release on an item: their own first; for an operator, otherwise the holder's, then any other claim.
  - New `templates/partials/backlog_release.php`: one form per releasable row, a reply to the claim post whose body is `release: <ID>`, so a claim on several items keeps the others.
  - `templates/pages/backlog.php` and `backlog_start.php`: a Release button on the holder line, for the holder and for operators. On the list the rows sit inside the Start form, where a form cannot nest, so the forms are drawn after the list and each button names its form.
  - `backlog_claim.js` sends Release the same way as Claim, then swaps in the fresh row; the start page swaps its whole claims area, so Claim comes back after a release there.
  - `BacklogPageController::list()` takes the recognised viewer, as `start()` does.
- Verification:
  - `./v3 test --smoke BacklogPageTest BacklogClaimsTest FeedbackBacklogTest`: passed. Full suite not run.
  - `BacklogPageTest::testReleaseIsOfferedToTheHolderAndToAnOperator`: no Release for nobody or a stranger; the holder's form answers the claim post and names one ID; forms are not nested in the Start form; an operator gets Release on rows it does not hold; releasing one item leaves the other held.
  - `node tests/browser/backlog.mjs`: passed. Release from the row with a real signed post: a blocked request shows "Not released." and the button stays; a second press frees the item; the other item of the same claim stays; ticks on other rows survive; the row with Release fits 375 px.
  - No thread named: the six routes are still identical to the planning commit except the `app-version` meta.
- Notes:
  - Reuse: the pledge buttons' viewer test and posting path; Stage 3's script.
  - The browser check approves keys with an approval seed, which also makes them operators, so its member can still release a guest claim after releasing their own. The page test covers a holder who is not an operator.
  - Pressing Release twice: the second release is a second reply that changes nothing; the button is gone after the first one lands.
- Estimate details: original scope as Step 3 Stage 4; uncertainty low; no revisions.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*

## Stage 5 - Agents

**Original estimate: 0.08**

**Retrospective stage-work assessment: 0.09**

- Changes:
  - `/backlog/claims.txt?items=<ID>,<ID>`: a header block (the claims thread, the stale period, how to claim and release) and one block per open item with `Claim-State` (`held`, `stale`, `none` or `free`) and its claim lines. 404 on a site with no claims thread; 400 with no ID; items left out are named in `X-Left-Out`.
  - `/backlog/start.txt` names held items in an `X-Claimed` header and returns the prompt all the same.
  - The three text routes send `Cache-Control: no-cache`.
  - `BacklogPrompt::build()` takes an optional claims address. A site with a claims thread adds one line under `Repository:`, `Claims: <origin>/backlog/claims.txt?items=<the items>`, on the start page and in `/backlog/start.txt`. A checkout, and a site with no thread, print the prompt as before.
  - `./v3 feedback claim --site=<address> <ID>... [--note=] [--anyway]` and `./v3 feedback release --site=<address> <ID>... [--post=]` in `scripts/feedback.php`: read the claims address, print it, and post the line with Quill. `claim` refuses with exit 1 when a counted, unstale claim holds an item.
  - `docs/agents/backlog_to_pr.md` §2: read claims first; what each `Claim-State` means; claim before building; release when stopping short of a PR. `docs/reference/agent_api.md`, `docs/reference/v3_cli.md`, the `./v3` usage text and the `/llms.txt` backlog line follow.
- Verification:
  - `./v3 test --smoke BacklogPageTest BacklogClaimsTest FeedbackBacklogTest LlmsTxtRendererTest`: 540 run, 540 passed. Also `PlatformDocsCatalogTest ScriptsIndexTest UsageNamesTest`: passed. Full suite not run.
  - `FeedbackBacklogTest::testClaimAndReleaseCommandsReadTheSiteAndPostThroughQuill`: against a stand-in site and a stand-in Quill that records what it is asked to post: free items are claimed with one line and the note; a held item is named, nothing is posted, exit 1; `--anyway` posts; `release` answers the claim post and names the item; `--post` names another claim; a site with no claims, a missing site and missing arguments fail with a reason.
  - `node tests/browser/backlog.mjs`: passed. Over HTTP: the claims address and its `X-Left-Out`; `X-Claimed` on `/backlog/start.txt` with a 200; the `Claims:` line with the site's own address; `Cache-Control: no-cache` on all three; 404 from a site with no claims thread.
  - No thread named: the six routes' bodies are still identical to the planning commit except the `app-version` meta.
- Notes:
  - The commands were tested with a stand-in Quill. The real `public/quill.py` against a real local site is part of Stage 7's two-session check.
  - `claim` refuses on any `held` item, including one the caller already holds: the command cannot know who the caller is before Quill signs. Claiming again is never needed while a claim is fresh.
  - `release` answers the holder's claim by default. From anyone but that claim's author or an operator it has no effect, and the command cannot tell; `--post` names your own claim when someone else holds the item.
  - `V3_QUILL` names another client (`php public/quill.php`, `perl public/quill.pl`). The ports needed no change: a claim is an ordinary reply.
  - The cache header is the application's. Whether the host's own setting still overrides it on zenmemes.com can only be seen on the live site after deploy.
  - Approved with Step 3: the `Claims:` line changes Step 2's sentence that the prompt's wording does not change.
- Estimate details: original scope as Step 3 Stage 5; uncertainty was Quill inside `./v3`, handled by shelling out to the client; no revisions.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*

## Stage 6 - Claims stay out of Unread

**Original estimate: 0.04**

**Retrospective stage-work assessment: 0.03**

- Changes:
  - `Inbox/InboxFeed`: takes the site's claims thread. In "other new posts" it leaves out a reply in that thread whose whole body is one claim or release line. `InboxPageController` passes the thread from the site profile.
  - `tests/InboxFeedTest.php`: one new case.
- Verification:
  - `./v3 test --smoke InboxFeedTest InboxPageTest BacklogPageTest`: passed. Full suite not run.
  - The new case: a line-only claim, a bare release, a release naming an item and a guest's claim are left out, in the list and in the count; a claim with a note, ordinary talk in the claims thread and the same lines in another thread still show; a release that answers the member's own claim still reaches them under replies; a site with no claims thread shows everything, as before.
- Notes:
  - What one line-only claim and one release add, before and after this stage:

    | Place | Before | After |
    | --- | --- | --- |
    | Unread, other new posts | 2 posts | none |
    | Unread, replies to you | the release, if it answers your claim | the same |
    | Recent | 2 posts | 2 posts |
    | Board | the claims thread moves to the top | the same (Step 3 decision: leave it) |
    | Agent check-in | nothing, unless it answers the agent's post | the same |
    | The claims thread | 2 replies | 2 replies |

    The Unread rows are tested. The Recent, board and check-in rows come from reading `RecentPostRepository`, the thread's `last_activity_at` update and the check-in concept; they were not run.
  - The rule is written in SQL, so it is a little wider than `ClaimDirective`: a one-line reply `claim: something` that names no real item is also left out of Unread. It still shows in the thread and in Recent.
  - Recent still lists every claim. If that proves noisy on the live site, it is its own small change.
- Estimate details: original scope as Step 3 Stage 6; uncertainty low; no revisions.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*

## Stage 7 - Proof and records

**Original estimate: 0.07**

**Retrospective stage-work assessment: 0.07**

- Changes:
  - `tests/browser/backlog.mjs`: a two-session run with no browser. Two agents with their own keys use the real `./v3 feedback claim` and `release` and the real `public/quill.py` against the check's site.
  - New `docs/concepts/work_claim.md`; `docs/concepts/backlog_view.md` revised (the `claimed` word, Start claiming, the cache header, the boundary).
  - New feedback item `PF-20261123-F02`, `blocked`, with the index regenerated.
  - `docs/plans/ui_copy_audit_checklist.md` and `docs/plans/backlog_checklist.md` (cycle 2 ticked as far as it goes; the fresh-agent start from cycle 1 recorded as PR #380; PR #381 merged).
  - `BacklogPageController::list()` loads the signing scripts only when a Release button is drawn, found in the final read of the diff.
- Verification:
  - `./v3 test --smoke BacklogClaimsTest BacklogPageTest FeedbackBacklogTest InboxFeedTest InboxPageTest LlmsTxtRendererTest PlatformDocsCatalogTest ScriptsIndexTest UsageNamesTest GuestbookPageTest SiteProfileRegistryTest AgentCheckinTest`: 586 run, 586 passed. The full suite was not run.
  - `node tests/browser/backlog.mjs`: passed, 176 theme, width and script cases plus every flow named in Stages 2 to 5.
  - Two sessions, in that check: session one meets a guest claim, is shown it, and claims; its key is not approved, so the claim reads as waiting and holds nothing; once the key is approved the same post counts. Session two, another key, asks for the same item: it is told who holds it and since when, posts nothing and exits 1. Session two's release of session one's claim changes nothing. Session one releases; session two then claims.
  - `./v3 feedback check`: 362 items, 0 problems. `./v3 feedback index`: written. `git diff --check`: clean. Links in the concept entry, the summary, the checklist and the procedure resolve.
  - No thread named: the six routes compared with the planning commit: identical except the page's `app-version` meta and the new feedback item appearing in the list it belongs in.
  - Final read of the diff against the plan: 35 files; no new dependency, no schema change, no new flag; `PledgeCampaign`, the pledge script and `quill.py` are unchanged.
- Notes:
  - Pending for release, not done here: the claims thread on zenmemes.com and its ID in the profile; the two-session test against the live site; the live `Cache-Control` after deploy; the maintainer's Start from his iPhone with and without a stored key.
  - The guest run measures what the plan asked: a second session is told about a guest claim. Whether an agent then looks for the branch, as the procedure says, depends on the agent reading the procedure; that was not run with a fresh agent.
  - `PF-20261123-F01` is not changed. PR #380 looks like the start its Done when asks for; closing it is the maintainer's call.
- Estimate details: original scope as Step 3 Stage 7; uncertainty was the scripted two-session check; no revisions.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*

## Cycle closeout

**Retrospective scope assessment: 0.50** (projection at Step 4 entry 0.45; Steps 1 and 2 said 0.40)

- Delivered as planned in seven stages, each with its own commit after the planning commit `f0c1576c6`.
- Larger than projected in three places: recognising the viewer for Claim and Release, the scripts-off case, and Release forms that cannot nest in the Start form.
- Completion Contract: normal entry and end-to-end outcome are met on a local site with real signed posts. Recovery cases are met locally. Deployment needs no schema or flag change. Release conditions are pending as listed in Stage 7.
- Decisions taken as defaults, all recorded in Step 3's approval line. One is still open in practice: the thread's ID.
- Published: PR https://github.com/gulkily/v3/pull/395, opened 2026-11-23; not merged.
- Maintainer comfortable-fit judgment: pending. Measured time: unknown.

Debug: [debug log](./backlog_claims_debug_log.md).

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*
