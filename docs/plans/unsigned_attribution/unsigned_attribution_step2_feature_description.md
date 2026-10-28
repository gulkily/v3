> **Feature plan:** [Step 1](./unsigned_attribution_step1_solution_assessment.md) · [Step 2](./unsigned_attribution_step2_feature_description.md) · [Step 3](./unsigned_attribution_step3_development_plan.md) · [Step 4](./unsigned_attribution_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/unsigned_attribution · [Host ledger](../effort_estimation_trial.md) · Standing enrollment P2

## Problem

Unsigned posts and votes can name any person, and the site counts them as that person's: in scores, post counts, profiles and Activity.

## User stories

- As a member, I want only items signed with my key to count as mine, so that nobody can speak for me.
- As someone without a key on this browser, I want my posts and votes accepted under my name, so that I can take part now and verify later.
- As a reader, I want to see which items are unverified, so that a name alone doesn't mislead me.
- As an operator, I want to choose when unsigned items stop counting and see from when, so that my members' history isn't changed without my say.
- As an agent developer, I want my existing unsigned requests to keep working and to be told they are unverified, so that I know signing is what makes them count.

## Core requirements

- A post or vote is **verified** only when its record carries a valid signature from the named identity. A session, cookie or the server's word is not proof.
- One site feature flag, **verified attribution**, turns the rule on. It reuses the existing signed site feature flags record and is **off by default**: with it off, the site behaves exactly as today.
- With the flag on, unsigned posts and votes naming someone are still accepted, never refused for lacking a signature. Those created after the cutoff are unverified: they do not count toward the approved score, post counts or the claimed profile. Labels written in an unverified post's body count as unverified votes.
- Unverified items show the claimed name with the existing "unverified" signature status, not linked to the profile, wherever the author appears (cards, Activity, record details).
- With the flag on, the response to an unverified write says it was recorded as unverified and how to sign instead.
- Everything that signs today keeps signing, and the server's reply agent starts signing its posts with its own key (regardless of the flag).
- **Cutoff:** unsigned items created before the instance's cutoff keep counting as they do today. The comparison uses the server-written creation time, never a client-supplied action time.
- The cutoff is written automatically, as a plain-text record in the content repository, the first time an operator turns the flag on. Turning the flag off and on again keeps it. Rebuilds read it, so they reach the same result.
- The Feature Flags page shows the cutoff beside the flag, and operators can move it.
- Turning the flag on or off takes effect without a manual rebuild.
- Items before the cutoff look as they do today; their record details already show their signature status.

## Decisions from Step 1

- Look: reuse the existing signature status wording; no new labels or explanations.
- Per-client limits: none in this cycle. Unverified items carry no more weight than anonymous posts, which have never been limited, and limits would add server state to maintain.
- Instance setting for how unverified items look: not in this cycle; considered with the vouching setting in the follow-up.
- Accepting unsigned content: one flag including anonymous posts, default on everywhere (maintainer, 2026-10-23). Moved to its own cycle in revision 3; it will depend on the verified attribution flag.
- Existing items: a cutoff, chosen after the zenmemes audit showed about 57% of attributed posts and 95% of votes unsigned, nearly all from the instance's own identities (maintainer, 2026-10-23). This is a deliberate, recorded exception to "signatures are the authority" for items before the cutoff; owners' claims in the follow-up can still verify them.
- Default: today's behavior stays the default, so the rule ships behind a flag that is off; the cutoff is written automatically and shown to operators (maintainer, 2026-10-23). It is written when the flag is first turned on rather than at upgrade, so items posted between upgrade and switch-on are not unverified retroactively. Shipping the rule as unreachable code until a later flag cycle was considered and not chosen: it could not be checked on a real instance.

## Delivery scope

- Work type: application change, including a read-model schema change under CONTRIBUTING.md's rules. No change to record formats.
- In scope: the verified attribution flag and the cutoff.
- Out of scope (own cycle): the accept-unsigned-content flag, which refuses unsigned writes and hides anonymous-post controls.
- Out of scope (follow-up cycle): claims, rejections, member vouching and the vouching feature flag.

## Completion boundary

- Normal entry: posting and voting from the board, thread, Outbox and API as today.
- End-to-end outcome: with the flag off, nothing changes. With it on, an unsigned item naming someone, created after the cutoff, is accepted and shown as unverified, and cannot change scores, post counts or the profile; items before the cutoff and signed or anonymous items behave as before.
- Recovery: unverified writers are told how to sign; turning the flag off restores today's counts; a rebuilt or upgraded read model reaches the same result.
- Release condition: audit counts reviewed; `/api/`, `/llms.txt` and the API reference describe the contract; focused and full-suite results recorded.

## Risks

- **Members' past unsigned items become unverified.** Resolved by the flag (off by default) and the cutoff: zenmemes counts are unchanged at release and when the flag is first turned on. Earliest validation: after-counts equal before-counts at the cutoff.
- **Flag changes must not need a rebuild.** Impact: an operator toggles the flag and counts are stale. Earliest validation: toggle in a test and compare with a full rebuild.
- **Backdating past the cutoff.** Impact: an unsigned item counts as legacy. Mitigation: unsigned writes get their creation time from the server; client-supplied action times are ignored for the cutoff.
- **Most new votes are still unsigned** (357 of 374 overall on zenmemes, 41 unsigned in October). Impact: after the cutoff, the approved score grows mostly from signed votes only. Earliest validation: count signed votes for a week after release. Mitigation: consider the existing automatic guest keypair flag so more browsers sign; the claims follow-up.
- **With the flag on, signed-in API clients that don't sign records lose verified status.** Earliest validation: audit unsigned records with a named identity. Mitigation: the response says how to sign.
- **The reply agent must sign.** Impact: otherwise its replies show unverified. Earliest validation: confirm its key can sign in the worker.
- **Unverified items under real names stay visible, unlimited.** Impact: impersonation attempts appear, marked. Mitigation: the mark, no profile link, no score effect; revisit limits if abuse appears.

## Shared component inventory

- Per-record signature status (Activity, record details): reuse as the verified/unverified source and wording.
- Author display on post, thread, quote and board cards; profile post lists and counts: reuse with the verified rule.
- Score and vote tally in cards and reaction responses: reuse; only the approved score's inputs change.
- Post and tag routes, Outbox and offline vote queue: unchanged entry; responses gain the unverified notice.
- `/api/` route catalog, `/llms.txt`, API reference: extend in place.
- Site feature flags record and Feature Flags page: add one flag in place, with the cutoff shown beside it.
- Instance records in the content repository: add the cutoff record beside the existing instance records.

## User flow

1. An operator turns on verified attribution; the cutoff is written and shown beside the flag.
2. A browser without a key votes, or an agent posts naming someone without signing.
3. It is accepted; the item shows the claimed name as unverified and does not change the score, post count or profile.
4. The response says how to sign; signed items count as before.

## Success criteria

- With the flag off, every post and tag route behaves as today (tests).
- With the flag on, no unsigned request after the cutoff changes any approved score, post count or profile (tests for each post and tag route).
- At release, and right after the flag is first turned on, zenmemes scores, post counts and profiles are unchanged.
- With the flag on, unsigned items naming someone after the cutoff are accepted and shown as unverified everywhere the author appears.
- Signed, anonymous and reply-agent items count as before; no site control sends an unsigned item when it holds a key.
- Full rebuild, incremental update and flag toggles agree; audit counts recorded.

## Enhancement Requests

- **Request:** keep today's behavior as the default; write the cutoff automatically and show it to operators (after revision 2). / **Disposition:** changed scope; the rule moves behind a default-off verified attribution flag; the accept-unsigned-content flag moves to its own cycle. / **Approval status:** maintainer direction 2026-10-23; pending renewed Step 2 and Step 3 approval. / **Outcome:** Step 2 revision 3.

- **Request:** keep existing unsigned items counting with a cutoff (after the Stage 1 audit). / **Disposition:** changed scope; returned to Step 2 during Step 4; Stage 1 kept. / **Approval status:** maintainer direction 2026-10-23; pending renewed Step 2 and Step 3 approval. / **Outcome:** Step 2 revision 2.

- **Request:** a feature flag for accepting unsigned content; one flag, including anonymous posts. / **Disposition:** in scope for this cycle (changed scope). / **Approval status:** maintainer direction 2026-10-23; pending Step 2 approval. / **Outcome:** Step 2 revision 1.

- Carried from Step 1 (approved 2026-10-23): accommodate first, verify later; Option B split, this cycle Option A; claims, vouching and the instance setting deferred to the follow-up.

## Effort snapshot

- Step 2 handoff — 2026-10-23T14:35Z; study `railspike-effort`, cycle `v3/unsigned_attribution`, scope v3, R1/P2; starting revision `9f33caff` (mapped `764fdd82`), assessed at `6f8e4189`. Claude (claude-opus-5-5), single exposed rater. **About 0.75 total projected**, down from about 0.80: no per-client limits (decided above), so no new server state. New understanding from settling Step 1's open questions, not a scope change. Approval context: Approved Step 1 (restart, revision 1).
- Step 2 revision 1 — 2026-10-23T14:38Z; same study, cycle, R1/P2 and rater. **Scope v4** (scope change): adds one accept-unsigned-content flag covering anonymous posts and unverified posts and votes, default on; with it off, unsigned writes are refused with an explanation, anonymous controls are hidden and drafts are kept. **About 0.90 total projected**, up from 0.75 (flag, refusal path across post, vote and Outbox flows, hidden controls). The 0.75 snapshot stays for scope v3. Approval context: maintainer request; Step 2 not yet approved.
- Step 2 revision 2 — 2026-10-23T15:04Z; same study, cycle, R1/P2 and rater. **Scope v5** (scope change during Step 4, after Stage 1): adds a per-instance cutoff so unsigned items before it keep counting. **About 1.05 total projected**, up from 0.95 at Step 3: cutoff record, its automatic first write, and the cutoff rule in both read-model paths. Above 1.0, so a split was considered: the remaining work is one connected rule change and the flag; moving the flag (Stage 7) to its own cycle would bring this to about 0.95. Approval context: maintainer chose the cutoff at the Stage 1 review gate; renewed approval pending.
- Step 2 revision 3 — 2026-10-23T15:20Z; same study, cycle, R1/P2 and rater. **Scope v6** (scope change): the rule ships behind a default-off verified attribution flag; the cutoff is written when the flag is first turned on and shown on the Feature Flags page, where operators can move it; toggling needs no manual rebuild. The accept-unsigned-content flag (refusal path) moves to its own cycle. **About 1.0 total projected**, down from 1.05: the refusal path leaves (about −0.1), the flag-gated read model and cutoff display join (about +0.05). Approval context: maintainer direction; renewed approval pending.
