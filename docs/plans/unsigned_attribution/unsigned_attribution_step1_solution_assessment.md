> **Feature plan:** [Step 1](./unsigned_attribution_step1_solution_assessment.md) · [Step 2](./unsigned_attribution_step2_feature_description.md) · [Step 3](./unsigned_attribution_step3_development_plan.md) · [Step 4](./unsigned_attribution_step4_implementation_summary.md)

Debug mode: enabled

Effort study: railspike-effort / v3/unsigned_attribution · [Host ledger](../effort_estimation_trial.md) · Standing enrollment P2

## Original Query

Please write a new Step 1, keep the existing docs for reference.

## Understood Intent

Reassess Cycle A of the [agent API onboarding checklist](../agent_api_onboarding_checklist.md) (unsigned routes attribute posts and votes to people who did not sign them) under the newly adopted [PRINCIPLES.md](../../../PRINCIPLES.md). The earlier [Step 1](./unsigned_attribution_step1_solution_assessment_superseded.md) and [Step 2](./unsigned_attribution_step2_feature_description_superseded.md) remain for reference; their code findings still hold.

## Problem

Anyone can make a post or vote count as someone else's, because unsigned routes accept any `author_identity_id` or identity-hint cookie, and the site treats that claim as proof.

## Principles that decide this

- Accommodate first, verify later: never turn a person away for lacking a signature.
- Digital signatures are the authority, not the server or database.
- Don't break what already works: old clients and APIs keep working.
- Keep simple, long-lived files; avoid new services and server state.

## Option A: Accept everything, verify by signature

Every post and vote is accepted. Only items whose record carries a valid signature from the named identity count as theirs. Unsigned items naming someone show the claimed name marked unverified, and stay out of scores, post counts and profiles.

- Pros: nobody is refused; no client breaks; the record alone decides, so rebuilds agree; reuses the existing per-record signature status; no record format change.
- Cons: read-model schema change; members' past unsigned items become unverified; claimed names stay visible (marked); the reply agent must start signing.

## Option B: Option A, plus later verification by claim or vouching

As A, and later, unverified items can be verified by a signed record:

- **Claim (always on):** from any device holding their key, the named person signs a claim for items that are theirs. This proves authorship, and the item counts as fully theirs.
- **Vouch (instance setting):** an approved member signs a statement that they believe the named person wrote the item, the same way members vouch for new people. Vouched items are shown as vouched, by whom, distinct from claimed ones. The named person's own claim, or their signed rejection, always takes precedence.
- **Instance setting:** a site feature flag decides whether vouching counts toward scores, post counts and profiles, or only shows. It reuses the existing signed site feature flags record.

Both are ordinary signed records, so verification needs no server-side trust.

- Pros: delivers "verify later" with signatures as the authority; replaces device tokens; works for hint-only browsers, offline queues and old agents alike; vouching covers people who lost every key; each community chooses how much vouching counts.
- Cons: new record types; needs review screens for claims and vouches; vouching can be wrong or collusive (mitigated by showing who vouched and the owner's override).

## Option C: Option A, plus approved keyless devices

As A, and a browser without a key gets a server-issued token that the owner approves (the superseded plan's follow-up).

- Pros: votes from a linked browser count immediately.
- Cons: the token is the server's word, which conflicts with signatures being the authority; adds server state to maintain and revoke; doesn't cover agents or old items.

## Option D: Refuse unsigned attribution

Unsigned posts naming someone are refused with an error.

- Cons: turns people and old clients away. **Ruled out** by accommodate first and don't break what works.

## Recommendation

Option B, delivered in two cycles:

1. **This cycle: Option A (about 0.80).** Accept and mark unverified; verified means signed; the reply agent signs; zenmemes audit; `/api/`, `/llms.txt` and reference updates.
   - Vertical slice: an unsigned post or vote naming someone is accepted and shown as unverified, and cannot change scores, post counts or the profile; signed and anonymous paths are unchanged.
2. **Follow-up cycle: claims, vouching and the instance setting (about 0.6, own cycle ID).** Review unverified items in your name and claim or reject them; approved members vouch; the instance chooses whether vouching counts. Could split further, claims first and vouching second, if Step 2 finds it oversized.

Together they are about 1.4, so a single cycle would be oversized.

To decide in Step 2: how unverified items look, per-client limits, whether the response to an unverified write says how to sign, and whether the instance setting should also cover how unverified items are shown.

## Enhancement Requests

- **Request:** accommodating users comes before immediate verification; verification can be postponed. / **Disposition:** basis of this assessment (Options A and B); now in `PRINCIPLES.md`. / **Approval status:** maintainer direction, 2026-10-23. / **Outcome:** recorded here.
- **Request:** let approved members also verify unverified items, with nuance; make it an instance preference. / **Disposition:** Option B revised: owner claims always verify and take precedence; member vouching shown distinctly and counted per a site feature flag. Placed in the follow-up cycle. / **Approval status:** maintainer direction, 2026-10-23. / **Outcome:** revision 1 of this assessment.
- **Request:** write a new Step 1 and keep the existing documents for reference. / **Disposition:** earlier Step 1 and 2 renamed with `_superseded` and bannered; same cycle ID. / **Approval status:** requested 2026-10-23. / **Outcome:** this document.

## Effort snapshots

Earlier snapshots (intake 1.0, Step 1 0.80 and revision 2 at 1.35 / 0.65, Step 2 0.70 and revision 2 at about 0.80) remain in the [superseded Step 1](./unsigned_attribution_step1_solution_assessment_superseded.md#effort-snapshots) and [superseded Step 2](./unsigned_attribution_step2_feature_description_superseded.md#effort-snapshot).

- Step 1 restart — 2026-10-23T14:28Z; study `railspike-effort`, cycle `v3/unsigned_attribution`, scope v3, R1/P2. Starting revision `9f33caff2e258c2a2068cdf9fd57e074b4f652ef` (mapped to `764fdd82` by the history rewrite); assessed at `5cfad15b`. Claude (claude-opus-5-5), single rater, heavily exposed through the superseded plan. **About 0.80 total projected** for this cycle (Option A), reaffirming Step 2 revision 2 for the same scope. Option B as a whole is about 1.2, split into this cycle and a claims follow-up at about 0.4. Approval context: earlier Approved Step 1 does not carry forward; this assessment awaits approval.
- Step 1 restart revision 1 — 2026-10-23T14:33Z; same study, cycle, scope v3 for this cycle, R1/P2 and rater. **About 0.80 reaffirmed** for this cycle (Option A, unchanged). The follow-up grows from about 0.4 to **about 0.6** with member vouching and the instance setting (scope change for the follow-up only); Option B as a whole is now about 1.4. Reason: maintainer asked for vouching by approved members with an instance preference. Approval context: not yet approved.
