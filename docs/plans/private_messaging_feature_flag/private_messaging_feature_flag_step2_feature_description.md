> **Feature plan:** [Step 1](./private_messaging_feature_flag_step1_solution_assessment.md) · [Step 2](./private_messaging_feature_flag_step2_feature_description.md) · [Step 3](./private_messaging_feature_flag_step3_development_plan.md) · [Step 4](./private_messaging_feature_flag_step4_implementation_summary.md)

Debug mode: disabled

## Problem

Operators cannot currently disable private messaging server-wide. One authoritative switch must govern messaging access and presentation while preserving history for later re-enablement.

## User Stories

- As an operator, I want to enable or disable messaging so that server availability follows my site's policy.
- As a member, I want clear unavailable feedback and recoverable drafts so that a messaging shutdown does not cause lost work or duplicate sends.

## Core Requirements

- Provide one site-wide messaging flag, **enabled by default**, in the existing Feature Flags controls. Reuse root-approved operator authorization, saved settings, effective-value/source display, and deployment override precedence and locking.
- When disabled, hide messaging navigation, badges, and composers on newly loaded pages. Deny all messaging page/API access, including legacy mailboxes, recipient keys, unread/read state, and history sync, without returning messaging data or performing messaging writes. Enabled mode retains current identity, approval, and key requirements.
- Apply the effective setting to each new request; requests already admitted while enabled may finish. Existing pages show a clear disabled state and stop further messaging activity upon encountering a disabled response; immediate notification across open tabs is not required.
- Retain stored messages, unread progress, history-sync state, and existing recoverable local drafts/pending attempts. Do not report uncertain sends as delivered or automatically resend them. After re-enablement and reload, users can resume existing recovery without duplicate delivery; already downloaded content cannot be retracted.

## Delivery Scope and Completion Boundary

**Work type: application change.** Deliver operator toggle → enforced member experience → re-enable and recover, with operator guidance. Exclude deletion, per-user exceptions, read-only mode, storage migrations, and new messaging capabilities. Release requires verified on/off/re-enabled behavior through ordinary navigation and direct requests, including a stale open composer.

## Risks

- **Missed surfaces permit access:** validate the route and entry-point inventory before Step 3; use the inventory below as the mandatory coverage boundary.
- **Disable during a send loses work or duplicates delivery:** review current draft/retry behavior before Step 3; preserve pending attempts and use the admitted-request boundary above.
- **Overrides make the switch misleading:** verify existing flag precedence and permission behavior before Step 3; reuse its source/lock presentation and test both override values.

## Shared Component Inventory

Extend existing canonical surfaces; no new settings page or parallel messaging components:

- Feature Flags page and signed setting-change flow: add the flag using existing controls.
- Shared navigation/unread badge; profile and aggregate-user composers: respect availability.
- Messages list, new-message recipient discovery, previews, pagination, conversation history/reply/read feedback, and legacy Inbox/Sent redirects: reuse shared messaging presentation and recovery with disabled feedback.
- Messaging APIs: send/retry, Inbox/Sent, conversation list/history, recipient keys, unread counts/read acknowledgments; history-sync work, transfers, and acknowledgments: enforce the same availability.
- Shared browser reader/composer, unread refresh, and background history-sync activity: stop on disabled feedback; API help and messaging/operator documentation: describe availability consistently.

## Simple User Flow

1. Operator opens Feature Flags, checks the effective value/source, and disables messaging if unlocked.
2. Members no longer see messaging entry points; direct requests and stale-page actions receive unavailable feedback.
3. Operator re-enables messaging; members reload, regain history, and resume drafts or check pending sends.

## Success Criteria

- Every inventoried messaging page/API denies access while disabled; newly loaded pages start no messaging activity.
- Unauthorized flag changes fail; deployment overrides remain authoritative and visible.
- Default-enabled behavior remains intact; disable/re-enable preserves history and recovery state, with no duplicate delivery in the interrupted-send journey.
