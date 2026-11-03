> **Feature plan:** [Step 1](./session_signed_cookie_step1_solution_assessment.md) · [Step 2](./session_signed_cookie_step2_feature_description.md) · [Step 3](./session_signed_cookie_step3_development_plan.md) · [Step 4](./session_signed_cookie_step4_implementation_summary.md)

Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/session_signed_cookie` — standing enrollment, protocol P2 ([ledger](./effort_estimation_trial.md#cycle-v3session_signed_cookie))
Extended estimates: enabled (new enrolled cycle default)
Parent: [`v3/session_replacement`](./session_replacement_step1_solution_assessment.md) (Option A, app-owned sessions). This cycle starts after that one ships.

**Cycle estimate: 0.55**

## Original Query

Please write another Step 1 for Option B, to be added on later as a feature flag. Let us keep this in mind while implementing Option A, please.

## Understood Intent

A later, separate cycle adds the stateless signed cookie (parent assessment's Option B) as an opt-in feature flag on top of the app-owned sessions from `v3/session_replacement`. This assessment is parked until that cycle ships; the parent records what it must leave in place so this flag stays small.

## Problem

Once sign-in lives in an app-owned store, some instances may want sign-in that needs no per-visitor row, for example to survive losing `state/private/` or to share sign-in across hosts with one secret.

## Starting assumptions (all options)

- The parent has shipped: one `ViewerSession` entry point, single-use challenge rows, per-scheme cookie names, HTTP round-trip tests.
- New flag in the **Access and identity** group, default off. New secret setting in `PrivateConfigSchema` (`secret: true`), generated on first enable, never in the Git record.
- The flag controls **issuance only**: both cookie formats keep verifying while a secret exists, so turning the flag on or off signs nobody out. Removing the secret ends signed-cookie sign-ins; browsers with a saved key sign back in as after the parent's cutover.
- Approval is still checked against the current profile on every request.

## Option A — Signed cookie for identity; rows stay for challenges and sign-out
**Estimate: 0.55** — secret lifecycle, a second cookie format behind the existing seam, a small deny list; range 0.45–0.75.

With the flag on, sign-in issues a cookie carrying identity, issue time and expiry, signed with HMAC-SHA256. Challenges keep using the parent's single-use rows. Sign-out and Remove add the cookie's ID to a deny list kept until its original expiry.
- Pros: no row read for ordinary requests; reuses the parent's challenge path unchanged; sign-out still ends the sign-in immediately.
- Cons: a leaked secret lets anyone forge any identity, operators included; a deny list is still state; secret rotation signs everyone with signed cookies out.

## Option B — Signed cookie in front of rows
**Estimate: 0.70** — two verification paths and a per-route decision about which one applies; range 0.55–0.90.

The signed cookie answers ordinary reads; operator tools, approvals and writes also require a matching row.
- Pros: a leaked secret alone cannot run operator actions; rows remain the revocation authority where it matters.
- Cons: keeps both mechanisms everywhere; loses the "no row store" benefit; the route split is a new list to maintain, which is the kind of allowlist the parent removes.

## Option C — Fully stateless, including challenges
**Estimate: 0.75** — replaces the parent's challenge path with signed nonces plus a replay table; range 0.60–0.95.

Challenges become signed nonces; a short replay table stops reuse.
- Pros: closest to the original Option B; no challenge rows.
- Cons: still needs replay state, so the gain over Option A is small; rewrites code the parent just verified.

**Combination considered:** Option A plus "operator routes require a row" takes Option B's protection for operators only, without a general route split. Worth deciding in Step 2.

## Recommendation

**Option A**, with the operator-row combination decided in Step 2. Releasable slice: an operator turns the flag on, new sign-ins over http and https use signed cookies, existing sign-ins keep working, sign-out ends a signed cookie at once, and turning the flag off signs nobody out. No new dependencies (PHP's `hash_hmac` and `random_bytes`); no read-model schema change.

## Concepts touched

- Viewer session — proposed in the parent cycle; changed (second cookie format).
- Feature flags — no inventory entry; used unchanged (new Access flag).

## Study checkpoint

Study / cycle: `railspike-effort` / `v3/session_signed_cookie`; parent `v3/session_replacement`; distinct follow-up, not an independent split of the parent's scope.
Intake 2026-11-03T05:50:13Z: **0.55**, recorded after full exposure to the parent's source inspection and estimates (no pristine intake). Step 1 handoff: **0.55 reaffirmed** for Option A. Alternatives B 0.70 / C 0.75 are complete-cycle and not additive. All estimates assume the parent's slice is delivered as described; they need re-rating against the post-parent code at Step 2.
Scope v1 / baseline `e6493e2ff4869f8bf98e5f43b3f6388814742c26` (pre-parent; Step 2 should record the post-parent revision) / R1 (all three SHA-256 verified) / P2. Rater: Claude (claude-opus-5-5), single exposed rater. Parked: no Step 2 until the parent ships and the user approves. No implementation, measured labor or accepted outcome. Uncommitted.

*— Claude · 2026-11-03 UTC · Model: claude-opus-5-5*
