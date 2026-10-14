> **Feature plan:** [Step 1](./instance_import_approval_review_step1_solution_assessment.md) · [Step 2](./instance_import_approval_review_step2_feature_description.md) · [Step 3](./instance_import_approval_review_step3_development_plan.md) · [Step 4](./instance_import_approval_review_step4_implementation_summary.md)

Debug mode: disabled

## Original Query

When we import another instance, we end up with a lot of unapproved accounts.
This is because root approvals are gone.
We need to figure out a way to make this process smoother.
Write Step 1 of `docs/railspike/RAILSPIKE.md`.

## Problem Statement

Instance imports leave source-approved identities awaiting repetitive local approval because both root seeds and approval actions are intentionally excluded.

## Current Behavior

- The [import runbook](../runbooks/instance_content_import.md#coverage-and-results) and [import planner](../../src/ForumRewrite/Import/ContentImportPlanner.php) exclude approval seeds, approval posts, and invitations; restoring roots alone cannot restore the missing approval chains.
- [Approval policy](../../src/ForumRewrite/Security/ApprovalPolicy.php) treats seeded identities as operators, so copying source seeds also grants local operator privileges.
- Imported signatures preserve source records but do not independently authenticate source authorship; source trust must be a deliberate destination decision.

## Option A: Explicitly Import Source Trust Roots and Approval Chains

Offer an opt-in mode that adopts selected source roots and their approval chains, reproducing source trust in the destination.

- **Pros:** Minimizes manual work for a deliberate migration; retains source approval relationships.
- **Cons:** Grants local operator privileges to adopted roots; must also handle missing approval actions, invitation-derived approvals, and conflicts with local account policy. Broad scope for a first slice.

## Option B: Review Imported Identities and Apply Local Approvals in Bulk

Give the destination operator a review of imported identities that remain unapproved, then apply ordinary local approvals to the selected identities in one operation.

- **Pros:** Removes repetitive approvals; retains destination control and approver attribution; avoids granting root privileges to imported users; can reuse existing approval records without a schema change.
- **Cons:** Requires operator selection; imported presence does not prove source approval; large imports need clear fingerprints, username collisions, and per-identity outcomes.

## Option C: Import Approval Chains Anchored in Locally Approved Identities

Preserve source approval actions and activate only chains reachable from identities already approved locally, with an explicit local approval needed for any new anchor.

- **Pros:** Reuses existing trust relationships; avoids copying root seeds; can reduce work on later imports.
- **Cons:** Local approval becomes delegation to source approval history; collision policy and signature verification need assessment; invitations require separate handling; disconnected chains remain unapproved.

## Option D: Expose Source Root-Approved Usernames to Operators for Individual Approval

Carry the source instance's list of root-approved usernames into the destination as advisory, non-authoritative data. Let destination operator users view that list and approve each listed identity individually through the ordinary local approval path.

- **Pros:** Gives operators a short, high-signal list to start from instead of every unapproved account; keeps each approval a deliberate, attributed local decision; grants no root or operator privileges to imported users; no need to replay source approval actions.
- **Cons:** The root list is excluded from import today, so it must be carried as separate metadata with clear provenance; a listed username is a source claim, not proof, so username/key collisions must be shown per identity; approving one by one is slower than bulk on large imports; operators beyond the seeded ones need access to this view, which widens who can see source trust data.

**Open questions for discussion:**

- Should the list contain only direct root seeds, or also identities approved by roots in the source?
- Is the view limited to operators, and does it live alongside the existing approval screens or in a separate import review page?
- Should individual approval be the only action, or should this feed Option B's bulk approval as a pre-selected set?
- How is the list refreshed or retired after later imports, and how is already-approved or since-changed-key status shown?

## Recommendation

Choose **Option B** as the first slice: import content, review the resulting unapproved identities, select by fingerprint, and issue destination approvals through an existing local operator. Show username/key collisions and approval outcomes explicitly; repeat or interrupted runs should skip completed approvals and identify what remains.

This is a viable vertical slice from the existing import command through review, local approval, and refreshed account status. Keep source approval claims distinct from local approval decisions. Leave source-root adoption, historical-chain replay, and invitation transfer for separate assessments. Validate early that the existing approval path can support an operator-authorized batch while retaining account policy and recoverable per-identity results.
