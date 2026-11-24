# Private key access Step 2 feature description

> **Feature plan:** [Step 1](./private_key_access_step1_solution_assessment.md) · [Step 2](./private_key_access_step2_feature_description.md) · [Step 3](./private_key_access_step3_development_plan.md) · [Step 4](./private_key_access_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/private_key_access`; [host ledger](./effort_ledger/private_key_access.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.90**

Approved direction: Step 1 Option A with Revision 1 (maintainer, "approved step 1", 2026-11-24). Epic: [private group chats checklist](./private_group_chats_checklist.md), cycle 1.

## Problem

Any approved member can approve a key into someone else's username. That key then receives the account's new private messages and, through history sync, its older ones. Approval should let a key post, but reading messages needs proof that the key belongs to the account.

## User stories

- As a member, I want a key someone else approved under my name to stay out of my messages so that one careless approval cannot expose my DMs.
- As a member adding a laptop, I want to confirm it from my phone in one tap so that it reads my messages without asking an operator.
- As a member whose new device can't read messages, I want to see why and what to do so that I am not left with an empty inbox.
- As a member, I want to be told when a key I didn't add appears under my name so that I can stop it.
- As an operator, I want to see and confirm waiting keys, and to turn enforcement off if it locks people out.

## Core requirements

- **The rule.** A key has private access when it is approved, not retired, and one of these holds:
  1. It is its username's first approved key, or a root seed.
  2. A same-username key with private access approved it, including through a session bootstrap.
  3. A root-approved operator approved it.
  4. A key meeting rule 2 or 3 later signed a confirm record for it.
- Every other approved key is public only. It posts, reacts and approves as today, but it is left out of new messages, receives no history, and cannot list or open a mailbox.
- The confirm record is a small signed `Key: value` record in Git, like a key retirement. A read-model rebuild gives the same result.
- **On the public-only device:** Messages shows "This device can't read messages yet", a short code from the key's fingerprint, and Show QR. With no private device left, the line points to an operator.
- **On the account's private devices:** Unread and Messages show a card with the same code and two buttons:
  - Confirm writes the confirm record.
  - Not me opens the existing key-retirement flow for that key.
  - Scanning the QR opens the same card.
- **Operators:** Tools → User Access lists waiting keys, with Confirm.
- **Rollback:** a site flag, `FORUM_PRIVATE_KEY_ACCESS_ENFORCED`, on by default. When off, messaging trusts plain approval again, as on `main`. The waiting list and Confirm still work with the flag off.

## Delivery scope

Work type: application change. It covers the rule, the confirm record and its spec, enforcement in the three messaging services, the device line, the card, the QR link, the User Access list, the flag, and docs and concept entries. No new dependency: the QR uses the bundled `qrcode-generator.js`. No change to approval, retirement or posting.

## Completion boundary

- Normal entry: Messages on any device; the card on Unread and Messages; Tools → User Access.
- End-to-end:
  - A test member's second key, approved by another member, shows the device line and gets no new DM.
  - The member confirms it from their first device by QR. The second key then reads a new DM and recovers an older one through history sync.
- Recovery:
  - A member with no private device asks an operator, who confirms from User Access.
  - If enforcement locks members out wrongly, the operator turns the flag off and messaging returns to plain approval.
  - Not me goes through retirement's existing confirmation, so a wrong tap can be cancelled.
- Release condition on zenmemes.com:
  1. Turn the site flag off, then deploy.
  2. Review the User Access list and confirm Ilya's own devices.
  3. Turn the flag on.

## Known user or environment

Ilya on zenmemes.com, with keys under `ilyag` on his iPhone SE and at least one other device.
- Entry: after deploy, the other device opens Messages and shows the device line.
- Failure handled: a legitimate device is downgraded because a member other than Ilya approved it.
- Next action without help: tap Show QR on that device and scan it with the iPhone; or, from the iPhone, use the card on Unread.
- Verification: that device opens Messages and reads a recent DM.
- Evidence: both devices on zenmemes.com showing the line, then the readable conversation. A local test alone does not satisfy this.

## Risks

- **Locking Ilya out.** Impact: his own devices lose messages on deploy. Early validation: a local import of zenmemes.com, listing who would be public only. Mitigation: the flag-off deploy order above.
- **Approver chains.** Impact: a fake key approved by another fake same-name key passes rule 2. Early validation: a test with two chained cross-account keys under one name, before any UI. Mitigation: rule 2 accepts an approver only if that approver has private access itself.
- **History sync gaps.** Impact: an old transfer still reaches a public-only key. Early validation: list every approval check in `PrivateMessageHistorySyncService.php` and test that a public-only key gets no transfer. Mitigation: one shared private-access check used by all three services.
- **Card fatigue.** Impact: the card on Unread nags about a key the owner ignores. Mitigation: no dismiss for now, since the card is also the alarm. Revisit after use.

## Shared component inventory

- `src/ForumRewrite/Messaging/ApprovedUserKeyResolver.php`: the one list of keys a message is encrypted to. Changed to return only keys with private access.
- `src/ForumRewrite/Messaging/PrivateMessageHistorySyncService.php`: its key checks (`key`, `nextKey`, `hasKey`, `context`) test `is_approved` only. Changed to the shared check.
- `src/ForumRewrite/Messaging/PrivateMessageMailboxService.php` `approvedViewer`: the mailbox gate. Changed to the shared check.
- `src/ForumRewrite/ReadModel/ReadModelBuilder.php`: builds `is_approved` and `approved_by_identity_id` for each profile. Rules 1–3 come from these and from `ApprovalPolicy::isOperator`. Approval receipts (`ApprovalAcceptance.php`) are the evidence if the read model lacks something.
- `src/ForumRewrite/Security/KeyRetirement.php` and `KeyRetirementPolicy.php`: the model for the confirm record (path, headers, detached signature, member or operator authority). Not me reuses the retirement flow unchanged.
- `templates/pages/private_message_access.php`: already decides guest and waiting states before paint. Extended with a public-only state, rather than a new page.
- `templates/pages/inbox.php` (Unread) and `templates/pages/private_message_list.php` (Messages): the card goes in each as one module, from one shared partial.
- `templates/pages/user_access.php` and `src/ForumRewrite/Http/UserAccessController.php`: the operator list joins the existing pending list.
- `public/assets/qrcode-generator.js`, as used by `invite_issuance.js`: the QR.
- `FeatureFlagRegistry.php`: one flag in the ACCESS group, requiring `FORUM_PRIVATE_MESSAGES_ENABLED`.
- New: the confirm record type and its spec, because no existing record grants a right to a key that is already approved.

## User flow

1. Someone approves a new `ilyag` key on a laptop. It posts publicly.
2. On the laptop, Messages shows "This device can't read messages yet", a code and Show QR.
3. On his iPhone, Unread shows the card with the same code.
4. Ilya scans the QR, or checks the codes match, and taps Confirm.
5. The laptop reloads Messages and reads his conversations. Older messages arrive through history sync.
6. If the codes don't match, he taps Not me and retires the key.

## Success criteria

- A cross-account key under someone else's name is public only and gets no new message, transfer or mailbox listing. Tests cover each of the three services.
- A chained fake (one fake key approving another) is public only.
- First keys, own-device approvals, session bootstraps and operator approvals keep private access with no extra step.
- Confirm by QR or card gives private access, and history sync then fills older messages.
- With the flag off, recipients and mailboxes match `main` exactly.
- The device line, card and list look final on first paint, in every theme, at 375 and 1280 px.

Concepts touched: [Private-message history](../concepts/private_message_history.md) (changed: only private keys take part), [Approval acceptance](../concepts/approval_acceptance.md) (read), [Key retirement](../concepts/key_retirement.md) (used by Not me), [Feature flags](../concepts/feature_flags.md) (one flag). Proposed inventory addition: "Private key access" (which keys may read an account's messages, and how one is confirmed).

Feedback items: none match; [PF-20261030-F55](../feedback/items/PF-20261030-F55.txt) stays related.

Decisions on Step 1 questions:
- Senders see nothing extra; public-only keys are simply left out.
- Quill gets no confirm command in this cycle.
- Not me reuses key retirement rather than a new review state.
- The downgrade list is the User Access list itself, reviewed with the flag off before enforcement.

Enhancement requests: none pending.

## Study snapshots

- Step 2 handoff, 2026-11-24 UTC: 0.90 projected total reaffirmed for scope v2. The new understanding (rules 1–3 come from the read model's `approved_by_identity_id`, and Not me reuses retirement) roughly balances the operator list and flag-off release steps. Claude single exposed rater; R1 hashes not re-verified this session.

*— Claude · 2026-11-24 UTC · Model: Claude Opus 5.5*
