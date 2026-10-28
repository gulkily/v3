> **Superseded (2026-10-23)** by the [new Step 1](./unsigned_attribution_step1_solution_assessment.md), written after the project adopted [PRINCIPLES.md](../../../PRINCIPLES.md). Kept for reference; its approvals and scope do not carry forward.
>
> Superseded set: [Step 1](./unsigned_attribution_step1_solution_assessment_superseded.md) · [Step 2](./unsigned_attribution_step2_feature_description_superseded.md)

Debug mode: enabled

Effort study: railspike-effort / v3/unsigned_attribution · [Host ledger](../effort_estimation_trial.md) · Standing enrollment P2

## Original Query

Start Cycle A.

## Understood Intent

Cycle A of the [agent API onboarding checklist](../agent_api_onboarding_checklist.md#cycle-a-unsigned-attribution-step-1-recommended): stop unsigned routes from attributing posts and votes to identities that did not sign them. Cycle D (merged in `9f33caff`) now advertises the API, which makes this more urgent.

## Problem

Anyone can make a post or a vote count as another person's: unsigned `create_thread`, `create_reply` and `create_quote` accept any existing `author_identity_id`, and `apply_thread_tag`/`apply_post_tag` accept an identity-hint cookie that anyone can set to any username.

**What the code shows**

- The writer only checks that the identity exists. The read model then links the post to the victim's profile and post count; `#label` words in the body become thread-label votes in the victim's name, and those count toward the approved score. A missing signature is accepted; only an invalid one is rejected.
- Votes always need an identity: with no hint, the tag routes refuse. There is no anonymous vote to fall back to.
- `set_identity_hint` sets a plain cookie to any value, and the server looks it up in `username_routes`. A hint-only browser is therefore indistinguishable from an attacker; the honest one needs something an attacker cannot copy.
- Every identity is key-backed (`public_key`, `signer_fingerprint`), and session sign-in is a signed challenge. So a keyless browser belongs to an account whose key lives on another device, or was lost.
- A keyless browser can keep a cookie (it keeps the hint), so it can keep a server-issued secret.
- The server's own reply agent posts through the same unsigned writer with its identity ID, without HTTP. A writer-level ban would break it.
- The browser never needs the unsigned attribution for posts: anonymous posts clear the field and everything else is signed. For votes, it signs when it holds a key but sends unsigned votes when it only has a hint. Old queued offline votes are always unsigned.
- On members-only instances the tag routes already use the authenticated session, not the hint.
- "Add this key to my account" already lets an existing device approve a new key, with operator grants as the fallback.
- No local copy of zenmemes content exists here, so the audit must read its published records.

## Requirement from review

Hint-only browsers must not lose voting: having a key cannot be assumed. Keyless use is a capability to keep, not a path to remove.

## Option A: Reject at the HTTP boundary

The public routes refuse `author_identity_id` on unsigned posts and refuse unsigned votes unless the session is authenticated.

- Pros: smallest change; the reply agent is unaffected.
- Cons: hint-only browsers lose voting. **Ruled out by the requirement.**

## Option B: Silently drop the field

Unsigned posts become anonymous instead of failing.

- Cons: contradicts the error-first contract Cycle D documented, and votes cannot be dropped usefully. Ruled out.

## Option C: Trust only verified signatures in the read model

- Cons: hint-only votes stop counting entirely, and every legitimate unsigned-but-attributed record changes display. Ruled out by the requirement.

## Option D: Hold each unsigned item for confirmation

The named person confirms each post or vote from a signed device.

- Cons: per-item queue, spam limits, and late score changes for every item. Superseded by Option E, which approves once per browser.

## Option E: Approved keyless devices (chosen)

Extra approval, like new keys, but per browser: the browser holds a server-issued, httponly device token linked to an identity instead of a key.

1. A hint-only browser asks to link to an identity.
2. The named person approves it from a device holding the key, on the same kind of screen as "Add this key to my account". With no such device, an operator approves it, as key grants already allow.
3. Votes from a linked browser are attributed because the token verifies, not because the hint says so. The owner can see and revoke linked browsers.

Attribution requires a signature, an authenticated session or a valid device token. A bare hint or a bare `author_identity_id` is not enough. The reply agent keeps using the writer directly.

**Before approval (chosen: count as unverified).** A hint-only vote is accepted and shows in the tally right away, but as unverified: it does not count toward the approved score and does not link to the claimed profile. Queued offline votes take the same path.

- Pros: voting is never refused; the hole closes for scores and profiles; approval happens once per browser, so there is no per-item queue; reuses the key-approval surfaces.
- Cons: a read-model change (unverified votes and a separate tally), so CONTRIBUTING.md's schema rules apply; two tallies to explain; an attacker can still add unverified votes under claimed names, so these need per-browser limits; hint-only votes from approved members stop affecting the approved score until the browser is linked.

## Recommendation

Option E, split in two cycles because together they come to about 1.35:

1. **This cycle — close the hole, keep voting (about 0.65).** Unsigned posts with `author_identity_id` are refused with an error pointing to prepare and create. Hint-only and queued votes are recorded as unverified: visible in the tally, outside the approved score, not linked to the profile. Includes the read-model schema change, the read-only zenmemes audit, tests, and reference, `/llms.txt` and `/api/` updates.
   - Vertical slice: an attacker's hint vote cannot move the approved score or appear on the victim's profile; an honest hint-only browser still votes and sees it counted as unverified; signed, anonymous and reply-agent posts still work.
2. **Follow-up cycle — device linking (about 0.8, own cycle ID).** Link request, approval by the owner or an operator, token-based attribution, a list of linked browsers with revoke. Linked browsers' votes count as verified.

To decide in Step 2:

- Whether a browser's unverified votes are promoted when it is later linked. This needs a token issued at link-request time, which would move part of linking into this cycle.
- How unverified votes are shown, and the per-browser limits.
- Whether linked keyless browsers may also post with attribution (new for the browser UI; follow-up candidate).
- Whether the audit's findings mark existing hint-attributed records as unverified.

## Enhancement Requests

- **Request:** treat unsigned attribution as an accessibility feature with an extra approval, like new keys. / **Disposition:** first assessed as Option D, then refined into Option E (per-browser approval). / **Approval status:** user chose E with unverified counting before approval (2026-10-23). / **Outcome:** recorded in this revision.
- **Request:** hint-only browsers must not lose voting. / **Disposition:** rules out A and C; met by unverified counting. / **Approval status:** requirement from the user. / **Outcome:** recorded in this revision.

## Effort snapshots

- Intake — 2026-10-21T20:48:13Z; study `railspike-effort`, cycle `v3/unsigned_attribution`, legacy alias "Cycle A" from the [onboarding checklist](../agent_api_onboarding_checklist.md); no parent. Scope v1 from `9f33caff2e258c2a2068cdf9fd57e074b4f652ef`, R1/P2. Claude (claude-opus-5-5), single exposed rater. The **1.0** is inherited from the checklist, which the same rater wrote on 2026-10-20 after inspecting `resolveAuthorIdentityId()`, the read-model linking and thread-label scoring, so this is not a pristine estimate. Recorded before this cycle's detailed inspection.
- Step 1 handoff — 2026-10-21T21:05Z; same rater, reference and starting revision. **0.80 total projected** for the recommended scope: boundary rejection for three post routes and two tag routes, browser handling for hint-only and queued votes, read-only zenmemes audit, tests, and reference, `/llms.txt` and `/api/` updates. This is lower than intake because inspection showed no read-model change is needed unless the audit finds forgeries, which goes to a follow-up. It is comparable to R1 composer decluttering (0.60) plus the explicit dependency installer's recovery work (0.90). New understanding, not a scope change. Approval context: user asked to start Cycle A; no step approved yet.
- Step 1 revision — 2026-10-21T21:20Z; same rater, reference and starting revision. **0.80 reaffirmed** for the recommended scope (Option A plus audit), which this revision does not change. If the user picks D in this cycle instead, the projection would be about **1.3** (scope change; split discussion). A separate D follow-up would be about **0.9** and get its own cycle ID. Reason: user review added Option D.
- Frozen R1 verified: report SHA-256 `d3a30b6400b83bb25283b4234b41ba07515df9a696ea44de59c10186103771b0` and diff review `068b3519326e08a939df40b5abe0106ddcbab80bd796616c76b5084a21462b84` in `/home/wsl/railspike-dev/docs/research/effort_estimation/`.
- Step 1 revision 2 — 2026-10-23T06:46Z; same rater, reference and starting revision. **Scope v2** (scope change): the user required that hint-only browsers keep voting and chose Option E (approved keyless devices) with unverified counting before approval. **About 1.35 total projected** for full E, which triggers a split discussion. The recommended split projects **0.65** for this cycle (post rejection, unverified hint votes with the read-model schema change, audit, tests, docs) and **about 0.8** for a device-linking follow-up with its own cycle ID. The 0.80 for Option A is superseded, not revised: A was ruled out. This cycle's 0.65 is lower than A's 0.80 because hint-vote browser handling and recovery messaging move out, while the read-model change comes in. Comparable to R1 composer decluttering (0.60). Approval context: user chose E and option 2 and asked to revise Step 1; no step approved.
