> **Feature plan:** [Step 1](./app_sessions_step1_solution_assessment.md) · [Step 2](./app_sessions_step2_feature_description.md) · [Step 3](./app_sessions_step3_development_plan.md) · [Step 4](./app_sessions_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./app_sessions_debug_log.md))
Effort study: `railspike-effort` / `v3/app_sessions` ([ledger](../effort_estimation_trial.md#cycle-v3app_sessions)); standing enrollment, protocol P2
Extended estimates: enabled

**Cycle estimate: 0.95**

Branch: `feat/app-sessions` from `main` `e6493e2f`. Planning commit `eafa09b5`.

## Step 4 entry checkpoint

- 2026-11-03 UTC, after the planning commit and before implementation: whole-cycle **0.95 reaffirmed** (Step 3 value), scope v1/Option A, baseline `e6493e2f`, R1/P2. Claude (claude-opus-5-5), single exposed rater.
- Stage-work originals copied from Step 3, not re-rated: S1 0.15, S2 0.10, S3 0.10, S4 0.25, S5 0.07, S6 0.15, S7 0.08. Not additive.
- Baseline full suite: running on a detached `main` worktree; result recorded in Stage 1.

## Enhancement requests

- Step 1 workaround inventory (2026-11-03): server-side items in scope (Stages 2–4); client simplifications, Deploy Sign in button, lobby-gate log removal and operator-delegation reintegration remain separate work.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Stage 1 - Session store, resolver and per-scheme cookie

**Original estimate: 0.15**

**Retrospective stage work: 0.12**

- Changes:
  - `src/ForumRewrite/Session/`: `ViewerSessionStore` (token hashes only; 400-day lifetime with no idle expiry; same-identity sign-in keeps the token; a replaced token forwards 30 s, matching the decision in `session_rotation_race_decision.md`; sign-out keeps the Lobby identity; single-use sign-in challenges with a 10,000 open-challenge cap; activity written at most hourly), `ViewerSession`, `ViewerSessionCookie` (`__Host-v3_session` over https, `v3_session` over http; legacy `PHPSESSID` expiry), `ViewerSessionDatabaseConfig` (`state/private/viewer_sessions.sqlite3`, WAL, 5 s busy timeout).
  - `PrivateConfigSchema`: `VIEWER_SESSION_DATABASE_PATH` (path, not in the template, like the other store paths).
  - `tests/ViewerSessionStoreTest.php`, registered in `tests/run.php`. Not yet wired into the app.
- Verification:
  - `php tests/run.php ViewerSessionStoreTest`: 14/14 passed, including idle 30 days, copied cookie after sign-out, forwarding window, scheme isolation and 12 concurrent processes on one file (no lock errors).
  - `php tests/run.php PrivateConfigCommandTest PrivateConfigSchemaTest`: 13/13 passed.
  - Export/backup risk: no archive, backup, checkout or deploy code reads `state/private/` wholesale (searched `src/`, `scripts/`, `v3`); `state/` is gitignored. Passed.
  - `php -l`, `git diff --check`: passed.
- Notes:
  - Challenges are no longer tied to a browser. A captured challenge and signature can be used once by whoever submits first; over http that attacker could equally take the cookie, so no new exposure. Recorded for the concept entry.
  - An unknown or expired token resolves to signed out without clearing the cookie, so a stale response can never delete a newer cookie (the original race).
- Estimate details: original scope unchanged; retrospective 0.12 (store simpler than expected; concurrency check worked first time).

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Stage 2 - Shared signed-in test helper and LocalAppSmokeTest

**Original estimate: 0.10**

**Retrospective stage work: 0.10**

- Changes:
  - `tests/Support/TestViewerSession.php`: `signIn()`, `lobbyOnly()`, `signedOut()`, `challenge()`, `identityId()`, `nextRequest()`, `reset()`. Tests now say who is signed in; the helper decides how. For now it drives PHP sessions as the tests did.
  - `tests/LocalAppSmokeTest.php`: all session setup and teardown moved to the helper (24 insertions, 120 deletions). The two tests that check the PHP mechanism itself (`session_status()`, PHP cookie parameters) are left for Stage 4, where the mechanism changes.
- Verification:
  - `php tests/run.php LocalAppSmokeTest` on the branch and on `main` (`e6493e2f` worktree): both 162 run, 159 passed, 3 failed, with the same three failures (`testPrivateLobbyOnlyExposesLobbyAccountAndAuthenticationSurfaces`, `testPrivateForteRoutesRecoverExpiredSessionsInsteadOfReturningFalseNotFound`, `testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp`). Behavior-neutral. Passed.
  - `php -l`: passed.
- Notes:
  - Finding: the runner writes to STDOUT, so `headers_sent()` stays false and in-process tests do start real PHP sessions. Once the application has started one, `use_strict_mode` stays on for the whole process, and a self-chosen session ID is silently replaced. The old tests passed only because they never sent a cookie and the application reused the replacement ID PHP had generated, which is another way pre-filled sessions masked real behavior. The helper lets PHP generate the ID.
  - Baseline full suite still running at commit time; recorded in Stage 3.
- Estimate details: original scope unchanged; retrospective 0.10 (the strict-mode discovery cost what the mechanical replacement saved).

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Stage 3 - Remaining tests use the helper

**Original estimate: 0.10**

**Retrospective stage work: 0.10**

- Changes:
  - Converted 10 PHP test files and one browser test: `DeployPageTest`, `VisitorStatisticsPageTest`, `ApprovalSeedCommandTest` (child-process fixtures with real cookies); `BackupPageTest`, `FeatureFlagChangePageTest`, `SourceRouteCommitMapTest`, `RootApprovalPageTest`, `UserAccessTest`, `WriteApiSmokeTest`, `PrivateMessageFeatureFlagTest` (in-process fixtures); `tests/browser/testing_self_approval.mjs` (now gets its weak Lobby-only cookie from the new `tests/Support/test_viewer_session_cookie.php`).
  - Helper additions: `holdOpen()`, `useStateDirectory()`, `cookie()`. The child-process sign-in now empties `$_SESSION` after writing, so the application must load the session through the cookie.
  - No test outside `SessionRotationTest` (deleted in Stage 4) touches `$_SESSION` or `session_*` directly (grep).
- Verification:
  - Baseline full suite on `main` `e6493e2f` (detached worktree): 1825 run, 1810 passed, 15 failed. Failures: 8 `FrmClientTest`, 3 `LocalAppSmokeTest` (as in Stage 2), `ApprovalSeedCommandTest::testUnreadableArtifactDeletionCannotReportSuccess`, `ReactionRecordFormatParityTest::testMixedFormatsBuildTheSameReadModelAsLegacy`, `ReadModelBuilderTimingTest::testProgressSeparatesParsingDerivationAndWritingWithBoundedCounts`, `TerminalOperatorUiCommandTest::testNonInteractiveInputPrintsCliFallbackWithoutWork`.
  - The 10 converted classes on the branch: 235 run, 234 passed; per-class pass counts identical to the baseline; the one failure is the baseline `ApprovalSeedCommandTest` failure. Passed.
  - `node tests/browser/testing_self_approval.mjs` (headless Chrome via the declared `playwright-core` 1.58.1, installed with `--no-save --no-package-lock`; no manifest change): passed.
- Notes:
  - `holdOpen()` exists because the in-process fixtures opened one PHP session up front and changed it in memory, so later requests saw the change regardless of route. Switching them to cookie-loaded sessions now would expose routes outside the session allowlist (the Deploy class of bug) before Stage 4 can fix them. It is a named, temporary shim; Stage 4 removes it.
- Estimate details: original scope unchanged; retrospective 0.10.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Stage 4 - Cutover to app-owned sessions

**Original estimate: 0.25**

**Retrospective stage work: 0.25**

- Changes:
  - `ViewerSessions` (new, request-scoped): the one answer to "who is signed in" and the one place that signs in or out. `Application::handle()` creates it fresh for every request from that request's cookies; `RouteServices::viewerSessions()` exposes it to controllers.
  - Readers switched: `Application` (viewer, lobby, Access history `canSignIn`, lobby-gate diagnostics), `RouteServices::securityReviewAllowed`, `DeployController`, `UserAccessController`.
  - `AuthApiController`: challenges from the store; a challenge is used up only by a verified signature, so a failed attempt can retry; sign-in issues or reuses the session and sets the cookie. Response text unchanged.
  - `IdentityHintController::clearIdentity`: sign-out ends the session on the server and keeps the Lobby identity.
  - A request still carrying `PHPSESSID` gets it expired.
  - Removed: `SessionRotation`, the session-start route allowlist, `shouldResumeViewerSession`/`resumeViewerSession`/`hasViewerSessionCookie`, every PHP session call; `SessionRotationTest`, `session_rotation_http.mjs`. Static/docs checks no longer look at `$_SESSION` (cookie presence alone, as before).
  - Test helper now creates real store sessions and cookies (temporary store via `VIEWER_SESSION_DATABASE_PATH`, inherited by child processes); `holdOpen()` removed. Two `LocalAppSmokeTest` mechanism tests rewritten (no session for keyless readers; 400-day session lifetime); two new tests (idle three hours, stale `PHPSESSID`, http cookie refused over https, copied cookie dead after sign-out; challenge needs no session and survives a failed attempt).
  - `docs/reference/agent_api.md`: the session cookie now arrives at sign-in, not at the challenge. No shipped client (`frm.py`, scripts) relied on the old timing.
- Verification:
  - `grep` for `$_SESSION`, `session_*()` and `SessionRotation` in `src/` and `templates/`: none (comments updated). Passed.
  - Full suite: 1836 run, 1818 passed, 18 failed. All 15 baseline failures reproduced. Three new:
    - `WriteApiSmokeTest::testOperatorSecurityReview…` and `RootApprovalPageTest::testPrivateSeedPage…`: conversion mistakes in the tests (setting `$_COOKIE` after sign-in discarded the session cookie). Fixed by ordering; both classes rerun in full: 200/200 passed.
    - `CheckoutsCommandTest::testRealGitOperatorAcceptance`: no session or cookie involvement; passes alone (59 s). Load-induced timing under the full run. Not attributed.
  - `DeployPageTest` signed-in POST in a fresh single-request process (the 2026-11-02 failure): passed.
  - Browser (headless Chrome): `testing_self_approval.mjs` and `background_sign_in.mjs` passed; `access_history.mjs` fails identically on `main` (pre-existing "Temporary authentication failure" wait). 
- Notes:
  - Plan deviation: the split trigger fired (about 200 added source lines, 128 in the new `ViewerSessions`). Not split, because readers cannot move to the store before sign-in writes to it, so either half alone would leave sign-in broken between commits. Same outcome as planned.
  - `public/assets/private_site_auth.js` still says "PHP sessions hold one challenge"; its queueing is harmless. Left for the deferred client-simplification follow-up rather than changing a fingerprinted asset here.
- Estimate details: original scope unchanged; retrospective 0.25.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Stage 5 - Testing Approve cookie per scheme

**Original estimate: 0.07**

**Retrospective stage work: 0.05**

- Changes:
  - `RouteServices::testingSelfApprovalCookieName()`: `testing_self_approval` over http, `__Host-testing_self_approval` (Secure) over https. `testingSelfApprovalToken()` and the `/api/test_self_approval` check read and set the scheme's own name. The browser script is unchanged (the cookie is HttpOnly and only checked on the server).
  - `TestingSelfApprovalTest::testApprovalCookieNameDiffersPerScheme`.
- Verification:
  - `php tests/run.php TestingSelfApprovalTest`: 2/2 passed.
  - `node tests/browser/testing_self_approval.mjs` (headless Chrome, http): passed.
- Notes: over https the double-submit token is now a `__Host-` cookie, which also stops subdomains from planting it.
- Estimate details: original scope unchanged; retrospective 0.05.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Stage 6 - Replay of the reported failures over http and https

**Original estimate: 0.15**

**Retrospective stage work: 0.15**

- Changes:
  - `tests/browser/app_sessions_http_https.mjs`: headless Chrome against one `php -S` served over http and, through a local TLS proxy (Node's built-in `https`, self-signed certificate from `openssl`), over https on the same host and different ports, so the browser shares cookies between them like the iPhone did. Uses the made-up host `app.test` mapped to 127.0.0.1, because Chrome treats `localhost`/`127.0.0.1` as secure and would send Secure cookies over http, hiding the bug; Chrome runs with `--no-proxy-server` because this environment's outbound proxy settings would otherwise capture `https://app.test`.
  - `tests/Support/app_sessions_browser_router.php`: marks proxied requests as https, then defers to the existing invite router. PHP's own file-session cleanup is set to 2 seconds, so "idle past cleanup" takes seconds; app-owned sessions ignore it.
  - Seven checks: https sign-in; http sign-in after an https visit; still signed in on reload; a browser holding a Secure `PHPSESSID` signs in over http; an https visit retires that `PHPSESSID`; operator Deploy POST after idle cleanup (over https, where every version signs in, so a refusal can only mean a lost session); a copied cookie is dead after sign-out.
- Verification:
  - On `main` `e6493e2f` (files copied into the detached worktree): 1 of 7 passed. http sign-in after https and the old-`PHPSESSID` browser failed with the server's reply "error=Authentication challenge is missing or expired." (the iPhone error, twice: first try and the client's retry); Deploy after idle cleanup refused with 403 "Deploying requires a signed-in root-approved operator."; the remaining failures follow from these or are new behavior. The replay reproduces the reported failures. Passed.
  - On the branch: 7 of 7 passed, twice. Passed.
- Notes:
  - Chromium evidence only. The real iPhone Safari check over http after an https visit remains the acceptance item for the maintainer (Stage 7 records the steps).
  - Harness lessons kept in the test's header comment: loopback hosts hide Secure-cookie shadowing; Playwright's `context.request` resolves names in Node, so checks fetch from inside a page.
- Estimate details: original scope unchanged; retrospective 0.15 (proxy and loopback issues cost what was budgeted as uncertainty).

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Stage 7 - Documentation, concept entry and closeout

**Original estimate: 0.08**

**Retrospective stage work: 0.08**

- Changes:
  - `docs/reference/authentication_modes.md`: session section rewritten for app-owned sessions (lifetime, per-scheme cookies, challenges, sign-out, switching from PHP sessions); evidence links point to the new sources and tests.
  - Superseded notes on `docs/plans/session_reauthentication_reference.md` and `docs/plans/session_rotation_race_decision.md` (the 30-second forwarding decision carries over inside the store).
  - New concept entry `docs/concepts/viewer_session.md`.
  - Study ledger closeout for `v3/app_sessions`.
- Verification:
  - Concept entry and reference links: every local link target resolves. Passed.
  - Final full suite: 1839 run, 1824 passed, 15 failed, exactly the 15 baseline failures; no new failures, none fixed. Passed.
  - Dependency delta: zero. No manifest or lock changes; `playwright-core` 1.58.1 was already declared and was installed locally with `--no-save --no-package-lock` only to run browser tests. Passed.
- Estimate details: original scope unchanged; retrospective 0.08.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Closeout

**Retrospective cycle scope: 0.95**

- Delivered: Option A as approved. Every request resolves "who is signed in" from its scheme's cookie through one store; no PHP session code, route list or rotation workaround remains; sign-out ends sessions on the server; the reported failures replay and pass.
- Commits: planning `eafa09b5`; stages `435d1a61`, `414a2bd6`, `8959db42`, `9c1b9ad0`, `78ed137d`, `eebd9fd3`, and this Stage 7 commit. 1 + 7 commits, as required.
- Checks:
  - Passed: store unit tests (14), cutover tests, full suite against baseline, signed-in Deploy POST in a fresh process, http/https replay in Chrome (7/7; fails 6/7 on `main` with the reported errors), testing Approve and background sign-in browser tests.
  - Failed on `main` and branch alike (pre-existing): `access_history.mjs` ("Temporary authentication failure" wait); the 15 baseline suite failures.
  - Pending: real iPhone Safari check (steps below); production deployment; maintainer comfortable-fit judgment.
  - Not checked: other browser tests not touched by this cycle; Apache (`tests/*_apache_test.py`) runs; production PHP/FastCGI.
- Deviations: Stage 4 not split despite its trigger (a split would leave sign-in broken between commits); Stage 3 used a temporary `holdOpen()` test shim, removed in Stage 4. No new requests; no splits or follow-up cycles created.
- Follow-ups (separate work, recorded in Step 1): Deploy page Sign in button; remove the lobby-gate diagnostic log; simplify the client challenge queue and its "PHP sessions hold one challenge" comment; bring `feature/operator-privilege-delegation` up to date (its session-resume commit `9f3f9563` is now unnecessary) and move `d7f14336`'s revoked-key check into `ViewerSessions` if it merges.
- Release note: deploying this signs every browser out once. Browsers with a saved key sign back in on their next page load; an operator's first Deploy or other POST after the switch may need one page load first.

### iPhone Safari acceptance (maintainer)

1. On the iPhone that failed, open the site over https and confirm Account shows you signed in.
2. Open the same site over http (same host name). Account should show you signed in within a few seconds, without "Authentication challenge is missing or expired".
3. Reload over http; still signed in. Then reopen over https; still signed in.
4. Optional, an operator account: after an idle evening, use Tools › Deploy › Check for updates over either scheme without signing in again.

Record the result here (passed, or what you saw); until then this item stays pending.

### Study closeout

- Entry 0.95 reaffirmed; stage originals 0.15/0.10/0.10/0.25/0.07/0.15/0.08; stage retrospectives 0.12/0.10/0.10/0.25/0.05/0.15/0.08; not summed. Retrospective cycle scope 0.95, judged separately by the same exposed rater (Claude, claude-opus-5-5). R1 hashes re-verified at closeout. No measured labor. Maintainer comfortable-fit judgment and acceptance pending; no accepted-outcome increment.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Merge with main for review - 2026-11-03

User authorization: "yes please <3" (push the branch and open a pull request). `main` had moved 22 commits to `fd4d0e02`, including key retirement. Merged `main` into the branch (no rebase; stage commits unchanged).

- Conflicts resolved:
  - `AuthApiController`: kept both. The challenge is used up first, then `withActiveIdentity()` (main's publication lock and retired-key check) wraps the app-owned `signIn()`, so a retired key still gets main's 403 and no session.
  - `RouteServices::securityReviewAllowed`: app-owned session reader plus main's `isActiveOperator`.
  - Study ledger: both cycles' appended records kept.
- Adapted main's two new retirement tests in `WriteApiSmokeTest`, which pre-filled `$_SESSION`: they now sign in through the helper. The retired-key sign-in check asserts that no session row was added (new `TestViewerSession::sessionCount()`), and an existing session from before retirement is shown to stop working.
- No `$_SESSION` or PHP session call came back in from `main` (grep `src/` and `tests/`).
- Verification on the merged tree:
  - Focused: `ViewerSessionStoreTest TestingSelfApprovalTest RootApprovalPageTest UserAccessTest DeployPageTest ApprovedUserKeyResolverTest PrivateMessageHistorySyncTest` 63/63 passed; `WriteApiSmokeTest LocalAppSmokeTest` 363 run, 360 passed, the 3 long-standing `LocalAppSmokeTest` failures.
  - Browser (headless Chrome): `key_retirement.mjs` (main's new journey), `app_sessions_http_https.mjs` 7/7, `background_sign_in.mjs` passed.
  - Full suite on the merged tree (after push): 1847 run, 1832 passed, 15 failed; exactly the 15 failures of the original `main` baseline, none new.
- Study: merge integration only; scores, retrospectives and acceptance count unchanged.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*
