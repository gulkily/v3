> **Superseded (2026-10-23)** by the [new Step 1](./unsigned_attribution_step1_solution_assessment.md), written after the project adopted [PRINCIPLES.md](../../../PRINCIPLES.md). Kept for reference; its approvals and scope do not carry forward.
>
> Superseded set: [Step 1](./unsigned_attribution_step1_solution_assessment_superseded.md) · [Step 2](./unsigned_attribution_step2_feature_description_superseded.md)

Debug mode: enabled

Effort study: railspike-effort / v3/unsigned_attribution · [Host ledger](../effort_estimation_trial.md) · Standing enrollment P2

## Problem

Anyone can post or vote as someone else: unsigned post routes accept any existing `author_identity_id`, and tag routes trust an identity-hint cookie anyone can set. These count as the named person's: forged votes move the approved score, and forged posts and votes appear on the victim's profile and in Activity.

## User stories

- As a member, I want only posts and votes signed with my key to count as mine, so that nobody can speak for me.
- As someone without a key on this browser, I want my posts and votes accepted under my name, so that I can take part now and verify later.
- As a reader, I want to see which posts and votes are verified, and I want scores to count only verified votes, so that naming someone else cannot game rankings.
- As an agent developer, I want my existing unsigned requests to keep working and to be told they are unverified, so that I know signing is what makes them count.

## Core requirements

- A post or vote is **verified** only when its record carries a valid signature from the named identity. Everything else that names an identity is **unverified**. The server's word, a session or a cookie is not proof.
- Unverified posts and votes are accepted, never refused for lacking a signature. They show the claimed name marked unverified, do not count toward the approved score, post counts or profile, and Activity shows the same mark.
- The response to an unverified write says it was recorded as unverified and how to sign instead.
- Pages that sign today keep signing: named posts from the site, votes from browsers that hold a key, and the server's reply agent, which starts signing with its own key.
- Existing records follow the same rule when the read model is rebuilt. The zenmemes audit measures how many posts, votes and scores change before release.
- Unverified writes are bounded per client, so claimed names cannot flood a thread.

## Delivery scope

- Work type: application change, including a read-model schema change under CONTRIBUTING.md's rules. No change to the record format.
- Out of scope (follow-up cycles): verifying earlier unverified items later (by device linking or a signed claim from the owner), linked-browser list and revoke.

## Completion boundary

- Normal entry: posting and voting from the board, thread and API as today.
- End-to-end outcome: an unsigned post or vote naming someone shows as unverified and cannot change scores, post counts or the profile; signed ones count as before.
- Recovery: an unverified writer is told how to sign; a rebuilt or upgraded read model reaches the same result.
- Release condition: audit counts reviewed, `/api/`, `/llms.txt` and the API reference describe the new contract, focused and full-suite results recorded.

## Risks

- **Members' past unsigned posts and votes become unverified.** Impact: profiles, post counts and rankings shift on release. Earliest validation: audit before/after counts. Mitigation: review counts before Step 3; later verification arrives in the follow-up.
- **Signed-in API clients that don't sign records lose verified status.** Impact: their votes stop counting. Earliest validation: audit unsigned records written with a session. Mitigation: the response says how to sign.
- **The reply agent must sign its posts.** Impact: otherwise its replies show unverified. Earliest validation: confirm its key can sign through the existing writer path.
- **Claimed names on unverified posts are visible.** Impact: impersonation attempts still appear, marked. Mitigation: unverified mark, no profile link, per-client limits.

## Shared component inventory

- Existing per-record signature status (Activity, record metadata): reuse as the verified/unverified source; no new indicator.
- Post author display on cards, profile post lists and counts: reuse with the verified rule.
- Score and vote tally on thread, quote and board cards and in reaction responses: reuse; only the approved score's inputs change.
- Post and tag routes, offline vote queue: unchanged entry; responses gain the unverified notice.
- `/api/` (route catalog), `/llms.txt`, API reference: extend in place.

## User flow

1. A browser without a key votes, or an agent posts with `author_identity_id` and no signature.
2. It is accepted and marked unverified; the tally rises, the approved score and profile do not change.
3. The response says how to sign; signed posts and votes count as before.

## Success criteria

- No unsigned request can change any approved score, post count or profile (tests for each post and tag route).
- Unsigned posts and votes that name someone are accepted and shown as unverified.
- Signed, anonymous and reply-agent paths still count as before; no site button sends an unsigned post or vote when it holds a key.
- Full rebuild and incremental update agree; audit counts recorded.

## Enhancement Requests

- Carried from Step 1: approved keyless devices (Option E) and keeping hint-only voting. Approved Step 1 on 2026-10-23; device linking is deferred to its follow-up cycle.
- **Request:** confirm no site button can fail because of the change. / **Disposition:** in scope; success criterion added. / **Approval status:** pending Step 2 approval. / **Outcome:** revision 2.
- **Request:** accommodating users comes before immediate verification; verification can be postponed (2026-10-23, now in `PRINCIPLES.md`). / **Disposition:** changed scope: unsigned attributed posts are accepted as unverified instead of refused; verified means signed only, dropping the session exception; reply agent signs. / **Approval status:** pending Step 2 approval. / **Outcome:** revision 2.

## Effort snapshot

- Step 2 handoff — 2026-10-23T10:21Z; study `railspike-effort`, cycle `v3/unsigned_attribution`, scope v2 from `9f33caff2e258c2a2068cdf9fd57e074b4f652ef` (now mapped to `764fdd82` by the history rewrite), R1/P2. Claude (claude-opus-5-5), single exposed rater. **0.70 total projected** for this cycle's scope, up from 0.65 at the Step 1 revision. New understanding, not a scope change: session-authenticated votes are unsigned, so the server must record the trust basis at write time (adds work); the existing per-record signature status can be reused (saves some). Comparable to R1 composer decluttering (0.60). Approval context: Approved Step 1 (Option E, split, this cycle only).
- Step 2 revision 2 — 2026-10-23T14:16Z; same study, cycle, starting revision, R1/P2 and rater. **Scope v3** (scope change): unsigned posts naming an identity are accepted as unverified instead of refused; verified now means a valid signature only (session exception dropped, since browsers holding a key already sign votes); the reply agent signs its posts. **About 0.80 total projected**, up from 0.70: post attribution joins the read-model change and the agent gains signing (adds); no trust-basis field is needed (saves). The 0.70 snapshot stays as recorded for scope v2. Approval context: user review against `PRINCIPLES.md`; Step 2 not yet approved.
