> **Feature plan:** [Step 1](./same_username_operator_step1_solution_assessment.md) · [Step 2](./same_username_operator_step2_feature_description.md) · [Step 3](./same_username_operator_step3_development_plan.md) · [Step 4](./same_username_operator_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/same_username_operator`; [host ledger](../effort_estimation_trial.md#cycle-v3same_username_operator). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.45 (revised; original 0.40)

Status: approved on 2026-11-09 with Option B. The maintainer wrote "Approved step 3" while only Step 1 existed, then chose "Write Step 2" when asked. Before approving Step 2, he chose a "Make root" button instead; see [Revision 1](#revision-1--a-make-root-button-current-direction). That revision is not yet approved. [Step 2](./same_username_operator_step2_feature_description.md) follows it.

## Original Query

Oh, you're right. I'm going to paste the fingerprint of the account. And I think we should add a rule. If you approve someone with the same username as you and you were also approved by root, you should also be allowed to do all the things that root allows, okay? I think this is like reasonably safe. Maybe this should be another feature flag, question mark.

openpgp-1fc2edb074108568ad28fec8748e323e033d32b4

## Understood Intent

The maintainer has several keys named `ilyag`. Only the seeded key is an operator, so a second key approved by the first cannot use the Feature flags page or other operator tools. A key that an operator approves under the operator's own username should become an operator too.

**Concepts touched:** [approval acceptance](../../concepts/approval_acceptance.md), [key retirement](../../concepts/key_retirement.md), [viewer session](../../concepts/viewer_session.md). Option B adds a candidate concept: operator delegation.

## Problem

An operator is a key with an approval seed, a record in `records/approval-seeds/`. About ten checks call `ApprovalPolicy::isOperator` or `isActiveOperator`. Some of them re-check signed records during a read-model rebuild. An extra key of the same person has no seed, so it is an ordinary member.

## Option A — Decide at check time, behind a flag

**Estimate: 0.75**

Teach `isOperator` a second path. A key counts as an operator when its approver is an operator and both keys were created with the same username. A site flag turns the rule on.

- Pro: no new records; it covers existing keys at once.
- Con: rebuilds re-check old signed records with `isOperator`. Turning the flag off would make a delegated key's past flag changes, retractions and theme lines fail on the next rebuild.
- Con: `isOperator` would need the read model, which some callers do not have.
- Con: every operator check reads a site flag, a wide change on a security path.

## Option B — Write a seed when an operator approves a same-name key

**Estimate: 0.40**

When an operator approves a key, and both keys were created with the same username, the server also writes an approval seed for the new key. The seed reason names the approver. A site flag turns the rule on; with it off, approval works as today.

- Pro: the existing seed contract does the rest. Rebuilds, write paths and the fix from PR #88 work unchanged.
- Pro: turning the flag off stops new delegations; existing seeds stay valid, like other approvals.
- Pro: chains work: a delegated key can delegate to another key with the same name.
- Pro: [key retirement](../../concepts/key_retirement.md) already ends operator power for a retired key.
- Con: it writes a seed from a web request, where today only `./v3 approval seed` writes one.
- Con: it does not cover keys approved before the change. Those still need `./v3 approve` once.
- Risk: someone can create a key named `ilyag` and ask for approval. The approval screen already says "This key will be able to act as ilyag." It would add that the key also becomes an operator.

## Option C — No code: seed each key from the server

**Estimate: 0.05**

Run `./v3 approve openpgp:1fc2edb074108568ad28fec8748e323e033d32b4` over SSH, and repeat for each new key. Document it as the way to add an operator key.

- Pro: works today, with no new security path.
- Con: needs SSH for every new device key.

## Option D — An operator seeds any key from the web

**Estimate: 0.45**

Add an operator-only "Make operator" action on profile pages. It writes a seed for any approved key, with any username.

- Pro: covers other people, such as a co-maintainer.
- Con: wider than the request; one mistaken click creates a new operator under another name.

## Recommendation

Use **Option C now** for the key above; it unblocks the Feature flags page tonight. Then build **Option B**, off by default. It matches the request: an operator's same-name key becomes an operator. It also keeps every operator check on the existing seed records. Option A's rebuild problem makes it unsafe to switch off. Option D is broader than asked.

Vertical slice: the flag, the seed write in the approval path, the warning line on the approval screen, and a test that rebuilds from records. One cycle fits.

## Revision 1 — a "Make root" button (current direction)

The maintainer asked whether an operator tool would be better, "or even from a user's page, like to have a button." Claude agreed, and the maintainer asked to update the plans. This replaces Option B as the recommendation. It is Option D, scoped to a button.

### Option E — "Make root" in the key's access box

**Estimate: 0.45**

The access box on a key's Tools → User Access page already holds the Approve button. Add a "Make root" button there, shown only to active operators, and only on approved, unretired keys without a seed. The operator signs the request, as for a flag change. One commit saves the signed request, its signature and a new approval seed. The seed reason names the operator and the request.

- Pro: deliberate; no hidden side effect of approving a key.
- Pro: works on keys approved earlier, such as `1fc2edb0…`, with no SSH.
- Pro: approval review and its signed context stay unchanged, so Step 2's replay risk goes away.
- Pro: rebuilds and every operator check keep reading seeds, as after PR #88.
- Con: wider than the request: any username, so also a co-maintainer.
- Con: a new signed record type, which needs a parser, a spec note and a replay check.
- Risk: granting root to a look-alike key. The confirmation names the username, and the key's root approval page shows who granted it.

Removal stays as today: retire the key. Deleting a seed would make the key's past operator actions fail on rebuild.

No feature flag: only operators can use the button, and operators can already change every flag. A flag remains cheap if the maintainer wants one.

Revised recommendation: **Option E.** Use `./v3 approve` for the current key only if the button is not ready when it is needed.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Study checkpoint

2026-11-09T03:52:58Z, `railspike-effort` / `v3/same_username_operator`, intake and Step 1 handoff. Scope v1: Option B as recommended. Starting revision `bc4bfd6b9d7e163ef807e1f7b876639ebf5a476a` on `main`. Reference R1, protocol P2; R1 hashes not re-verified. Single exposed Claude rater. Pristine intake is missing: the rater read `ApprovalPolicy.php`, the seed paths, and the approval-state builders before rating, and wrote PR #88 in this session. Intake and Step 1 projected total **0.40**. Option estimates 0.75, 0.40, 0.05 and 0.45 are alternatives and do not add up. No implementation, measured labor or accepted outcome.

2026-11-09T05:12:35Z, Revision 1 checkpoint: the maintainer chose a "Make root" button over Option B. Scope v2: Option E, operator-only, signed, writes a seed, no flag, any username. Projected total 0.40 → **0.45**, reason changed scope: a new signed record type and its parser outweigh dropping the approval-context change. Same baseline, R1/P2 and single exposed Claude rater. No approval implied.

## Enhancement Requests

- 2026-11-09: while investigating, Claude found that three checks trusted `approved_by_label === 'root'`, which any member named `root` could satisfy. Fixed separately in PR #88 (`fix/root_approved_check_uses_seed_records`), outside this cycle.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
