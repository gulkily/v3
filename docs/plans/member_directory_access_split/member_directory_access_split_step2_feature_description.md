> **Feature plan:** [Step 1](./member_directory_access_split_step1_solution_assessment.md) · [Step 2](./member_directory_access_split_step2_feature_description.md) · [Step 3](./member_directory_access_split_step3_development_plan.md) · [Step 4](./member_directory_access_split_step4_implementation_summary.md)

Debug mode: enabled

## Problem

Users mixes finding members and their contributions with reviewing account access. Separate these journeys so members browse through Users and reviewers manage access through Tools → User Access.

## User stories

- As a member, I want a directory and public member pages so that I can find people, read their contributions, and use existing messaging.
- As an authorized reviewer, I want pending users and account key review under Tools so that I can inspect the correct identity, approve access, and confirm the result.

## Core requirements

- Keep Users and its standard member subpages focused on approved member browsing, activity, public profile information, and existing messaging. Retain concise approval status and approver attribution; remove pending-user filters, approved/unapproved key inventories, detailed approval history, public-key diagnostics, and approval controls from these pages.
- Provide Tools → User Access with pending approvals and access to existing accounts' profile/key records, including additional pending keys for an approved username. Review details include identity, approval history/evidence, and existing policy consequences. A contextual User Access link may connect a public profile to its corresponding management record.
- Apply existing visibility and action authorization to each destination; moving a page must not grant additional access or hide existing public evidence. Reuse existing signed approval behavior, preserve the exact target identity, and display success, failure, and actionable retry feedback in the management flow.
- Preserve public member URLs. Old pending-directory links lead to the new management destination with authorization enforced. Old key-history fragments and pagination retain a visible path to the corresponding history; approval callbacks and evidence links remain usable. Direct visits, normal navigation, and cached/static serving must agree.
- Use short labels and contextual help; retain essential consequences and recovery beside actions. Record affected copy cleanup in the existing UI copy audit checklist.

## Delivery scope and completion boundary

Work type: application change. Includes the standard directory and member/profile subpages, Tools management pages, shared history/review presentation, navigation, compatibility, and focused verification. Forte retains its current combined directory/access presentation, pending filter, detail API, and management information; shared changes must preserve its behavior. Excludes new access powers, policy changes, personal account setup redesign, and schema changes.

Release when Users → member → contributions/messaging and Tools → User Access → identity review → existing approval → confirmed outcome both work. A failed or unauthorized approval provides the existing relevant recovery action without losing the target; empty queues and unavailable records remain understandable.

## Risks

- **Wrong account/key approved:** usernames can contain several identities. Validate existing queue/review target handling before planning stages; preserve identity-specific review and authoritative policy checks.
- **Lost history or recovery links:** history uses member URLs, fragments, and pagination. Inventory links and approval callbacks before defining route changes; retain compatibility paths and verify full history navigation.
- **Authorization or stale-page regression:** public history and restricted queues have different audiences, and host/static routing can bypass assumptions. Map current visibility and serving paths before implementation; preserve current permissions and test both rendering paths.

## Shared component inventory

- Standard Users/pending templates: extend existing directory components to show members only; reuse queue data and actions in Tools. Forte filters, list/detail panes, and detail API remain unchanged.
- Standard username and individual profile pages: reuse public content and messaging; move access sections into management views. New Tools list/detail presentation is needed for the distinct management journey, with shared review/history components.
- Shared approval-history partial, history service, root/source/post evidence pages: extend shared history navigation for Tools while retaining Forte-compatible defaults and links; reuse canonical evidence and existing visibility.
- Pending-approval browser actions, signed preparation/submission APIs, profile approval action and callbacks: reuse authoritative contracts and adapt destinations/feedback.
- Tools registry/subnavigation, standard navigation, host routing, static generation/invalidation: extend canonical navigation and serving paths for consistent entry and freshness.

## Simple user flow

1. Open Users, select a member, and browse contributions or message them.
2. Open Tools → User Access, select a pending identity or an existing account's key record, and inspect its history and consequences.
3. Complete an authorized approval; see the confirmed status or resolve the displayed failure and retry.

## Success criteria

- The standard directory and its public subpages contain no pending queues, key inventories/history, or approval controls; member activity, attribution, and messaging still work.
- Forte retains its current filters, member/key details, and access information after shared-component changes.
- Tools exposes existing-account key review and pending approvals with the same authorization and approval outcomes as before.
- Legacy management links, history pagination, approval feedback, and serving freshness pass focused regression checks.

## Approval

Revised Step 2 approved by the user after excluding Forte. Implementation remains gated on `Approved Step 3`.

## Request record

- Request: Leave Forte out; its technical audience does not need the same separation.
- Disposition and reason: Changed scope; apply separation only to standard pages and preserve Forte behavior in shared components.
- Approval status: Revised Step 2 approved by the user.
- Outcome: Forte redesign deferred with no separate implementation planned. Step 3 revised to cover standard pages with Forte compatibility checks only.
