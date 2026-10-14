> **Feature plan:** [Step 1](./operator_record_deletion_step1_solution_assessment.md) · [Step 2](./operator_record_deletion_step2_feature_description.md) · [Step 3](./operator_record_deletion_step3_development_plan.md) · [Step 4](./operator_record_deletion_step4_implementation_summary.md)

Debug mode: disabled

## Original Query

As an operator-privileged user, I want to have the ability to delete records that are not my own. Please write Step 1.

## Clarified Direction

“I want the same method as the ‘Delete’ button for current users’ self-delete.” Self-delete means users deleting their own posts. Extend this existing method to operators acting on other users’ posts, preserving its auditability. Label the action “Retract post” and place it with less frequently used post actions.

## Current Behavior

The [existing Delete flow](../../reference/author_post_retraction.md) creates a timestamped post-retraction record, verifies the author's signature, and commits the record and signature to Git. It suppresses the post on site views, keeps replies under a placeholder, and supports retry after a partial refresh failure. Original records, Git history, repository downloads, backups, and previously saved copies are not erased. Both submission and suppression rules currently require the post's author; there is no operator override.

## Problem Statement

Operators need to use the same auditable Delete action on other users’ posts without changing what deletion does or misattributing the action to the author.

## Option A Shared Retract action in an overflow menu

Place “Retract post” in a per-post “…” menu with the accessible label “More actions” for authors and eligible operators, using the existing retraction, confirmation, and recovery flow.

- **Pros:** Keeps one retraction experience; makes the label accurate and moves an infrequent action away from frequent controls.
- **Cons:** Requires coordinated authorization and attribution changes across submission, stored evidence, and suppression during rebuilds; a button change alone is insufficient.

## Option B Separate operator entry using the same method

Provide an operator-specific deletion entry point that uses the existing post-retraction mechanism and recovery flow.

- **Pros:** Makes operator actions distinct while preserving deletion behavior.
- **Cons:** Adds an interface and permission surface without a requested benefit; still requires the same underlying authorization and attribution changes.

## Recommendation

Choose **Option A**, extending the existing signed retraction mechanism through a shared per-post “…” menu.

- **Placement and wording:** Rename Delete to “Retract post” and place it inside a “…” menu everywhere the retraction action is currently available, including supported thread-root cards, reply cards, and individual post pages, for both authors and operators. Give the menu button the accessible label “More actions”. Show it only to eligible users; keep the menu accessible by keyboard and touch. Confirmation explains suppression and retained copies; feedback and Retry remain easy to find. Use “retracted by its author” or “retracted by an operator” as appropriate.
- **Authorization:** Preserve current author deletion. Permit an approved operator to delete another user's supported post, using existing operator eligibility checked when the action takes effect. Reject ordinary cross-user requests, forged actions, and stale operator authorization.
- **Auditability:** Retain the target, timestamp, actual actor, signature, and Git evidence. Operators sign with their own identity; preserve the distinction between operator deletion and author deletion in both evidence and displayed wording. Existing author retractions remain valid.
- **Consistent outcome:** Apply the decision consistently across site views, source access, rebuilds, imports, and refresh retries. Step 2 must settle how accepted operator authority is retained so later privilege changes do not silently resurrect posts or invalidate historical attribution.
- **Scope:** Cover the posts and entry surfaces already supported by Delete, retaining the exclusions for identity, approval, and invitation posts. Preserve suppression and reply placeholders. Defer other record types, bulk actions, mandatory reasons, additional moderation screens, physical erasure, and cross-instance enforcement.
- **Vertical slice:** Operator opens another user's supported post → … → Retract post → confirms → signs → sees the existing deletion outcome with accurate attribution or a recoverable failure. Verify consistent label and placement across every existing retraction entry point, author parity, ordinary-user denial, operator success, durable audit evidence, and rebuild/reimport consistency. Keep within one day or eight stages.

## Review

**Approved Step 1** received. Option A, including the shared “…” menu and “Retract post” label at every existing retraction entry point, is the basis for Step 2.
