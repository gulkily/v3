> **Feature plan:** [Step 1](./backlog_claims_step1_solution_assessment.md) · [Step 2](./backlog_claims_step2_feature_description.md) · [Step 3](./backlog_claims_step3_development_plan.md) · [Step 4](./backlog_claims_step4_implementation_summary.md)

Cycle: `v3/backlog_claims`, the second of three; see the [backlog checklist](../backlog_checklist.md). Branch: `feat/backlog_claims`, new from a freshly fetched `origin/main` after this step is approved.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/backlog_claims`; host ledger [backlog_claims.md](../effort_ledger/backlog_claims.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.45** (revised; Steps 1 and 2 said 0.40)

Concepts touched: new *work claim* entry (Stage 7). [Backlog view](../../concepts/backlog_view.md) (revised in Stages 2 to 5). [Pledge campaign](../../concepts/pledge_campaign.md) and [guestbook](../../concepts/guestbook.md): patterns followed, entries unchanged. Feedback: a new item for claims is allocated in Stage 7; [PF-20261123-F01](../../feedback/items/PF-20261123-F01.txt) is not touched.

## What inspection settled before this plan

Step 2 promised two checks before Step 3. Both were done by reading the code; the timing measurement moves to Stage 1.

- **Where a reply in the claims thread shows up.** It moves the thread to the top of the board (`last_activity_at`), it counts as an "other new post" on Unread (`Inbox/InboxFeed.php`), and it appears in Recent. It does not appear in the agent check-in's replies section unless it answers the agent's own post. Stage 6 keeps bare claim and release replies out of Unread. The board order is left alone; see the first decision below.
- **How a button posts a signed reply.** `public/assets/pledge_withdraw.js` signs and sends a one-line reply through the Outbox's prepare and finalize calls, and the template decides who sees the button from the viewer the server recognises. Claim and Release follow it.
- **Where the thread is named.** `SiteProfileRegistry` holds `guestbookThread` in code, so naming the claims thread is a line in the zenmemes profile, changed in the PR. See the second decision.
- **The two-day cache** on the text routes is not in the repository's `public/.htaccess` (which says 300 seconds for assets). It comes from the host or the server's own copy. Stage 5 sends an explicit header and checks the live answer after deploy.

## Decisions asked for in this step

Approving the step without naming one takes the default.

- **The claims thread rises on the board with every claim.** Default: leave it; one thread at the top is a fair picture of a site whose work is being started. Alternative: claim replies do not move it, which touches the read model's update of a thread's last activity and adds about 0.05.
- **Who creates the thread.** Default: the maintainer posts a thread titled "Backlog claims" on zenmemes.com before the PR opens and gives its ID; Stage 2 puts the ID in the zenmemes profile, so the first deploy turns claims on. Alternative: `claude` posts it. Without an ID the PR ships with no thread named and claims stay off until a follow-up.
- **The address word.** Default: `claimed=hide`, as in `/backlog?claimed=hide`; absent means show everything.
- **Start and the claim.** Default: Start opens the start page at once, as today, and the claim is posted from that page in the background; the page then shows "Claimed by NAME" or "Not claimed" with Claim. Opening a start address from a link or a reload never claims. This keeps Start as fast as it is.

## Completion Contract

- Normal entry: Tools → Backlog on zenmemes.com, on a phone; `./v3 feedback claim` in a checkout.
- End-to-end outcome: the maintainer ticks two items and presses Start; both rows read "Claimed by ilyag"; a second session that follows the procedure reads the claim and stops; after the PR is merged and deployed the items leave the startable list.
- Recovery: no key, scripts off, or a failed post leave the prompt usable and the page saying "Not claimed", with Claim to try again when a key exists. Release frees a wrong claim. Stale and an operator's release cover a holder who went away. A named thread that is missing shows no claims and no error.
- Deployment: no schema change and nothing stored beyond posts. No new flag: claims exist when the Backlog flag is on and the profile names a thread. Deploy now is enough.
- Release: merged; deployed with the thread named; the two-session test run against zenmemes.com; the maintainer's own Start from his iPhone, with and without a stored key.

## Key Risks

- **High risk:** Start now leads to a post. Impact: a slow or failed claim makes Start feel broken, most likely on the phone when Safari has dropped the key. Early validation: Stage 1 posts a claim line into a local thread and times until the reader sees it, with high-traffic mode on and off; Stage 3 replays no key, scripts off and a failed post in the browser check. Mitigation: the start page and prompt never wait on the claim.
- **High risk:** agents without an approved key claim as guests, and guest claims do not count. Impact: two cold-start sessions still collide. Early validation: Stage 7's two-session test runs once with keys and once with a guest. Mitigation: the procedure treats a guest or stale claim as a reason to look for the branch or PR; the row shows it.
- Claim posts add noise. Impact: Unread and Recent fill with bookkeeping. Early validation: Stage 6 lists what a claim and a release add to Unread, Recent and the board before and after its change. Mitigation: bare claim and release replies stay out of Unread; the thread stays complete.
- Reading the thread on every backlog view. Impact: a slow page as the thread grows. Early validation: Stage 1 times the reader over 500 claim posts. Mitigation: if it costs more than a few milliseconds, read only replies newer than the stale period plus those still held; decided in Stage 1 before any page work.

## Stage 1

**Estimate: 0.10**

- Goal: one reader turns the claims thread's posts into who holds each item, and the two timing questions are answered.
- Dependencies: none.
- Expected changes: new `Support/ClaimDirective` (`extract(string $body): ?array` of item IDs from a first-line `claim:`) and `Support/ReleaseDirective` (`extract(string $body): ?array`, empty for the bare line; `isBare()`), shaped like `WithdrawDirective`. New `Feedback/BacklogClaims::fromPosts(array $posts, array $items, int $now, ?callable $isOperator = null): array`, giving per item ID: `holder`, `since`, `claim_post_id`, `also` (later counted claims), `guests`, `waiting`, `stale`. Rules as approved: counted means unretracted, approved key, counted attribution; earliest counted claim holds; a release by the same key or an operator ends it, in whole or for named IDs; items that are closed or `blocked` show nothing; stale after 3 days when the item was not touched since.
- Verification approach: new `BacklogClaimsTest`: each rule above, order independence of release and claim, a release by a stranger, retraction, unknown IDs, two holders, a quoted `claim:` line that is not first. Timing: the reader over 500 generated posts; a claim posted into a local site, then read, with high-traffic mode on and off.
- Estimate details (extended mode only): driver: the ending rules and their interactions; uncertainty: medium (high-traffic read delay).
- Risks or open questions:
  - Impact: a claim that shows minutes late defeats its purpose.
  - Early warning / validation: the timing run.
  - Mitigation: if high-traffic mode delays the read, Stage 2 reads the thread's posts without the saved page, as the backlog page already does for items; if it still lags, stop and report before Stage 3.
- Canonical components/API contracts touched: `Support/PledgeDirective.php`, `WithdrawDirective.php` and `View/PledgeCampaign.php` (pattern and counting rules followed; not changed). New classes because nothing maps posts to feedback items.

## Stage 2

**Estimate: 0.08**

- Goal: the backlog shows who holds each item, and held rows can be hidden.
- Dependencies: Stage 1.
- Expected changes: `SiteProfileRegistry` gains `backlogClaimsThread` (validated like `guestbookThread`; set for zenmemes when the ID is known). `BacklogPageController` loads the thread's replies with `ThreadRepository::replyPostsByThreadId()` and passes the Stage 1 result to the list. `templates/pages/backlog.php`: one more line on a held row ("Claimed by NAME", "also claimed by NAME", "· stale", "Claimed by a guest · not counted", "waiting for approval"). `FeedbackBacklog::words()` gains `claimed` (`hide`); the link row gains "Hide claimed". `BacklogText` adds `Claimed-By`, `Claimed-Since` and `Claim-State` lines to a held item's block.
- Verification approach: `BacklogPageTest`: each wording; Hide claimed hides counted, unstale claims only; the address word; no thread named gives the same bytes as before for the page and `/backlog.txt` (compared against the current output); a named but missing thread. `node tests/browser/backlog.mjs`: holder lines at 375 and 1280 px in every theme, no sideways overflow.
- Estimate details (extended mode only): driver: one more row line in every theme; uncertainty: low.
- Risks or open questions:
  - Impact: a sixth line of links on a 375 px phone, where five already precede the list.
  - Early warning / validation: the 375 px screenshot.
  - Mitigation: "Hide claimed" joins the Show row as one more word, not a new row.
- Canonical components/API contracts touched: `SiteProfileRegistry.php` and `Http/GuestbookPageController.php` (pattern reused); `Feedback/FeedbackBacklog.php`, `BacklogText.php`, `Http/BacklogPageController.php`, `templates/pages/backlog.php`, `public/assets/backlog.css` (extended).

## Stage 3

**Estimate: 0.10**

- Goal: pressing Start claims the picked items, and the start page says what happened.
- Dependencies: Stage 2.
- Expected changes: `templates/pages/backlog_start.php`: each picked item shows its holder; an item held by someone else is marked with the holder and date; a Claim form (a reply to the claims thread whose body is the `claim:` line for the unheld picked items) is drawn only for a viewer the server recognises, as the pledge buttons are. New `public/assets/backlog_claim.js`: Start on the list leaves a one-time note for the picked IDs; the start page, finding that note, sends the Claim form through the same signing calls as `pledge_withdraw.js`, then shows "Claimed by NAME" or "Not claimed" with the reason. No note, no automatic claim. `BacklogPageController::start()` passes holders and the thread ID.
- Verification approach: `BacklogPageTest`: the form's body and target; held items marked; no form for a signed-out viewer or with no thread. `tests/browser/backlog.mjs`: Start with a key claims and both rows show it; a reload of the start page posts nothing more; opening the start address directly posts nothing; no key; scripts off; a failed post (the request blocked) shows "Not claimed" and Claim works on retry; the prompt is on screen before the claim settles in every case.
- Estimate details (extended mode only): driver: the failure cases in a real browser; uncertainty: medium (signing from a page that did not have it before).
- Risks or open questions:
  - Impact: the first high risk above.
  - Early warning / validation: the browser cases, written before the script.
  - Mitigation: if signing cannot load on the start page without slowing first paint, the claim waits for a tap on Claim and the plan is revised with the maintainer.
- Canonical components/API contracts touched: `public/assets/pledge_withdraw.js` and `browser_signing.js` (calls reused; a second small script because the pledge one reloads the page and reads pledge forms); `templates/partials/agent_prompt.php` (unchanged).

## Stage 4

**Estimate: 0.05**

- Goal: a holder frees an item from its row.
- Dependencies: Stage 3.
- Expected changes: on a row the viewer holds, and for an operator on any held row, a Release button: a reply to the claim post whose body is `release: <ID>`. It uses the Stage 3 script and returns to the list. The start page shows Release beside the viewer's own claim.
- Verification approach: `BacklogPageTest`: the button for the holder and an operator, none for others; the body names one ID so a two-item claim keeps the other. Browser check: Release frees the row; pressing twice changes nothing more.
- Estimate details (extended mode only): driver: who sees the button; uncertainty: low.
- Risks or open questions:
  - Impact: a button on every held row crowds a 375 px row.
  - Early warning / validation: the screenshot with several held rows.
  - Mitigation: Release sits on the holder line, shown only to those who can use it.
- Canonical components/API contracts touched: `templates/partials/pledge_amount.php` (the viewer test followed); Stage 3's script.

## Stage 5

**Estimate: 0.08**

- Goal: an agent reads, makes and ends claims without a browser.
- Dependencies: Stage 1 (Stage 2 for the text lines).
- Expected changes: `/backlog/claims.txt?items=<ID>,<ID>` returns one `Key: value` block per item with its claim lines, and the thread's address for posting. `/backlog/start.txt` names held items in an `X-Claimed` header and still returns the prompt unchanged. The three text routes send `Cache-Control: no-cache`. `./v3 feedback claim --site=<address> <ID>...` and `./v3 feedback release --site=<address> <ID>...` read the claims address, print who holds what, and post the line with `public/quill.py reply`; with a counted claim by someone else, `claim` exits 1 and posts nothing unless `--anyway` is given. `docs/agents/backlog_to_pr.md` §2: read claims first, stop on a counted unstale claim by another, claim before building, release when stopping short of a PR; a guest or stale claim means look for the branch or PR. `docs/reference/agent_api.md` and `v3_cli.md` follow.
- Verification approach: `BacklogPageTest` for the two routes, the header and the cache header; `FeedbackBacklogTest` for both commands against a local site, including the refusal and `--anyway`; the prompt bytes unchanged. Read the procedure against thread to pull request.
- Estimate details (extended mode only): driver: a command that posts to a site from a checkout; uncertainty: medium (Quill's key handling inside `./v3`; whether `quill.php` and `quill.pl` need anything, expected not).
- Risks or open questions:
  - Impact: the prompt does not say which site holds the claims, so an agent in a bare checkout cannot find them.
  - Early warning / validation: run the procedure by hand from the prompt alone.
  - Mitigation: a prompt made by a site gains one line, `Claims: <address>/backlog/claims.txt`, only when that site has a claims thread; `./v3 feedback start` in a checkout prints no such line.
- Canonical components/API contracts touched: `scripts/feedback.php`, `Application.php` text routes, `Feedback/BacklogPrompt.php` (one optional line), `public/quill.py` (used as is).

## Stage 6

**Estimate: 0.04**

- Goal: claims do not fill Unread.
- Dependencies: Stage 1.
- Expected changes: `Inbox/InboxFeed` leaves a reply out of "other new posts" when it is in the site's claims thread and is a bare claim or release line. A claim with a note, and any other reply in the thread, stay. Replies to the member's own posts are untouched, so a release of your claim by an operator still reaches you.
- Verification approach: the Unread tests with a bare claim, a claim with a note and a release; a before and after list of what one claim and one release add to Unread, Recent and the board, written into the Step 4 summary.
- Estimate details (extended mode only): driver: one query condition; uncertainty: low.
- Risks or open questions:
  - Impact: hiding posts from Unread can hide something that matters.
  - Early warning / validation: the before and after list.
  - Mitigation: only the bare lines are left out, and only from Unread.
- Canonical components/API contracts touched: `Inbox/InboxFeed.php` (extended).

## Stage 7

**Estimate: 0.07**

- Goal: prove the cycle and write it down.
- Dependencies: Stages 1 to 6.
- Expected changes: a two-session check, scripted against a local site: session one claims with an approved key; session two, given only the prompt and the procedure's commands, is told the holder and stops; repeated with session one as a guest, where session two is told of a guest claim and goes on to the branch search. New concept entry `docs/concepts/work_claim.md`; the backlog view entry revised. A new feedback item for claims, `blocked` until release. `docs/plans/ui_copy_audit_checklist.md`, the backlog checklist and the ledger.
- Verification approach: `./v3 test --smoke BacklogClaimsTest BacklogPageTest FeedbackBacklogTest` and the Unread tests touched; `node tests/browser/backlog.mjs`; `./v3 feedback check` and `index`; `git diff --check`; a read of the whole diff against this plan. Full suite not run. Replay of the named environment: the browser check at 375 px with a key, with none, and with scripts off. Left for release and stated as pending: the two-session test against zenmemes.com, the live cache header, and the maintainer's Start on his iPhone.
- Estimate details (extended mode only): driver: the scripted two-session check; uncertainty: medium.
- Risks or open questions:
  - Impact: the second high risk above.
  - Early warning / validation: the guest run.
  - Mitigation: if a guest second session does not find the first session's work, the procedure text is revised in this stage.
- Canonical components/API contracts touched: `docs/concepts/`, `docs/feedback/items/`, `tests/browser/backlog.mjs`.

## Enhancement requests

- **Request (agent's suggestion):** claim replies do not move the claims thread up the board. **Disposition:** the first decision above; not in the plan unless chosen. **Approval status:** not approved.
- **Request (Step 2, agent's suggestion):** stop the two-day browser cache on the backlog text routes. **Disposition:** Stage 5. **Approval status:** approved with Step 2.
- **Request (new in this step):** a `Claims:` line in a site-made prompt. **Disposition:** Stage 5; Step 2 said the prompt's wording does not change, so this is a change to that sentence and needs this step's approval. **Approval status:** pending with this step.
- **Request (carried):** `#issue` Claimed marks on rows; claiming parked plans; telling a holder about a stale claim. **Disposition:** not in this cycle. **Approval status:** not approved.

## Study snapshots and approval

- 2026-11-23 UTC: **Approved Step 2** by the maintainer ("approved step 2").
- 2026-11-23 UTC, Step 3 handoff: **0.45**, up from 0.40. The plan adds a Release stage and an Unread stage that the earlier figure did not size. Stage values 0.10, 0.08, 0.10, 0.05, 0.08, 0.04, 0.07 are stage work, not summed. Scope v1 with the `Claims:` line pending. Claude, single exposed rater. Reference R1, hashes not re-verified this session. Baseline `origin/main` `cdc8abf46`.

- 2026-11-23 UTC: **Approved Step 3** by the maintainer ("Approved step 3."). No default changed: the claims thread may rise on the board; the maintainer creates the thread (no ID given yet, so the profile names none until he does); the address word is `claimed=hide`; Start opens the start page at once and the claim posts from it. The `Claims:` line in a site-made prompt is approved with the step.

Status: Step 3 approved.

*— Claude · 2026-11-23 UTC · Model: claude-fable-5-2*
