# Private key access Step 1 solution assessment

> **Feature plan:** [Step 1](./private_key_access_step1_solution_assessment.md) · [Step 2](./private_key_access_step2_feature_description.md) · [Step 3](./private_key_access_step3_development_plan.md) · [Step 4](./private_key_access_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/private_key_access`; [host ledger](./effort_ledger/private_key_access.md). Standing P2 enrollment; extended estimates enabled.

Cycle estimate: 0.80

## Original Query

Ok, so the concern is that anyone can approve anyone else's key and then steal the DMs?

[Claude: Mostly. With `FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL` on (the default), any approved member can approve a new key for an existing username. New DMs are then encrypted to it, and history sync shares older DMs with it.]

Ok, so maybe we split approval for public vs private? For private it takes the extra layer of same-username and/or operator?

[Claude: proposed the rule below and asked whether existing cross-account keys are grandfathered or downgraded.]

Downgrade.

## Understood Intent

Approval keeps meaning "may post as this username". Reading that username's private messages becomes a separate right with a stricter source. This closes today's DM exposure, and it is a prerequisite for [private group chats](./private_group_chats_step1_solution_assessment.md). Decided so far: existing keys that do not meet the rule lose private access until someone confirms them.

## Problem

Any approved member can approve a key into someone else's username, and that key then receives the account's new DMs and, through history sync, its older ones.

Today three places trust plain approval: `ApprovedUserKeyResolver.php` (who new messages are encrypted to), `PrivateMessageHistorySyncService.php` (which keys may receive history), and `PrivateMessageMailboxService.php` (who may list and fetch a mailbox).

Concepts touched: [Approval acceptance](../concepts/approval_acceptance.md) (read; its receipts supply the rule's evidence), [Private-message history](../concepts/private_message_history.md) (changed: only private keys take part), [Replaced key](../concepts/replaced_key.md) and [Key retirement](../concepts/key_retirement.md) (used unchanged), [Feature flags](../concepts/feature_flags.md) (one new flag). Options A and B propose a "private key access" concept entry.

Feedback items: none match. [PF-20261030-F55](../feedback/items/PF-20261030-F55.txt) (new keys for returning agents) is related; a returning agent's new key would post publicly at once and wait for private access.

## The rule (all options except D)

A key has private access when it is approved, not retired, and one of these holds:

1. It is the first approved key for its username (receipt classification `new_account`, or a root seed).
2. A same-username key that itself has private access approved it (`own_account_key`), including a session bootstrap.
3. A root-approved operator approved it.
4. A key meeting rule 2 or 3 later confirmed it.

Keys without private access are "public only": they post, react and approve as before, but receive no new messages, no history and no mailbox.

## Option A — Work it out from approval receipts, plus a confirm record
**Estimate: 0.80** — driver: tracing each key's approver chain and gating all three messaging services; uncertainty: how many legacy grants lack a receipt.

Rules 1–3 come from receipts in `records/approval-acceptances/`, which already store the classification and operator flag. Rule 4 adds one small signed record type, "confirm private access", in the plain `Key: value` format. Each key's page shows a Public only pill. The owner's other devices and Tools → User Access offer "Confirm this is my device". A grant without a receipt counts as public only unless it is the username's first key, which is the downgrade.

- No new step for the common cases: a first key or a key you approve yourself works at once.
- Signatures in Git stay the authority, and a read-model rebuild reproduces the result.
- The approver chain needs care, so a fake key cannot pass rule 2 by being approved by another fake one.
- Downgrade may cut off a legitimate second device until its owner confirms it.

## Option B — A separate private-access grant for every extra key
**Estimate: 0.90** — driver: a second record on every device add; uncertainty: the approval flow changes.

Every key after the first needs its own private-access record, written by a same-username or operator key. Same-username approvals write both records at once.

- One explicit record per right, easy to audit.
- Ignores the evidence receipts already hold, and doubles the records written per device.

## Option C — Keep the private-access list on the server only
**Estimate: 0.50** — driver: a table and a checkbox; uncertainty: low.

Store which keys may read messages in private SQLite, set by operators or same-username keys.

- Smallest change.
- Not signed and not in Git, so a rebuild or a move loses it; this goes against "digital signatures are the authority".

## Option D — Turn off cross-account approval
**Estimate: 0.05** — driver: one site setting; uncertainty: none.

Set `FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL` off on zenmemes.com.

- Stops new cross-account keys today, with no code.
- Keys already approved that way keep full access, against the downgrade decision.
- Also blocks public posting for those keys, which this cycle wants to keep open.

## Recommendation

Option A, with Option D as an optional stopgap until it ships. The vertical slice: someone makes a new `ilyag` key and another member approves it. The key posts publicly, gets no DMs and shows Public only. Ilya then confirms it from his phone, and it starts receiving new messages and history. Existing keys that fail the rule show Public only after deploy. A feature flag, `FORUM_PRIVATE_KEY_ACCESS_ENFORCED`, on by default, lets an operator return to plain approval if a bug locks members out.

Questions for Step 2, with proposed defaults:

- Before deploy: list which zenmemes.com keys the downgrade would affect, so Ilya can confirm his own devices first.
- Senders: say nothing extra when a recipient has public-only keys; they are simply left out.
- Agents: Quill gets no confirm command in this cycle.

Enhancement requests: none beyond the conversation above.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*

## Study snapshots

- Intake (after code inspection; no pristine intake): 0.80 projected total for the recommended scope. Baseline `2d7805c8b6d63b0f2ff6ecdd264ec257eaece23b`, scope v1, R1, P2; Claude single exposed rater, 2026-11-24 UTC. R1 hashes not re-verified this session.
- Step 1 handoff: 0.80 for Option A. Options A 0.80, B 0.90, C 0.50, D 0.05; not summed.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*
