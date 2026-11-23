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
