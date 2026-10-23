> **Feature plan:** [Step 1](./account_key_revocation_step1_solution_assessment.md) · [Step 2](./account_key_revocation_step2_feature_description.md) · [Step 3](./account_key_revocation_step3_development_plan.md) · [Step 4](./account_key_revocation_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/account_key_revocation` — [P2 ledger](../effort_estimation_trial.md#cycle-v3account_key_revocation).

## Problem

A compromised approved key can authenticate again after session clearing. Operators need durable revocation that preserves account ownership and historical evidence without removing access from previously sponsored members.

## User stories

- As an operator, I want to revoke an exact account key so that it cannot exercise account privileges again.
- As an account holder, I want my username and approval history preserved so that I can recover through another trusted key.
- As a sponsored member, I want a sponsor's later revocation to leave my accepted membership intact.

## Core requirements

- Only a currently approved, root-seeded operator may authorize revocation, after reviewing the account, exact key and access-loss consequence. Reject root-seeded targets server-side. Bind the signed action to the reviewed target; reject changed authority or stale review and recheck at acceptance.
- Revocation is terminal for the targeted key in this cycle. Existing sessions lose privileges on subsequent protected requests; new login, direct signed writes, pending preparations, approvals, invitations and retries cannot restore or bypass access. Concurrent acceptance has a defined ordering; untrusted access state fails closed. Public access follows existing site rules.
- Preserve accepted grants to other keys, historical attribution and durable username ownership, including after the last ordinary key is revoked. Block new grants/redemptions relying on a revoked issuer without cascading to previously accepted sponsorships.
- Preserve revoked keys and actor/time/source evidence in account and operator history within existing audience boundaries. Full/incremental rebuild and supported archive restoration retain revocations; a pre-revocation backup is not current security state and must not silently reopen service.
- Show the affected device an actionable revoked-access state. Recovery uses another approved key, or a new key explicitly approved by an operator against the retained account claim. Permit last ordinary-key revocation with an explicit warning; do not offer undo, same-key reapproval, or imply downloaded information can be erased.

## Explicit revocation cutoff

The signed revocation request must contain an explicit `revocation_cutoff` UTC timestamp. Preparation defaults it to “now” and freezes that exact value for review and signing; the accepted record preserves it unchanged. The operator reviews the cutoff alongside the account and exact key. Submission time, server acceptance time and Git commit time are separate facts and must never supply, replace or silently reinterpret the cutoff. Missing, malformed or modified cutoff values are rejected. An expired/stale review requires a newly prepared request and renewed review, not an unsigned timestamp substitution. This cycle exposes no custom, backdated or scheduled cutoff selector.

Access denial begins when the server accepts the revocation under the write lock; the explicit cutoff does not make enforcement retroactive. Already accepted posts, signatures and sponsorships remain preserved, including actions accepted during a preparation-to-acceptance delay. Same-time concurrent actions use server acceptance ordering, not a member-supplied creation timestamp. Future cutoff-based historical trust/moderation rules require a separate design.

## Delivery and completion boundary

Work type: application change. Deliver User Access entry → exact-key review/signing → accepted revocation → enforced denial and visible evidence, including stale-review refresh, cancellation, rejection, duplicate retry and committed-but-unrefreshed recovery. Reuse existing dependencies; proposed dependency delta is zero. Deliver operator recovery/rollback guidance and update the safety checklist, cycle artifacts and study ledger. No production deployment is included.

Member self-service (Option B), possible cross-user authority beyond root-approved operators, notifications, pairing, social recovery and root-key rotation are separate follow-ups. The user's explicitly requested `todo.txt` additions record those follow-ups without expanding this implementation scope.

## Risks and early validation

- **Cascading access loss:** current approval derives through sponsors. Validate direct/invitation and legacy sponsorship fixtures before designing enforcement; retain historical grant authority separately from current access. An unresolved preservation rule blocks dependent work.
- **Username takeover or lockout:** current grouping relies on usernames. Validate last-key revocation, rename and replacement-key recovery before UI work; retain the claim and reject unauthorized replacement. Rescope if durable ownership needs a larger identity redesign.
- **Bypass or stale access:** enumerate sessions, signed writes, private messaging, operator routes and caches early; verify acceptance-time races, stale projections and rebuild/import parity. Preserve committed revocation if refresh fails and give a rebuild/retry path.
- **Misleading recovery:** test affected-device and operator flows early. Warn beside last-key decisions; distinguish irreversible key revocation from session clearing and document backup/older-code rollback limits.

## Shared surfaces

Extend User Access for the action; reuse canonical signing/review and recovery controls. Extend shared account-key history across classic/Forte username/profile pages and profile API, operator Access history/security Activity, evidence/source links and CLI approval audit. Extend shared approval evaluation, direct/manual/invitation acceptance, full/incremental projections and archive validation for consistent current/historical state. Reuse authentication status/reconnection, members-only gates, private messaging key resolution and signed-write authorization for denial. Review static/offline publication and invalidation boundaries; no new navigation destination or public feed.

## Flow and success criteria

1. An authenticated operator opens User Access, selects an ordinary account key and reviews the exact target and consequence.
2. Signing accepts once or returns an actionable refusal/refresh path; cancellation writes nothing. A lost response can be retried without another event.
3. Subsequent privileged requests from that key fail, including an existing browser session and direct signed requests; affected devices receive recovery guidance.
4. The operator/member sees retained revocation evidence; another trusted or explicitly operator-approved replacement key retains account access. Sponsored members stay approved and the username stays reserved.

Release requires isolated signed end-to-end phone/desktop success, denial and recovery checks; authorization/race/stale-state tests; retained-history/claim/sponsorship assertions; rebuild/archive parity and relevant cache/access-boundary checks. Full-suite or production acceptance is not implied by focused passes.

## Approval and study checkpoint

Step 1 approved; Step 2 awaiting approval. Request/disposition: Option B and possible cross-user revocation recorded in `todo.txt` as deferred, authority unresolved for the latter; implementation not approved. Snapshot 2026-10-23T14:52:58.854480+00:00: scope v1 clarified, original baseline `82cef24f17de4b644497a7d9c38aaa54d27faf02`, R1/P2, Codex single exposed rater. Total projection remains unavailable pending exact frozen feedback recovery; report/diff hashes match R1, current feedback differs as recorded in Step 1. No invented score, measured effort or accepted outcome; ledger bookkeeping remains uncommitted until authorized closeout.

## Cutoff clarification — scope v2

User request: specify the cutoff inside the revocation request. Disposition: revise the signed review/evidence contract above; retain operator-only authority and all prior-post preservation rules. Step 2 v1 approval remains historical; this revised timestamp contract is pending review, and Step 3 remains unapproved. No implementation authorization follows from this design edit.

Step 2 revision snapshot 2026-10-23T17:34:33.303738+00:00: scope v2; same original baseline/R1/P2 and exposed single rater. Total projection remains unavailable pending exact frozen feedback recovery; prior snapshots retained. No measured effort or accepted outcome.
