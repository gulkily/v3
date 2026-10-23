> **Feature plan:** [Step 1](./unsigned_attribution_step1_solution_assessment.md) · [Step 2](./unsigned_attribution_step2_feature_description.md) · [Step 3](./unsigned_attribution_step3_development_plan.md) · [Step 4](./unsigned_attribution_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/unsigned_attribution · [Host ledger](../effort_estimation_trial.md) · Standing enrollment P2

## Problem

Unsigned posts and votes can name any person, and the site counts them as that person's: in scores, post counts, profiles and Activity.

## User stories

- As a member, I want only items signed with my key to count as mine, so that nobody can speak for me.
- As someone without a key on this browser, I want my posts and votes accepted under my name, so that I can take part now and verify later.
- As a reader, I want to see which items are unverified, so that a name alone doesn't mislead me.
- As an agent developer, I want my existing unsigned requests to keep working and to be told they are unverified, so that I know signing is what makes them count.

## Core requirements

- A post or vote is **verified** only when its record carries a valid signature from the named identity. A session, cookie or the server's word is not proof.
- One site feature flag, **accept unsigned content**, covers every unsigned write: anonymous posts and unverified posts and votes. It reuses the existing signed site feature flags record and is **on by default**, which keeps today's behavior.
- With the flag on, unverified posts and votes are accepted, never refused for lacking a signature. They do not count toward the approved score, post counts or the claimed profile. Labels written in an unverified post's body count as unverified votes.
- Unverified items show the claimed name with the existing "unverified" signature status, not linked to the profile, wherever the author appears (cards, Activity, record details).
- The response to an unverified write says it was recorded as unverified and how to sign instead.
- With the flag off, unsigned writes are refused with an error saying how to sign. The site hides its anonymous-post controls, and the compose form and Outbox keep what the person wrote.
- Everything that signs today keeps signing, and the server's reply agent starts signing its posts with its own key.
- Existing records follow the same rule when the read model is rebuilt; the zenmemes audit measures the change before release.

## Decisions from Step 1

- Look: reuse the existing signature status wording; no new labels or explanations.
- Per-client limits: none in this cycle. Unverified items carry no more weight than anonymous posts, which have never been limited, and limits would add server state to maintain.
- Instance setting for how unverified items look: not in this cycle; considered with the vouching setting in the follow-up.
- Accepting unsigned content: one flag including anonymous posts, default on everywhere (maintainer, 2026-10-23).

## Delivery scope

- Work type: application change, including a read-model schema change under CONTRIBUTING.md's rules. No change to record formats.
- In scope: the accept-unsigned-content flag.
- Out of scope (follow-up cycle): claims, rejections, member vouching and the vouching feature flag.

## Completion boundary

- Normal entry: posting and voting from the board, thread, Outbox and API as today.
- End-to-end outcome: an unsigned item naming someone is accepted and shown as unverified, and cannot change scores, post counts or the profile; signed and anonymous items behave as before.
- Recovery: unverified writers are told how to sign; with the flag off, refused writers keep their text and are told how to sign; a rebuilt or upgraded read model reaches the same result.
- Release condition: audit counts reviewed; `/api/`, `/llms.txt` and the API reference describe the contract; focused and full-suite results recorded.

## Risks

- **Members' past unsigned items become unverified.** Impact: profiles, post counts and rankings shift on release. Earliest validation: audit before/after counts. Mitigation: review counts before Step 3; the follow-up lets owners claim them.
- **Signed-in API clients that don't sign records lose verified status.** Earliest validation: audit unsigned records with a named identity. Mitigation: the response says how to sign.
- **The reply agent must sign.** Impact: otherwise its replies show unverified. Earliest validation: confirm its key can sign in the worker.
- **Unverified items under real names stay visible, unlimited.** Impact: impersonation attempts appear, marked. Mitigation: the mark, no profile link, no score effect; revisit limits if abuse appears.

- **With the flag off, keyless and no-JavaScript visitors cannot post or vote.** Impact: the basic plain-form posting path closes on that instance. Earliest validation: check every compose and vote path with the flag off. Mitigation: default on; the instance chooses this knowingly, and the Feature Flags page states the consequence.

## Shared component inventory

- Per-record signature status (Activity, record details): reuse as the verified/unverified source and wording.
- Author display on post, thread, quote and board cards; profile post lists and counts: reuse with the verified rule.
- Score and vote tally in cards and reaction responses: reuse; only the approved score's inputs change.
- Post and tag routes, Outbox and offline vote queue: unchanged entry; responses gain the unverified notice.
- `/api/` route catalog, `/llms.txt`, API reference: extend in place.
- Site feature flags record and Feature Flags page: add one flag in place.

## User flow

1. A browser without a key votes, or an agent posts naming someone without signing.
2. It is accepted; the item shows the claimed name as unverified and does not change the score, post count or profile.
3. The response says how to sign; signed items count as before.

## Success criteria

- No unsigned request changes any approved score, post count or profile (tests for each post and tag route).
- Unsigned items naming someone are accepted and shown as unverified everywhere the author appears.
- Signed, anonymous and reply-agent items count as before; no site control sends an unsigned item when it holds a key.
- With the flag off, every unsigned write is refused with an explanation, no anonymous-post control shows, and nothing the person wrote is lost.
- Full rebuild and incremental update agree; audit counts recorded.

## Enhancement Requests

- **Request:** a feature flag for accepting unsigned content; one flag, including anonymous posts. / **Disposition:** in scope for this cycle (changed scope). / **Approval status:** maintainer direction 2026-10-23; pending Step 2 approval. / **Outcome:** Step 2 revision 1.

- Carried from Step 1 (approved 2026-10-23): accommodate first, verify later; Option B split, this cycle Option A; claims, vouching and the instance setting deferred to the follow-up.

## Effort snapshot

- Step 2 handoff — 2026-10-23T14:35Z; study `railspike-effort`, cycle `v3/unsigned_attribution`, scope v3, R1/P2; starting revision `9f33caff` (mapped `764fdd82`), assessed at `6f8e4189`. Claude (claude-opus-5-5), single exposed rater. **About 0.75 total projected**, down from about 0.80: no per-client limits (decided above), so no new server state. New understanding from settling Step 1's open questions, not a scope change. Approval context: Approved Step 1 (restart, revision 1).
- Step 2 revision 1 — 2026-10-23T14:38Z; same study, cycle, R1/P2 and rater. **Scope v4** (scope change): adds one accept-unsigned-content flag covering anonymous posts and unverified posts and votes, default on; with it off, unsigned writes are refused with an explanation, anonymous controls are hidden and drafts are kept. **About 0.90 total projected**, up from 0.75 (flag, refusal path across post, vote and Outbox flows, hidden controls). The 0.75 snapshot stays for scope v3. Approval context: maintainer request; Step 2 not yet approved.
