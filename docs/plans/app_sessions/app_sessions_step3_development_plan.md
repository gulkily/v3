> **Feature plan:** [Step 1](./app_sessions_step1_solution_assessment.md) · [Step 2](./app_sessions_step2_feature_description.md) · [Step 3](./app_sessions_step3_development_plan.md) · [Step 4](./app_sessions_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./app_sessions_debug_log.md))
Effort study: `railspike-effort` / `v3/app_sessions` ([ledger](../effort_estimation_trial.md#cycle-v3app_sessions)); standing enrollment, protocol P2
Extended estimates: enabled

**Cycle estimate: 0.95**

Status: Approved Step 3 (2026-11-03T02:37Z, maintainer: "Approved step 3."). Implementation on `feat/app-sessions`.

## Completion Contract

- Normal entry: sign in from Account, Lobby, Access history or the resume page, over http or https.
- Outcome: the identity is recognized on every cookie-bearing request (including Deploy POSTs) for 400 days; sign-out ends it on the server; same-identity sign-in reuses the session.
- Recovery: an old `PHPSESSID` is expired on first contact and a browser with a saved key signs back in silently; operators use Access history sign-in.
- External verification: real iPhone Safari over http after an https visit (maintainer); recorded as pending if not done before closeout.
- Release: Stages 1–7 committed; focused suites pass; full suite failures match the `main` baseline; HTTP round-trip and shadowing replay pass; zero dependency delta.

## Key Risks

- **High risk:** cutover signs everyone out at once. Impact: every member re-signs on next page load; cold POSTs fail once. Validate in Stage 6 with a browser holding a legacy cookie. Mitigation: rely on existing silent sign-in; say so in release notes.
- Test conversion volume (12 files, about 120 sites). Validate in Stage 2 by converting the largest file first. Mitigation: behavior-neutral helper before the cutover, so only the helper changes in Stage 4.
- SQLite contention from per-request writes. Validate in Stage 1 with concurrent requests. Mitigation: no write on read; "last seen" updated at most once per interval.
- Session store reaching exports or backups. Validate in Stage 1 against archive, backup and checkout inputs. Mitigation: `state/private/` (gitignored, not exported); store only token hashes.
- Stage 4 size (largest stage, mostly deletions). Validate by diff size before commit. Mitigation: if over about 150 changed non-deletion lines, split readers from sign-in before committing.

## Stage 1

**Estimate: 0.15**

- Goal: a tested viewer session store, resolver and per-scheme cookie, not yet wired.
- Dependencies: none.
- Expected changes: `ViewerSessionStore` at `state/private/viewer_sessions.sqlite3` (path override registered in `PrivateConfigSchema`, not in the template): sessions (token hash, identity, lobby identity, scheme, created, last seen, expires) and single-use challenges; `issue(identity, scheme)`, `resolve(token, scheme)`, `signOut(token)`, `createChallenge()`, `consumeChallenge(value)`, `purgeExpired()`; injectable clock. `ViewerSessionCookie`: `__Host-v3_session` (https, Secure) and `v3_session` (http); https ignores the http cookie.
- Verification approach: unit tests for issue/resolve/expiry/sign-out/same-identity reuse/single-use challenges/scheme isolation; concurrent-request check; confirm archive, backup and checkout code never read `state/private/viewer_sessions*`.
- Estimate details: driver: new store plus cookie rules; uncertainty: contention behavior 0.10–0.20.
- Risks or open questions:
  - Impact: a wrong cookie rule repeats the shadowing bug. Early validation: scheme-isolation tests. Mitigation: names differ per scheme by construction.
- Canonical components/API contracts touched: `PrivateConfigSchema` (new path setting); existing `*DatabaseConfig` pattern reused.

## Stage 2

**Estimate: 0.10**

- Goal: one shared signed-in test helper; `LocalAppSmokeTest` uses it with no behavior change.
- Dependencies: none (may run before Stage 1).
- Expected changes: `tests/Support` helper `signedInAs(identity)` / `signedOut()` that still drives the current PHP session; replace the 49 direct `$_SESSION` sites in `LocalAppSmokeTest`.
- Verification approach: `LocalAppSmokeTest` result identical to baseline.
- Estimate details: driver: mechanical replacement; uncertainty: child-script setups 0.07–0.15.
- Risks or open questions:
  - Impact: the pilot shows conversion is not mechanical. Early validation: this stage. Mitigation: rescope Stage 3 before continuing.
- Canonical components/API contracts touched: test support only.

## Stage 3

**Estimate: 0.10**

- Goal: every other test uses the helper; no test touches `$_SESSION` or `session_*` except rotation tests due for deletion.
- Dependencies: Stage 2.
- Expected changes: the remaining 11 PHP test files and `tests/browser/testing_self_approval.mjs` fixtures.
- Verification approach: each converted file's result identical to baseline; grep shows no remaining direct session setup.
- Estimate details: driver: 11 files of varied setup; uncertainty: child-process fixtures 0.07–0.15.
- Risks or open questions: none beyond Stage 2's.
- Canonical components/API contracts touched: test support only.

## Stage 4

**Estimate: 0.25**

- Goal: cutover; the app uses only the new store.
- Dependencies: Stages 1–3.
- Expected changes: resolver runs once at request start for any cookie-bearing request; `viewerIdentityId()` / `lobbyIdentityId()` replace every `$_SESSION` read (`Application`, `RouteServices`, `DeployController`, `UserAccessController`, `IdentityHintController`); `AuthApiController` uses store challenges, issues or reuses sessions and keeps response text unchanged; sign-out deletes the row and keeps the lobby identity; an incoming `PHPSESSID` is expired. Removed: `SessionRotation`, start allowlist, `shouldResumeViewerSession`/`resumeViewerSession`, all PHP session calls, `SessionRotationTest`, `session_rotation_http.mjs`. The Stage 2 helper switches to issuing real store sessions and cookies.
- Verification approach: full PHP suite vs baseline; grep proves no `session_` or `$_SESSION` in `src/`; tests for Deploy POST signed in, idle past 24 minutes (clock), sign-out invalidating a copied cookie, same-identity reuse.
- Estimate details: driver: many call sites in one switch; uncertainty: lobby and sign-out interplay 0.20–0.35. Templates and `public/*.php` have no session reads (checked 2026-11-03).
- Risks or open questions:
  - Impact: a missed reader signs someone out. Early validation: grep `src/` before editing. Mitigation: one accessor; grep gate in verification.
  - Impact: static pages turn dynamic if a cookie is set for keyless readers. Early validation: static/high-traffic tests. Mitigation: cookies only at sign-in.
- Canonical components/API contracts touched: `/api/auth_challenge`, `/api/authenticate_identity`, `/api/auth_status`, `/api/clear_identity` (responses unchanged); static publication cookie checks (unchanged).

## Stage 5

**Estimate: 0.07**

- Goal: the testing Approve cookie no longer shadows across schemes.
- Dependencies: Stage 1 cookie rules.
- Expected changes: per-scheme name for the testing self-approval cookie, same rule as the session cookie.
- Verification approach: testing self-approval PHP and browser tests; scheme-isolation unit test.
- Estimate details: driver: small; uncertainty: browser test fixtures 0.05–0.10.
- Risks or open questions: none.
- Canonical components/API contracts touched: `RouteServices::testingSelfApprovalToken`, `testing_self_approval.js` (unchanged).

## Stage 6

**Estimate: 0.15**

- Goal: replay the reported failures through real browsers and cookies.
- Dependencies: Stages 4–5.
- Expected changes: an HTTP test serving the app over http and over https (self-signed, same host, different ports, so cookies are shared as on the iPhone) using `php -S` and Node's built-in TLS; headless Chrome flows: https sign-in, then http sign-in succeeds and persists; browser with a legacy Secure `PHPSESSID` signs back in silently; Deploy POST after a long idle; sign-out then reuse of the old cookie fails.
- Verification approach: the replay passes and fails on `main` for the shadowing and idle cases (proves the replay is faithful).
- Estimate details: driver: TLS harness with no new dependency; uncertainty: Chrome certificate flags 0.10–0.25.
- Risks or open questions:
  - Impact: harness cannot reproduce shadowing. Early validation: run it on `main` first. Mitigation: record as unverified in Chromium; iPhone check remains the acceptance evidence either way.
- Canonical components/API contracts touched: `private_site_auth.js` background sign-in and resume page (exercised, unchanged).

## Stage 7

**Estimate: 0.08**

- Goal: documentation, concept entry and closeout.
- Dependencies: Stages 1–6.
- Expected changes: `docs/reference/authentication_modes.md` session section; status notes on `session_reauthentication_reference.md` and `session_rotation_race_decision.md`; new `docs/concepts/viewer_session.md`; iPhone check steps for the maintainer; closeout in the Step 4 summary and ledger.
- Verification approach: concept paths checked against code; links resolve; full suite compared to baseline; dependency delta zero.
- Estimate details: driver: docs; uncertainty: low 0.05–0.10.
- Risks or open questions: none.
- Canonical components/API contracts touched: none.

## Concepts touched

- Viewer session (sign-in): created in Stage 7 (`docs/concepts/viewer_session.md`).
- [Static publication](../../concepts/static_publication.md): used unchanged; verified in Stage 4.

## Execution and study checkpoint

- Step 3 handoff 2026-11-03 UTC: whole-cycle **0.95**, revised from 0.90 (new understanding, same scope v1): tests need a behavior-neutral pre-pass and the replay needs a TLS harness. Under 1.0, so no split proposed. Stage work S1–S7 originals 0.15/0.10/0.10/0.25/0.07/0.15/0.08, independent annotations, not summed.
- Baseline `e6493e2ff4869f8bf98e5f43b3f6388814742c26`; R1 hashes verified at Step 2; P2. Claude (claude-opus-5-5), single exposed rater. No implementation, measured labor or accepted outcome. Awaiting Approved Step 3; records uncommitted.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*
