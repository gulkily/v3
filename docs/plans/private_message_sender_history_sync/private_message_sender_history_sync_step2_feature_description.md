# Sender-assisted private message history synchronization — Step 2

> **Feature plan:** [Step 1](./private_message_sender_history_sync_step1_solution_assessment.md) · [Step 2](./private_message_sender_history_sync_step2_feature_description.md) · [Step 3](./private_message_sender_history_sync_step3_development_plan.md) · [Step 4](./private_message_sender_history_sync_step4_implementation_summary.md)

## Problem

Recipients can lose historical access while senders retain readable copies. Recover incoming messages through senders when recipient devices cannot help.

## User stories

- As a recipient, I want sender-assisted recovery so that losing older devices need not lose history.
- As a sender, I want automatic contributions so that recovery needs no coordination.
- As a reader, I want verified originals so that authorship remains trustworthy.

## Core requirements

- An approved sender-account key with verified access may help approved keys of each message's original recipient. Recheck current authorization per message and transfer use; reject unrelated accounts. No additional sharing prompt.
- Authenticate transfers against intended participants and exact originals. Verify original signatures before trusted display or recovery confirmation. Preserve original ciphertext and retain transfers for reload; uploads/confirmations never replace verification.
- Contribute during authenticated visits, including non-Messages pages. Bound work fairly across recipients/keys/history; combine partial donors, resume interruptions and revisit gaps without starving same-account recovery. Simultaneous visits are unnecessary.
- Keep plaintext/private keys/unwrapped session keys out of server storage/logs and public/offline artifacts. Expose no recipient read receipts, detailed recovery progress or unrelated history to donors. Revocation cannot retract delivered secrets.
- Preserve same-account transfers, previews/history/groups, drafts, focus, reading position, sending and unread semantics. Keep sync silent: no global notice/counts/Retry history button; retain per-message states/retry.

## Delivery scope and completion boundary

**Application change:** Option A. Deliver approval → separate sender visit → recipient Messages → verified incoming history, including partial recovery/reload. Require authorization, compatibility and browser regressions; fit eight stages/one day or rescope. Update the shareable guide. Exclude recipient-to-sender assistance, authentication redesign, notifications, export and production deployment.

## Risks

- **Disclosure across accounts:** first test unrelated accounts, mixed-recipient batches and revocation; reject invalid contributions before enabling donors.
- **Metadata leakage:** first review discovery responses; expose only scoped contribution work.
- **Starvation or foreground delays:** first test multiple recipients and interrupted visits; bound work and preserve independent messaging failure handling.
- **False recovery or compatibility loss:** first test retained transfers, bad candidates and missing originals; retain original verification and retryable gaps.

## Shared component inventory

- Extend membership/recipient-key resolution, visit coordination and sync work/upload/retrieval/acknowledgment APIs; reuse bundles and private sync storage.
- Reuse canonical reading/rendering across list previews, conversation history, groups and legacy mailboxes; preserve Inbox/Sent redirects.
- Preserve send/conversation/list/unread/read contracts, navigation indicators and conversation/profile/aggregate-user composers. Add no recovery UI.

## Simple user flow

1. Approve the recipient's new key; older incoming messages may remain unavailable.
2. A sender with verified access visits and contributes automatically.
3. The recipient returns to Messages and reads verified originals; remaining gaps stay retryable.

## Success criteria

Recover 31 incoming messages across multiple batches with recipient donors absent and sender closed before reading. Verify multiple recipients, partial donors, existing targets, stale-key sends, reload, revocation, invalid transfers and compatibility. Preserve UX/privacy regressions and ordinary messaging during sync failure; unavailable originals remain unavailable.

Steps 1–2 approved. Continue with [Step 3](./private_message_sender_history_sync_step3_development_plan.md). Implementation requires Approved Step 3.
