> **Feature plan:** [Step 1](./operator_record_deletion_step1_solution_assessment.md) · [Step 2](./operator_record_deletion_step2_feature_description.md) · [Step 3](./operator_record_deletion_step3_development_plan.md) · [Step 4](./operator_record_deletion_step4_implementation_summary.md)

Debug mode: disabled

## Problem

Only authors can currently retract their posts. Operators need the same signed, auditable retraction capability for other users’ posts, with accurate attribution and an action placed among infrequently used controls.

## User Stories

- As an operator, I want to retract another user's post so that I can remove it from this site's served content.
- As an author, I want “Retract post” in the “…” menu so that its purpose is clear and it stays out of frequent actions.
- As a reader, I want retained replies and accurate attribution so that I understand the conversation and who retracted the post.

## Core Requirements

- Preserve author authorization; additionally permit approved operators under the existing operator policy. Verify the actual actor's signature and authority at acceptance; reject ordinary cross-user requests and protected identity, approval, or invitation posts.
- Everywhere retraction currently exists, use **… → Retract post** for both roles. Label the menu “More actions” for accessibility; support keyboard and touch, confirmation, visible progress, and recoverable errors.
- Reuse current suppression behavior across pages, feeds, search, APIs, raw source access, and generated artifacts. Preserve replies and explain retained repository/history, backups, and saved copies.
- Retain the target, timestamp, actual signer, action classification, and evidence of operator authority at acceptance. Keep existing author retractions valid; accepted operator retractions survive privilege removal, rebuilds, and reimports. Invalid or unauthorized imported actions must not suppress content.
- Distinguish “retracted by its author” from “retracted by an operator” wherever placeholders appear. Keep signed evidence and the existing Activity Commits entry available without exposing retracted prose through normal served content.

## Delivery Scope

Application change covering existing retraction surfaces and supported posts with a signing author. Exclude new retraction entry points, other record types, bulk actions, mandatory reasons, restoration UI, physical erasure, cross-instance enforcement, and a new dedicated activity-feed event.

## Completion Boundary

Entry is an eligible user opening a supported post's “…” menu. Completion is a signed, attributed retraction and consistent suppression; partial refresh failures offer visible Retry without a duplicate action. Release requires author parity, operator success, unauthorized denial, and persistence after privilege removal and reimport.

## Risks

- **Authority drift:** Wrongful suppression or resurrection; validate acceptance-versus-rebuild authority first; retain verifiable acceptance evidence and reject untrusted actions.
- **Attribution mismatch:** Operator action described as author intent; inventory placeholders early; use shared actor-aware wording and preserve legacy author interpretation.
- **Hidden recovery or stale content:** User cannot finish a partial action; exercise menu, refresh failure, and reimport paths early; keep feedback/retry visible and reuse suppression rules.

## Shared Component Inventory

- Extend the shared retraction control used by supported thread-root cards, reply cards, and individual post pages; reuse signing and status handling with a shared “…” menu.
- Extend existing prepare/finalize retraction APIs and operator eligibility; preserve ordinary author requests.
- Extend the canonical retraction evidence and shared suppression rules used by rebuilds, imports, read APIs, source routes, and artifact refresh.
- Extend shared notices across post/root cards, QDB placeholders, and Forte paned views; preserve listing titles. QDB and Forte gain accurate notices, with no new action placement.
- Reuse Activity's Commits view and source evidence for audit inspection.

## Simple User Flow

1. Author or operator opens **… → Retract post** and reviews confirmation.
2. Browser signs; server verifies authority and records the action.
3. Site shows the attributed placeholder and retained replies, or visible recovery instructions.

## Success Criteria

Every existing action surface uses the new label/menu; eligible operator and author actions succeed; ordinary cross-user and forged actions fail. Attribution remains correct, original author records remain effective, replies survive, and accepted retractions persist through retry, rebuild, privilege removal, and reimport.

## Review

**Approved Step 2** received; requirements and scope are the basis for Step 3.
