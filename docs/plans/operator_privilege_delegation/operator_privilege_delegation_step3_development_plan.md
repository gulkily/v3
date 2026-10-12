> **Feature plan:** [Step 1](./operator_privilege_delegation_step1_solution_assessment.md) · [Step 2](./operator_privilege_delegation_step2_feature_description.md) · [Step 3](./operator_privilege_delegation_step3_development_plan.md) · [Step 4](./operator_privilege_delegation_step4_implementation_summary.md)

## Completion Contract
- Entry/outcome: From classic/Forte user pages, an authenticated operator signs a username-wide grant; approved recipient keys gain existing operator capabilities and can delegate.
- Recovery: Preview/revoke individual grants; alternate paths preserve access, regranting restores unrevoked descendants, and root seed commands recover authority. Pending keys remain excluded; protected-account key approvals remain restricted during suspension.
- Deployment/release: Rebuild derived state from canonical records; verify live/rebuilt parity and both site flows locally. No remote deployment is authorized. Release requires all stages/checks; verify the same flows after deployment before operational handoff.

## Key Risks
- **High risk: account/key escalation.** Impact: wrong keys inherit authority. Validate username/bootstrap/root binding and every approval/invitation entry first; centralize eligibility and preserve protection through suspension.
- **High risk: graph/recovery divergence.** Impact: unauthorized or missing access. Validate cycles, alternate paths, historical authorization and replay ordering; use one derivation contract for live/rebuilt state and fail closed while stale.
- **High risk: stale revocation preview.** Impact: unexpected cascading loss. Validate intervening grants/key changes; bind confirmation to current effects and recheck under the write lock. Unresolved risks block dependent stages.

## Stage 1
- Goal: Fix authority contracts and risk fixtures (~1 hour).
- Dependencies: Approved Step 3; feature branch/planning commit.
- Expected changes: Define canonical account/bootstrap binding, root anchors, signed action ordering and grant-specific revocation; plan `deriveOperatorState(records, profiles, seeds): array`.
- Verification approach: Fixture cases for multi-key accounts, alternate paths, cycles, replay and restoration.
- Risks or open questions: Historical validity could diverge on rebuild; validate acceptance evidence and deterministic ordering, resolve before Stage 2.
- Canonical components/API contracts touched: Canonical post/seed records, ProfileRepository, shared operator-state contract.

## Stage 2
- Goal: Make recorded authority agree with operator access (~1 hour).
- Dependencies: Stage 1.
- Expected changes: Shared derivation and projection; conceptual operator-state/read-model additions; full/incremental/seed refresh wiring and cache invalidation; replace root-only authorization/listing with effective authority.
- Verification approach: Live/rebuilt parity; approved versus pending keys; Tools, feature flags, statistics and Codebase State access.
- Risks or open questions: Stale projections permit access; exercise refresh failure and enforce fail-closed/rebuild recovery before writes.
- Canonical components/API contracts touched: ReadModelBuilder/IncrementalReadModelUpdater, Application, ToolsPageController/ToolsPageSupport, LocalWriteService.

## Stage 3
- Goal: Protect operator-account key admission (~1 hour).
- Dependencies: Stage 2.
- Expected changes: Shared eligibility for direct/prepared approval, invitation redemption and pending-key controls, independent of ordinary approval settings; retain suspended-account protection.
- Verification approach: Ordinary member denied; approved own-account key/current operator allowed; invitations and suspended accounts covered.
- Risks or open questions: Alternate entry bypasses protection; inventory all membership writers and enforce at final acceptance.
- Canonical components/API contracts touched: IdentityApprovalAndInvitationApiController, LocalWriteService, approval/invitation presentation.

## Stage 4
- Goal: Complete signed grant from user page to usable access (~1 hour).
- Dependencies: Stages 2–3.
- Expected changes: Tagged grant preparation/finalization, exact signer/account/target binding, shared browser control on classic/Forte user pages, and attributed activity.
- Verification approach: Normal grant and onward delegation; reject tampering, expiry, wrong signer, pending key and lost authority.
- Risks or open questions: Arbitrary tags confer access; validate signature and authorization in canonical acceptance and reconstruction.
- Canonical components/API contracts touched: Signed bootstrap replies, shared signing, user/profile templates/controllers, activity projection.

## Stage 5
- Goal: Complete previewed grant revocation (~1 hour).
- Dependencies: Stage 4.
- Expected changes: `previewOperatorRevocation(grantId): array`; signed grant-specific revocation by any current operator, shared impact confirmation, root protection and replay rejection.
- Verification approach: Cascading loss, alternate paths, disconnected cycles and changed-state confirmation rejection.
- Risks or open questions: Confirmation races; recompute effects/authority under write lock and require renewed review.
- Canonical components/API contracts touched: Operator action API/writer, grant provenance/control, browser signing and shared derivation.

## Stage 6
- Goal: Complete restoration and failed-refresh recovery (~1 hour).
- Dependencies: Stage 5.
- Expected changes: New incoming grants restore unrevoked descendants; retain explicit revocations; connect seed/rebuild repair and truthful persisted-but-refresh-failed responses.
- Verification approach: Bob/Carol restoration, deeper descendants, revoked edges, seed retry and stale-state failure/recovery.
- Risks or open questions: Recovery resurrects revoked edges; compare canonical rebuild with live recovery before handoff.
- Canonical components/API contracts touched: Approval seed CLI, projection repair/stale marker, operator action responses, recovery runbook.

## Stage 7
- Goal: Align every user-facing authority surface (~1 hour).
- Dependencies: Stage 6.
- Expected changes: Shared role/provenance across directories, username/profile pages, summary/detail panes, profile API, pending/account-key views, classic/Forte activity and commit manifests; distinguish root attribution.
- Verification approach: Presentation matrix; hidden/disabled actions agree with API eligibility; signing cancellation and stale-preview retry.
- Risks or open questions: Theme drift hides effects; reuse canonical components and inspect both normal flows.
- Canonical components/API contracts touched: ProfilePageController/ForteProfileController/ApiTextController, user/profile/activity templates and shared controls.

## Stage 8
- Goal: Verify release contract and record handoff (~1 hour).
- Dependencies: Stage 7; all risks resolved.
- Expected changes: User/operator documentation and recovery guidance; finish summary with verification, deployment/rebuild procedure and rollback limitations.
- Verification approach: Targeted authorization/approval/invitation/presentation/recovery tests, then `php tests/run.php`; local classic/Forte grant → delegate → revoke → restore smoke flows and reconstruction parity.
- Risks or open questions: Rollback loses enforcement; retain canonical history, document older-code incompatibility and avoid rollback that exposes protected accounts.
- Canonical components/API contracts touched: Test runner/regression fixtures, operator docs/runbook, Step 4 summary.

Each stage must finish verification and its summary/commit before the next. Eight stages target one day; if a stage exceeds ~1 hour or new contracts are needed, stop and rescope through Steps 2/3 before implementation continues.
