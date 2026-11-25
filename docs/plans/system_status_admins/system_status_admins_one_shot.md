Cycle: `v3/system_status_admins`; Mode: one-shot; Record/index: [plans index](../README.md).

Debug mode: enabled

Effort study: `railspike-effort` / `v3/system_status_admins`; host ledger [system_status_admins.md](../effort_ledger/system_status_admins.md). Standing P2 enrollment.

Extended estimates: enabled. No alternatives or work units, so no unit estimates.

**Cycle estimate: 0.05**

Status: planned; next permitted action: implementation.

Original request: the maintainer shared screenshots of the MIT Rap Club System status page (/tools/codebase/). Its Instance card lists 18 admins, but the query shown under the list, run in the query viewer, returns one row (`ilyag`). "can you please help me understand the discrepancy", then "can you please review this in the codebase and prepare to make a pr", then "please pr".

Authorization: "please pr" on 2026-11-25, answering a proposal that named the fix (Admin means approved directly by `root`, matching the shown query and /instance/), the test, and the PR. One-shot, because the outcome and its limits are clear. Exclusions: no change to the shown query, /instance/, the user directory or approval data.

Outcome and work type: application change. The System status page's "Admin" line lists the profiles approved by `root`, the same rows its shown query returns and the same list /instance/ shows as "Admins". Approved users who were approved by someone else (production: `claude`, `guest`, the agents) no longer appear there.

Approach and risks:
- Cause: `CodebaseStateController::render()` passes `ProfileRepository::approvedDirectoryUsers()` (every approved user, ordered by threads and posts, the /users/ directory list) as `admins`, while the template shows the `approved_by_label = 'root'` query. The mismatch dates from the code's first commit in this repository (`c93ba5f1`).
- Fix: pass `ToolsPageSupport::fetchSeedApprovedUsers()`, the function /instance/ already uses and whose SQL matches the shown query exactly.
- **Risk:** production's Admin line shrinks from 18 names to one. Impact: intended; it now agrees with the query and /instance/. No other mitigation needed.

Reuse decisions: reuse `ToolsPageSupport::fetchSeedApprovedUsers()` (src/ForumRewrite/Tools/ToolsPageSupport.php) instead of a new query; `templates/pages/codebase_state.php` and `partials/query_action.php` unchanged; `ProfileRepository::approvedDirectoryUsers()` stays for /users/ and its other callers.

Completion conditions: on /tools/codebase/, with a user approved by someone other than `root`, the Admin line lists only root-approved users, the same names as /instance/'s Admins line, and the shown query returns the same rows. Deploy with Deploy now; no schema change.

Concepts touched: none.

Verification and recovery plan: new `LocalAppSmokeTest` test that adds an approved profile with `approved_by_label = 'guest'` and many threads, then checks /tools/codebase/ and /instance/ list the same admins and leave it out; it must fail before the fix. Run `./v3 test --smoke LocalAppSmokeTest PresentationProfileMatrixTest`. Not run: the full suite (the maintainer runs it). Production replay: after deploy, the MIT Rap Club page should show `ilyag` alone, matching the query viewer. Recovery: revert the one-line change.

Starting revisions and branch: `fix/system-status-admins` from `origin/main` `0431fc93`.

Planning commit: pending.

Study history:
- 2026-11-25 UTC, intake and execution entry: **0.05**, before implementation, after reading both controllers and reproducing the mismatch on the test fixture. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified.

Dated execution history:
- 2026-11-25: reproduced on `parity_minimal_v1` with an extra profile approved by `guest`: /instance/ shows `guest`; /tools/codebase/ shows `helper , guest`.

_Claude · 2026-11-25 UTC · Model: claude-opus-5-5_
