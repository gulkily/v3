> **Feature plan:** [Step 1](./app_sessions_step1_solution_assessment.md) · [Step 2](./app_sessions_step2_feature_description.md) · [Step 3](./app_sessions_step3_development_plan.md) · [Step 4](./app_sessions_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./app_sessions_debug_log.md))
Effort study: `railspike-effort` / `v3/app_sessions` ([ledger](../effort_estimation_trial.md#cycle-v3app_sessions)); standing enrollment, protocol P2
Extended estimates: enabled

**Cycle estimate: 0.90**

Status: Approved Step 2 (2026-11-03T02:26Z, maintainer: "Approved step 2.").

## Problem

Sign-in state lives in PHP session files, ini defaults and a cookie the browser may refuse, so members and operators are silently signed out, or never signed in, in ways that look different each time.

## User stories

- As an operator, I want to stay signed in until I sign out so that Deploy and other operator tools work after an idle evening.
- As a member on an iPhone, I want sign-in to work over http after visiting over https so that the site behaves the same on either address.
- As a returning member with a saved key, I want the switch to new sessions to sign me back in silently so that I never notice it.
- As a member who signs out, I want that session to end on the server at once so that a copied cookie stops working.
- As a contributor, I want tests to sign in through the real cookie so that a passing test means sign-in actually works.

## Core requirements

- One answer to "who is signed in" for every request carrying the session cookie, with no route list. Keyless readers get no cookie, so static pages stay static.
- A session lasts its stated lifetime (400 days, as today) unless signed out. Sign-out ends it on the server immediately. Signing in again as the same identity keeps the existing session. A request in flight during sign-in or sign-out never signs the browser back in or out by accident.
- Sign-in behaves the same over http and https without forcing https: each scheme has its own cookie, and an http cookie never authorizes an https request. The testing Approve cookie gets the same treatment.
- No PHP session functions remain. An existing `PHPSESSID` is expired on first contact; browsers with a saved key sign back in through the existing background sign-in and resume page; cold POST/API requests get today's signed-out response.
- The existing sign-in API responses stay byte-compatible for browsers and agents. Server-side workarounds listed in Step 1 (rotation forwarding, start allowlist/resume, `$_SESSION`-prefilled tests) are removed.

## Delivery scope

- Work type: application change, with tests and documentation.
- Documentation: this cycle's plans and debug log, the study ledger, `docs/reference/authentication_modes.md`, status notes on `session_reauthentication_reference.md` and `session_rotation_race_decision.md`, and a new concept entry.
- Dependency delta: zero (PHP's built-in SQLite and random functions).
- Out of scope (separate work): Deploy page Sign in button, lobby-gate diagnostic log removal, client-side challenge-queue simplification, operator-delegation reintegration, "sign out other browsers" UI.

## Completion boundary

- Normal entry: sign in from Account, Lobby, Access history or the resume page, over http or https.
- Outcome: the signed-in identity is recognized on every route, including Deploy POSTs, for the session lifetime; sign-out ends it.
- Recovery: after the cutover or a cleared cookie, a browser with a saved key signs back in silently on its next page load; operators use the existing Access history sign-in.
- Release: focused and full suites run with failures compared to baseline; real cookie round trips pass over http and https; the iPhone check below is done or explicitly recorded as pending.

## Known user and environment

- **iPhone Safari over http, holding an https-era Secure `PHPSESSID`.** Entry: open the site over http and sign in. Next action without help: none needed; if a sign-in fails, Account's sign-in retries. Verify: sign in, reload, and see the signed-in account over http, then over https. Evidence: a real iPhone Safari check by the maintainer; a Chromium replay of the shadowing condition is supporting evidence only.
- **Operator idle past 24 minutes.** Entry: Tools › Deploy after a long idle. Verify: Deploy POST succeeds without signing in again. Evidence: a test with a session idle well past PHP's old default.

## Risks

- **Test conversion volume** (12 files, 49 sites in `LocalAppSmokeTest`). Impact: slice overruns. Validate first by converting one file with a shared signed-in-request helper. Mitigation: mechanical helper; split if the pilot shows otherwise.
- **SQLite write contention.** Impact: slow or failed requests if every request writes "last seen". Validate with a concurrent-request check early. Mitigation: avoid a write per request.
- **Session store leaking into exports or backups.** Impact: tokens leave the server. Validate by checking archive/backup inputs before building. Mitigation: store only token hashes, outside exported paths.
- **Unmerged branches adding session reads** (operator delegation, revoked-key sessions). Impact: merge conflicts later. Mitigation: one resolver to call; note both branches in the closeout.

## Shared component inventory

- Sign-in APIs (`/api/auth_challenge`, `/api/authenticate_identity`, `/api/auth_status`, `/api/clear_identity`): reuse; same responses, new storage.
- Browser sign-in `private_site_auth.js`, resume page, Access history sign-in partial, `auth_navigation.js`, `member_approval.js`, Messages access: reuse unchanged.
- Viewer lookups in `Application`, `RouteServices`, `DeployController`, `UserAccessController`, `IdentityHintController`: switch to the shared resolver.
- Static/high-traffic and docs cookie checks: reuse unchanged; they keep working because keyless readers get no cookie.
- Testing Approve cookie, `identity_hint` cookie: reuse; Approve cookie gains per-scheme naming.
- New: session store and resolver. Nothing existing holds app-owned session state.

## User flow

1. A member with a saved key opens any page over http or https.
2. The browser signs in through the existing flow; the server issues a session cookie for that scheme.
3. Every later request with that cookie is recognized, on any route.
4. Sign-out ends the session on the server and clears the cookie.
5. A browser still holding an old `PHPSESSID` loses it on its first request and signs back in silently.

## Success criteria

- No PHP session function or `$_SESSION` use remains in `src/`; no session route list remains.
- Tests drive real cookie round trips; none pre-fill session state.
- Signed-in state survives a simulated idle longer than PHP's old 24-minute default and lasts to the stated lifetime.
- Sign-in works over http after an https visit (Chromium replay of the shadowing case; iPhone check recorded).
- Sign-out makes a copied cookie unusable on the next request.
- Signing in repeatedly as the same identity does not grow the session store per visit.

## Concepts touched

- Viewer session (sign-in): **created** as a new entry in `docs/concepts/`.
- [Static publication](../../concepts/static_publication.md): used unchanged.

## Enhancement requests

- Step 1 workaround inventory (2026-11-03): recorded in Step 1; server-side items in scope here, client items deferred.

## Approval and study checkpoint

- Step 2 handoff 2026-11-03T02:08:04Z: whole-cycle **0.90 reaffirmed** for scope v1/Option A. Requirements match the Step 1 recommendation; the explicit iPhone acceptance condition was already implied.
- Baseline `e6493e2ff4869f8bf98e5f43b3f6388814742c26`; R1 (all three hashes re-verified); P2. Claude (claude-opus-5-5), single exposed rater. No implementation, measured labor or accepted outcome. Awaiting Approved Step 2; records uncommitted.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*
