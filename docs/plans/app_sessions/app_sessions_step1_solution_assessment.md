> **Feature plan:** [Step 1](./app_sessions_step1_solution_assessment.md) · [Step 2](./app_sessions_step2_feature_description.md) · [Step 3](./app_sessions_step3_development_plan.md) · [Step 4](./app_sessions_step4_implementation_summary.md)

Debug mode: enabled (project marker `.railspike_debug_on` present; [debug log](./app_sessions_debug_log.md))
Effort study: `railspike-effort` / `v3/app_sessions` ([ledger](../effort_estimation_trial.md#cycle-v3app_sessions)); standing enrollment, protocol P2
Extended estimates: enabled

**Cycle estimate: 0.90**

## Original Query

Repo: github.com/gulkily/v3 (the ZenMemes forum). Clone it and read AGENTS.md, PRINCIPLES.md and CONTRIBUTING.md first.

Task: start a Railspike cycle to replace PHP's built-in session management. Write the Step 1 solution assessment only, then stop and wait for "Approved Step 1".

Source material: the todo.txt entry "replace PHP's built-in session management (flaky, causes many hard-to-diagnose issues)". It lists the failures we hit on 2026-11-02, the three session keys in use (authenticated_identity_id, forum_auth_challenges, lobby_identity_id), the files that read or write them, options A/B/C, constraints and success criteria. Treat it as an outline, verify it against the current code, and correct anything that's wrong.

Background on the failures:
- POST /tools/deploy/ ran with no session, because Application::handle() only starts sessions for an allowlist of paths.
- An operator's sign-in lapsed after an idle period while the cookie still had ~400 days left (PHP's server-side session GC).
- On iPhone Safari over http://, sign-in failed with "Authentication challenge is missing or expired". A Secure PHPSESSID set earlier over https blocked http from storing a new cookie. The server was fine; a curl round trip worked over both http and https.
- The earlier sign-in rotation race led to SessionRotation's forwarding workaround (docs/plans/session_rotation_race_decision.md).
- A test pre-filled $_SESSION and hid a real bug, because $_SESSION stays populated after session_write_close().

What I want from Step 1:
- Compare app-owned server sessions (SQLite), a stateless signed cookie, and hardened PHP sessions, plus sensible combinations.
- Cover revocation, migration of existing sessions (browsers holding a key should sign back in silently), and whether to force https site-wide.
- Recommend one option as a releasable slice.
- Note related small fixes separately, e.g. a Sign in button on the Deploy page reusing the Access history sign-in.

Follow the repo's Railspike rules: standing effort-study enrollment (protocol P2, verify the R1 hashes), the debug log (.railspike_debug_on is present), extended estimates, and the concept inventory. Keep planning docs uncommitted until Step 3 is approved.

## Problem

"Who is signed in" lives in PHP session files, ini defaults and a cookie the browser may refuse, so sign-in silently drops or never sticks and each failure looks different.

## Outline corrections (verified at `e6493e2f`)

- **Deploy:** `/tools/deploy` joined the start allowlist in `cdfa00dc`. The cause remains: other POSTs and all `/api/` paths outside the list run sessionless; resume covers only cookie-bearing GET pages plus `POST /api/prepare_invitation`.
- **Inventory:** `ProfileRepository` and `LlmExchangesController` only mention sessions in comments. Missing: `IdentityHintController::clearIdentity` (sign-out writes `lobby_identity_id`), `Application` (`lobbyViewerProfile`, security-review `canSignIn`, lobby-gate diagnostic log), SessionRotation's `_session_replaced_at/_by` keys, and any-cookie/any-session checks in `DocsPublication`, `DocsPageRenderer` and `StaticArtifactBuilder` that keep static pages static. 13 test files touch `$_SESSION`.
- **Expiry:** no `gc_maxlifetime` or `save_path` is set anywhere, so host defaults (24-minute idle) apply.
- **Option C's "redirect http to https" conflicts with PRINCIPLES** ("instances serve HTTP alongside HTTPS" for old clients).
- **Shadowing is not PHP-specific:** any cookie reused across schemes hits it, so every option needs a scheme-specific cookie name.
- **Prior decision:** [session_reauthentication_reference.md](../session_reauthentication_reference.md) made the saved browser key the durable sign-in authority and deferred app-owned sessions "if PHP lifecycle control remains inadequate". This cycle is that trigger.

## Option A — App-owned sessions in SQLite

**Estimate: 0.90**

A random token cookie maps (by hash) to a row in a new `state/` SQLite store (not the disposable read model): identity, lobby identity, created, last seen, expires. Challenges become single-use rows, so signing in needs no prior session. One resolver runs for every request that carries the cookie; no allowlist, no PHP session calls.

- Pro: explicit lifetime; sign-out and "sign out other browsers" delete rows immediately; inspectable with `sqlite3`.
- Pro: no pre-sign-in session to rotate, so SessionRotation and its race go away; no per-browser file locks.
- Pro: matches the existing SQLite stores and the 2026-10-13 recorded direction.
- Con: one DB read per cookie-bearing request; a new store needs schema/recovery notes (deleting it only signs people out).
- Driver: ~12 call sites and 13 tests move to one resolver; range 0.80–1.05.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Option B — Stateless signed cookie

**Estimate: 0.80**

Cookie carries identity + expiry, HMAC-signed with an auto-generated secret in `state/` (pattern: `PrivateMessageReadState`). Challenges still need a single-use store to stop replay.

- Pro: no per-request lookup; nothing to garbage-collect for identity.
- Con: sign-out only clears that browser; a copied cookie (likely over http) works until expiry unless a deny list or per-identity generation table is added, which reintroduces A's lookup.
- Con: lobby identity and future per-session state must fit in the cookie; secret rotation signs everyone out.
- Driver: challenge store + secret lifecycle; range 0.70–0.95.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Option C — Hardened PHP sessions

**Estimate: 0.45**

Start/resume in one place for every cookie-bearing request, own `save_path` under `state/`, `gc_maxlifetime` equal to the cookie lifetime, scheme-specific session names. No https redirect (see corrections).

- Pro: smallest change; keeps current tests and flows.
- Con: keeps SessionRotation, file locks, ini coupling, the `$_SESSION` test trap and 400-day files with no listing or revocation.
- Driver: start-path consolidation; range 0.35–0.60.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Combinations considered

- **B identity + A-style revocation table:** needs the lookup anyway; converges on A with more moving parts. Not recommended.
- **C now, A later:** C fixes today's three failures cheaply, but its work is thrown away by A. Choose only if A must wait.

## Cross-cutting decisions

- **https:** do not force site-wide. Use `__Host-` + Secure cookie over https and a separate plain cookie over http; https ignores the http cookie so an http token never authorizes https.
- **Revocation:** approval and identity removal are already read from the current profile per request in every option. A adds immediate sign-out of any session.
- **Migration:** do not read PHP session files. On first contact, expire `PHPSESSID`; the existing background sign-in (`private_site_auth.js`) and resume page re-sign browsers holding a key. Cold POST/API requests get the ordinary signed-out response.
- **Static pages:** cookies are set only on sign-in, so keyless readers keep static delivery.

## Recommendation

**Option A** as one releasable slice: normal entry is signing in on Account, Lobby or Access history over http or https; outcome is staying signed in for the cookie lifetime on every route, including Deploy POSTs; recovery is silent re-sign-in after the PHPSESSID cutover. Tests drive real cookie round trips. Fits about six stages, near the 1.0 ceiling; if Step 2 adds "sign out other browsers" UI, split it off.

## Related small fixes (separate work)

- Deploy page Sign in button reusing the Access history sign-in (todo).
- Remove the lobby-gate diagnostic log once A lands (its open question is session loss).
- Update `docs/reference/authentication_modes.md` and the reauthentication reference (part of A's documentation, not separate).

## Concepts touched

- Viewer session (sign-in): **proposed new entry** (none exists in `docs/concepts/`), all options.
- [Static publication](../../concepts/static_publication.md): used unchanged (cookie-absent static delivery must hold).
- [Deployment configuration](../../concepts/deployment_configuration.md): used unchanged by B only if the secret moved to private config; not needed for A.

## Session workaround inventory

Verified on `main` at `e6493e2f`. "Remove" items go away with Option A in this slice; "Simplify" items are follow-ups so the slice stays under 1.0.

| Workaround (commit) | Why it exists | Under A |
| --- | --- | --- |
| `SessionRotation` 30-second forwarding, strict-mode toggle (`05e43912`) | Old session ID deleted mid-flight at sign-in | Remove: no pre-sign-in session to rotate |
| Start allowlist + `shouldResumeViewerSession`/`resumeViewerSession`, cookie deletion on unknown ID (`b9a89400`, `5de6d3f6`, `cdfa00dc`) | Avoid creating sessions on every request | Remove: one resolver, cookie-gated |
| QDB routes added to `isApplicationRoute` so resume runs (`188917e6`, reload loop) | Resume tied to a route list | Remove with the resolver |
| Testing Approve token moved to a double-submit cookie (`c62f587c`) | Session changes lost the token | Keep the cookie; it shares the Secure-over-http shadowing bug, so give it the per-scheme name |
| Lobby-gate diagnostic log (`Application::logLobbyGateDiagnostics`) | Chasing unexplained session loss | Remove once A ships (separate) |
| Client challenge queue + one retry on "missing or expired" (`private_site_auth.js`, "PHP sessions hold one challenge") | Parallel first requests created competing sessions | Simplify: single-use challenge rows; keep identity-change queueing |
| Approve retries sign-in once on 403 (`testing_self_approval.js`) | Replaced or lost session | Keep: also covers key changes |
| 401 treated as "maybe expired": `member_approval.js`, `auth_navigation.js` status check | Sessions expired early | Keep: cookies can still be cleared; fires rarely |
| Access history sign-in page (`904e055a`), Messages one-reload guard, unread retry after in-place sign-in | Recover a lost operator/member session | Keep: general recovery; Deploy's Sign in reuses the first |
| 12 PHP test files pre-fill `$_SESSION` (49 sites in `LocalAppSmokeTest`); `SessionRotationTest`, `session_rotation_http.mjs` | Tests could not drive real sessions | Remove: shared signed-in-request helper over the real cookie; delete rotation tests |

Not PHP-specific (stay as they are):

- Invite key/session mismatch recovery (`dff26767`, `ad1ff060`): the session's identity can differ from the browser's saved key under any store.
- Public pages render an empty signed-in identity (static first-paint rule), so the browser signs in on every public visit. Design consequence for A: signing in again as the same identity must reuse the existing session, or rows grow per visit.

Unmerged branches that touch this: `feature/operator-privilege-delegation` (`9f3f9563`) widens POST session resume and becomes unnecessary under A; `d7f14336` (deny revoked-key sessions) should move into A's resolver if it merges first.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Enhancement requests

- **Request:** review the codebase for other workarounds for PHP session shortcomings (2026-11-03). **Disposition:** in-scope refinement of Step 1; inventory added above, server-side removals folded into Option A, client simplifications deferred as follow-ups. **Approval:** not an approval of Step 1. **Outcome:** this section.

## Approval

Approved Step 1 — Option A (2026-11-03T02:07Z, maintainer: "Okay, let's go with option A, approved step 1."). Option B's downsides were discussed first; the recommendation is unchanged.

## Study checkpoint

- Intake 0.90 recorded 2026-11-03T01:28:51Z after reading the todo outline and this request, before source inspection. Step 1 handoff 0.90 for Option A, reaffirmed after inspection.
- Scope v1 / baseline `e6493e2ff4869f8bf98e5f43b3f6388814742c26` / R1 (all three SHA-256 hashes verified) / P2. Option estimates are whole alternatives, not additive.
- Rater: Claude (claude-opus-5-5), single exposed rater; exposed to the todo outline and prior session plans. No implementation, measured labor or accepted outcome.
- Revision 1 (2026-11-03, workaround inventory): **0.90 reaffirmed** for Option A, scope v1 unchanged (new understanding, not new scope). Test conversion (49 sites in one file) is the main upward risk; range stays 0.80–1.05. Client simplifications are excluded from this estimate.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*
