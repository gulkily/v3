> **Feature plan:** [Step 1](./private_messaging_live_navigation_step1_solution_assessment.md) · [Step 2](./private_messaging_live_navigation_step2_feature_description.md) · [Step 3](./private_messaging_live_navigation_step3_development_plan.md) · [Step 4](./private_messaging_live_navigation_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Saving the messaging flag updates its settings row but leaves Messages navigation reflecting page-load availability. Operators should see navigation agree with the confirmed setting and their current eligibility without reloading.

## User Stories

- As an operator, I want Messages to appear/disappear after a successful toggle so that navigation immediately reflects available features.
- As an operator, I want clear recovery when navigation cannot refresh so that I know whether my setting was saved.

## Core Requirements

- After a confirmed messaging toggle or reset, update Messages in place on the current Feature Flags page, without another user action or a full-page reload. Preserve focus, scroll, and other settings; pending, rejected, or locked changes must not imply success.
- Show exactly one Messages link only when the server's effective flag and current-user eligibility permit it, using existing authentication, approval, and members-only rules. Preserve canonical position, styling, accessibility, and destination; server access enforcement remains authoritative.
- Disabling removes the link and stops associated messaging activity. Re-enabling restores a working link, refreshes unread status, and resumes normal bounded history sync without duplicate activity, even when the page initially loaded disabled. Clear obsolete disabled feedback; unread failures show the existing unavailable state rather than an incorrect count or endless loading.
- Repeated off/on changes converge on the latest confirmed availability; delayed responses from earlier states must not undo it. Preserve messages, drafts, pending attempts, and durable progress.
- If saving succeeds but navigation refresh fails, retain the confirmed setting and explain that navigation is stale, with an explicit reload recovery action. Do not repeat the signed setting change as recovery. A known disabled state must still stop messaging activity.

## Delivery Scope and Completion Boundary

**Work type: application change.** Deliver Feature Flags → confirmed toggle → immediate eligible navigation → open Messages or toggle back, including refresh-failure recovery. Limit live updates to this page's messaging control; exclude broadcasting across tabs/users, other flags, and automatic restoration of open conversations/composers elsewhere. Release requires verified initially-enabled and initially-disabled journeys, repeated toggles, eligibility/override checks, and failure recovery.

## Risks

- **Navigation and eligibility diverge:** inspect current server navigation rules before Step 3; reuse them instead of independently inferring access in the browser.
- **Re-enable remains stuck or duplicates background work:** review current startup and disabled-state behavior before Step 3; define repeatable stop/resume behavior and reject obsolete results.
- **Partial success misleads operators:** trace save/refresh failures before Step 3; distinguish saved-setting feedback from navigation failure and retain reload recovery.

## Shared Component Inventory

- Feature Flags toggle/reset controls, status feedback, signed prepare/finalize flow, and effective-value/override evaluation: extend existing behavior.
- Server navigation eligibility, shared navigation partial, and theme layouts: reuse canonical Messages presentation; no parallel navigation design.
- Shared messaging availability/disabled notice, unread badge and refresh, and background history sync: extend lifecycle behavior for this page.
- Messages destination and protected messaging APIs: retain existing access/recovery behavior; operator and messaging documentation: explain immediate updates and reload fallback.

## Simple User Flow

1. Operator opens Feature Flags and toggles Private messages.
2. After confirmation, Messages appears/disappears in place according to eligibility.
3. Operator follows Messages or toggles back; if navigation refresh failed, reloads using the provided recovery action.

## Success Criteria

- Both initial states and repeated toggles produce zero or one eligible link without page reload or duplicate background activity.
- Failed/locked changes and obsolete responses never falsely grant navigation availability.
- Unread refresh, normal Messages entry, and saved-setting/navigation-failure recovery work; retained messaging data remains intact.
