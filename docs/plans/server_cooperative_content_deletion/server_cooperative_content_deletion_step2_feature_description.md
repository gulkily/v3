> **Feature plan:** [Step 1](./server_cooperative_content_deletion_step1_solution_assessment.md) · [Step 2](./server_cooperative_content_deletion_step2_feature_description.md) · [Step 3](./server_cooperative_content_deletion_step3_development_plan.md) · [Step 4](./server_cooperative_content_deletion_step4_implementation_summary.md)

Debug mode: enabled

# Server Cooperative Content Deletion Step 2 Feature Description

## Problem

Authors cannot retract their own posts; only operators can remove records, and that leaves content in Git history with no author authority. Authors need a self-service retraction that the server honors and that restores or imports cannot undo.

## User Stories

- As an author, I want to retract my post so that it stops being shown on the site.
- As an author, I want to see whether my retraction is pending, complete, or partly failed so that I know what was actually removed.
- As a reader, I want replies to a retracted post to remain in place behind a placeholder so that the conversation stays understandable.
- As an operator, I want retractions kept distinct from moderator removals so that author intent and moderation are not confused.

## Core Requirements

- Only a request verifiably signed by the post's author can retract it.
- Retracted content is no longer displayed in pages, search, feeds, APIs, or current exports.
- A minimal tombstone, without the deleted prose, prevents import or restore from resurrecting the post.
- Replies are preserved; a deleted-post placeholder replaces the retracted post.
- The UI states honestly what has and has not been removed (history, backups, copies held by others).

## Delivery Scope

- Work type: application change.
- First slice is suppression (Step 1 Option A). Physical purge of Git history, artifacts, and backups is a later slice under the same tombstone state.
- Out of scope: cross-instance propagation, anonymous-post retraction without a signing identity, lost-key recovery.

## Completion Boundary

- Entry: the author opens their own post and chooses retract.
- Outcome: the signed request is verified, the post is suppressed everywhere, a tombstone is recorded, and the author sees the resulting status.
- Recovery: a failed or partial retraction shows its state and can be retried; an operator can inspect it.
- Release: a re-import of the original archive leaves the post suppressed.

## Risks

- **Weak promise misread as erasure:** impact is false user assurance; validate with copy review in Step 3; mitigate with explicit wording on retained history.
- **Resurrection through import, restore, or static artifacts:** impact is deleted content reappearing; validate with an import/restore test early; mitigate with the tombstone checked on every ingest path.
- **Authority edge cases (multiple or lost keys):** impact is wrongful or blocked retraction; validate by enumerating key cases first; mitigate by limiting the first slice to the post's signing key.
- **Thread-root retraction:** impact is broken subject, navigation, or reply structure; validate against thread and board views; mitigate with a placeholder that keeps the subject-less root.

## Shared Component Inventory

- Post and thread rendering (`templates/pages/thread.php`, `post.php`, `message.php`, `board.php`, `tag.php`, `profile.php`, `username.php`): extend the canonical post/event block with a deleted placeholder state rather than forking markup.
- Activity and paned views (`activity.php`, `forte_*.php`, `partials/paned_activity_*`): reuse; must honor the tombstone.
- Search, feeds, and APIs: reuse the existing read model; apply the tombstone filter there.
- Operator `delete-record` command: unchanged; remains the moderator mechanism, separate from author retraction.
- Retract action and status display: new, since no author-facing deletion surface exists.

## Simple User Flow

1. The author opens their post and selects retract.
2. The browser signs a deletion request for that post.
3. The server verifies authority and records a tombstone.
4. The server suppresses the content and rebuilds the read model and artifacts.
5. The author sees status, and others see the placeholder.

## Success Criteria

- An author retraction of their own post hides it on every listed surface.
- A non-author request is rejected and changes nothing.
- Re-importing the original archive does not restore the post.
- Replies to a retracted post remain visible under the placeholder.
- No retracted prose appears in current pages, search, feeds, APIs, or exports.
