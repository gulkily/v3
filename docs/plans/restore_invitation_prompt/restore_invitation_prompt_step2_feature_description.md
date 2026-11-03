> **Feature plan:** [Step 1](restore_invitation_prompt_step1_solution_assessment.md) · Step 2 · [Step 3](restore_invitation_prompt_step3_development_plan.md) · [Step 4](restore_invitation_prompt_step4_implementation_summary.md)
Debug mode: enabled
Effort study: railspike-effort / v3/restore_invitation_prompt; [ledger](../effort_estimation_trial.md#restore-invitation-prompt)
Extended estimates: enabled
**Cycle estimate: 0.65**

## Problem and user stories

Untargeted invitations silently create a `guest` identity, and the default-off Username changes flag can leave no naming route. Step 1 Option C is approved: inline naming, not native prompts.

- As a new invitee, I want to enter my username beside Redeem invitation so I join under that name.
- As an existing guest, I want to name my current key so I retain my posts and membership.

## Core requirements

- Untargeted invitations needing a name show a labeled Username field directly above Redeem invitation. One submission validates the name, prepares or reuses the key, and redeems. Empty/invalid input never silently becomes `guest`; leaving before submission consumes no invitation slot.
- Existing named identities keep their key and name. Automatic guest keys are reused. Initial naming is available in Lobby and Account even when later username changes are disabled; ordinary renaming still follows the flag. Eligibility is authoritative: original published name `guest` and no prior verified name update, not merely current display text or browser storage. Existing legacy guests meeting that rule qualify; choosing `guest` again does not count as a completed initial name.
- A signature from the same key authorizes naming; approval is neither required for naming nor granted by it. Existing name normalization, duplicate-name rules, signed history and rebuild behavior apply. Concurrent or stale requests cannot reopen initial naming after it is used.
- Failures preserve the key and entered name for retry. If naming succeeds but redemption fails, show the real failure and reuse the saved name. If acceptance succeeds but the response or refresh fails, recover the same grant without another slot. Leaving or retrying never silently replaces a key or undoes committed naming.
- Targeted invitations retain automatic entry; existing guests can subsequently name themselves through Account. Signed APIs, keyless participation and single-/multi-use invitation authority remain compatible. No native prompts or bot-blocking claim are added.

## Delivery and completion boundary

Work type: Application change, focused tests and documentation. Deliver Lobby naming through confirmed membership plus Account guest recovery; update invitation guidance, affected API/flag descriptions, concepts, UI audit and cycle/study records. No dependencies, schema changes, broad Account redesign, merge or deployment.

Reported condition: User opens an untargeted invitation, gets no name choice, and finds no later naming action. Replay with username changes disabled and automatic guest keys both on and off: enter a name, redeem, then verify Account and the same fingerprint after reload/rebuild. An already accepted guest uses Account without a new invitation. The actual site/browser is unspecified; local reproduction does not establish live recovery.

## Risks and early validation

- Flag bypass: Inspect verified original identity and name history before Step 3; test renamed-back-to-guest, legacy guests and competing requests. Reject ineligible changes server-side at acceptance, not just in the UI.
- Partial success: Review naming and redemption recovery before Step 3; preserve committed identity/name/grant and provide retry for the unfinished action. A failed invitation may leave a chosen name, but never a falsely reported membership.
- Shared UI regression: Inspect existing Account editor, identity initialization and static-page rules before Step 3. Reuse controls and determine locally knowable visibility before paint; no late initialization shifts. Verify phone/desktop and affected themes. Unresolved eligibility or recovery risks block dependent implementation.

## Shared surfaces and concepts

Reuse/extend: Lobby template and invite_redemption.js; browser_signing.js identity/name helpers; Account's existing name editor; profile-name prepare/finalize APIs and LocalWriteService; ProfileNames history and existing profile/author/activity consumers; invitation browser fixtures. No parallel editor, key system or naming contract. Extend [invitation lifecycle](../../concepts/invitation_lifecycle.md) entry guidance; add an initial-naming/renaming concept describing delivered policy. Authority stays unchanged.

## Flow and success criteria

1. Open an untargeted invitation, enter a username if needed, and select Redeem invitation.
2. See verified member access under the chosen name; named visitors retain their identity.
3. Retry failures in place, or recover an existing guest's name through Account.

Release evidence: Browser tests assert actual field entry and saved names, not optional dialog handling; cover fresh/automatic/published guest/named keys, flags on/off, public/private sites, invalid input, abandonment, partial success, lost responses and capacity-preserving retries. Verify same fingerprint, approval and posts after reload/rebuild; retain targeted-entry and shared-consumer regression coverage. Record theme/viewport checks, failures and user-environment limitations honestly before completion.

## Approval and study checkpoint

Authorization: User said “approved step 1”; Option C/v2 selected. Step 2 proposed; no implementation or branch reuse authorized. Guest recovery included; prompt restoration and bot controls excluded.
Checkpoint: 2026-11-01 UTC, v2/C, baseline 767a8a2ba8270e29269fd9d2f6d2e707bff8ffa4, R1/P2; whole-cycle 0.65 reaffirmed, original A 0.55 preserved in Step 1. Three frozen hashes verified; Codex single exposed rater; prior inspection and missing pristine intake retained. No measured labor or accepted outcome. Planning remains uncommitted; await Approved Step 2.

*— Codex · 2026-11-01 UTC*

Approval received: User said “approved step 2” on 2026-11-01; v2/C requirements approved for development planning, not implementation.

*— Codex · 2026-11-01 UTC*
