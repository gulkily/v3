> **Feature plan:** [Step 1](./session_replacement_step1_solution_assessment.md) · [Step 2](./session_replacement_step2_feature_description.md) · [Step 3](./session_replacement_step3_development_plan.md) · [Step 4](./session_replacement_step4_implementation_summary.md)

Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/session_replacement` — standing enrollment, protocol P2 ([ledger](./effort_estimation_trial.md#cycle-v3session_replacement))
Extended estimates: enabled (new enrolled cycle default)

**Cycle estimate: 0.95**

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

Sign-in state depends on PHP session files, ini defaults and a path allowlist the app does not control, so sign-in drops or never sticks in ways that each look different.

## Outline corrections (verified at `e6493e2f`)

- **Deploy:** already patched in `cdfa00dc` by adding `/tools/deploy` to the start list. The defect class remains: sessions start only for a hand-kept path list, and otherwise resume only on GETs (POST: `/api/prepare_invitation` only) when a cookie is present.
- **GC:** the app sets no `save_path` or `gc_maxlifetime`; retention is the host default (PHP default 1440 s idle; production value not checked).
- **iPhone:** the root cause is that the sign-in challenge lives in the session. A single-use challenge checked by signature needs no cookie; only the signed-in identity does.
- **Readers:** `ProfileRepository` only mentions `$_SESSION` in a comment; `LlmExchangesController` reads through an Application closure; Docs renderer/publication only check that no session or cookie exists. Missing from the list: `StaticArtifactBuilder` and `scripts/high_traffic_render.php` (same emptiness guard), the temporary lobby-gate diagnostic log and the Access history `canSignIn` check in `Application`.
- **Tests:** 17 direct `$_SESSION` writes across 5 test files; `DeployPageTest` writes a real session file first. A real HTTP cookie round trip exists only in `tests/browser/session_rotation_http.mjs`.
- **https:** Option C's "redirect http to https" conflicts with PRINCIPLES (HTTP stays for clients too old for current TLS).

## Shared across options

- **https:** do not force it. Use a scheme-specific cookie name (Secure only on https), so an https cookie can never block http. Forcing https would need a PRINCIPLES exception.
- **Revocation:** approval is already checked against the current profile on each request in every option; the difference is ending a sign-in (sign-out, Remove).
- **Migration:** old `PHPSESSID` sessions end at cutover and the cookie is cleared once. Browsers with a saved key sign back in through the existing resume page (members-only) and background sign-in (eligible public pages); operator pages without either show signed-out until the person signs in.

## Option A — App-owned sessions in a private SQLite store
**Estimate: 0.95** — one entry point replacing ~15 call sites, plus migration and round-trip tests; range 0.80–1.20.

A random token cookie maps to a row in a new `state/private/sessions.sqlite3` (identity, created, last seen, expiry). Challenges become single-use rows keyed by value, so sign-in needs no prior cookie. Anonymous visitors get no row and no cookie. One `ViewerSession` call answers "who is signed in" for every route.
- Pros: no PHP session functions or ini; expiry matches the cookie; sign-out deletes the row immediately; inspectable with `sqlite3`; precedent in `state/private/`; read model untouched; rotation race largely disappears (in-flight anonymous requests never set a cookie), so `SessionRotation` can likely go.
- Cons: a new store to back up/exclude and prune; every reader and test changes at once.

## Option B — Stateless signed cookie
**Estimate: 1.10** — new server secret lifecycle plus replay and deny tables; range 0.90–1.40.

Identity and expiry are HMAC-signed with a secret in private config; challenges become signed nonces with a short replay table.
- Pros: no per-visitor rows; any route can verify without a lookup.
- Cons: introduces the first server secret (leak = forge any identity, including operators; loss = everyone signed out); sign-out cannot end a copied cookie without a deny list; still needs replay state, so it is not stateless.

## Option C — Hardened PHP sessions
**Estimate: 0.45** — narrower; does not meet the inspectability or test success criteria; range 0.35–0.60.

One start point for every route, own save path and lifetime matching the cookie, scheme-specific cookie name.
- Pros: smallest change; keeps working code.
- Cons: keeps ini coupling, opaque serialized files, `SessionRotation` and the `$_SESSION`-after-close test trap; GC still runs on PHP's schedule unless the host's cron cooperates.

**Combinations considered:** B identity + A challenge table keeps both a secret and a table (rejected). A's challenge rows + scheme cookie name alone would fix the iPhone class quickly and is the natural split if A grows past 1.0.

## Recommendation

**Option A** as one releasable slice: normal sign-in over http and https, staying signed in for the cookie lifetime, sign-out and Remove ending the session at once, old sessions cleared with silent key-based re-sign-in where it exists today, and HTTP round-trip tests replacing pre-filled `$_SESSION`. No new dependencies; no read-model schema change.

## Revision 1 — keep a signed-cookie mode addable

Request (2026-11-03): add Option B later as a feature flag, and keep that in mind while implementing Option A. Disposition: B becomes the separate follow-up cycle [`v3/session_signed_cookie`](./session_signed_cookie_step1_solution_assessment.md); this cycle adds design constraints only, no flag or secret. Not approval of Step 1.

Option A must leave in place:
- One `ViewerSession` entry point with separate issue, verify and end steps; no caller reads the store or cookie directly.
- Challenges stored independently of the signed-in identity (already part of A).
- A versioned cookie value (for example a `v1.` prefix), so a second format can be recognized alongside the first.
- Sign-out and Remove pass through the single end step, where a deny list can hook in later.
- HTTP round-trip tests written against the entry point's behavior, not the store, so they can run against both modes.

Estimate: **0.95 reaffirmed**, scope v1.1. These constraints describe the shape A already needed; the cost is design discipline, not new work.

*— Revised by Claude · 2026-11-03 UTC · Model: claude-opus-5-5*

## Concepts touched

- Viewer session — proposed, created (sign-in challenge, signed-in identity, sign-out).
- [Application update](../concepts/application_update.md) — used unchanged (Deploy relies on the signed-in check).
- [Private message history](../concepts/private_message_history.md) — used unchanged (signed-in check).

## Related work (separate, not in this slice)

| Request | Disposition | Approval | Follow-up |
| --- | --- | --- | --- |
| Deploy page Sign in button reusing `partials/access_history_auth.php` | Separate work; small, reduces the migration gap on operator pages | Pending | Own cycle or direct change |
| Remove the lobby-gate diagnostic log after cutover | Separate cleanup; its open question is likely the GC finding | Pending | After this cycle |
| Update `authentication_modes.md` and the reauthentication reference status | In scope for this cycle's docs | — | Step 2 |
| Option B as a later feature flag; keep it in mind for A | Separate follow-up cycle; A carries only the constraints in Revision 1 | Step 1 drafted, parked | [`v3/session_signed_cookie`](./session_signed_cookie_step1_solution_assessment.md) |

## Study checkpoint

Study / cycle: `railspike-effort` / `v3/session_replacement`; new cycle, no parent; relates to the 2026-10-30 rotation-race decision.
Intake 2026-11-03T05:31:50Z: **0.95**, after reading the todo outline, rules and ledger, before session source inspection. Step 1 handoff 2026-11-03T05:33:43Z: **0.95 reaffirmed** for Option A (more readers than listed, offset by challenges no longer needing a session). Alternatives B 1.10 / C 0.45 are complete-cycle and not additive.
Scope v1 / baseline `e6493e2ff4869f8bf98e5f43b3f6388814742c26` / R1 (all three SHA-256 verified) / P2. Rater: Claude (claude-opus-5-5), single exposed rater; no independent rating, measured labor or accepted outcome. Uncommitted; awaiting Approved Step 1.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*
