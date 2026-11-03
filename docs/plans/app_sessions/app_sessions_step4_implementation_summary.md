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
