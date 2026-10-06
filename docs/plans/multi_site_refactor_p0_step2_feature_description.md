# Multi-Site Refactor P0 — Step 2: Feature Description

> **Feature plan:** [Step 1](./multi_site_refactor_p0_step1_solution_assessment.md) · [Step 2](./multi_site_refactor_p0_step2_feature_description.md) · [Step 3](./multi_site_refactor_p0_step3_development_plan.md) · [Step 4](./multi_site_refactor_p0_step4_implementation_summary.md)

## Problem

Profile identity is only partly declared in `SiteProfileRegistry`; browser/offline identifiers and the profile-specific static-output rule are duplicated elsewhere. Adding a site therefore requires coordinated edits and can accidentally couple presentation to instance state.

## User Stories

- As a maintainer, I want one validated profile descriptor so that a site's presentation identity is reviewed in one place.
- As an operator, I want every profile to resolve a deterministic presentation-output root so that static artifacts do not collide.
- As a developer, I want absent or invalid profile selection to recover to Zenmemes so that existing deployments retain their behavior.

## Core Requirements

- The canonical descriptor declares display identity, default/permitted themes, browser/offline namespace, editorial-content key, and enabled experience keys for Zenmemes, Chouse, and QDB.
- Descriptor validation rejects duplicate or browser-unsafe identifiers and active-profile resolution falls back safely to Zenmemes when selection is absent or unknown.
- One shared resolver supplies profile-derived presentation/static-output roots to all current default-path consumers; explicit operator-provided paths retain precedence.
- Zenmemes retains its current unsuffixed default output path; other profiles resolve distinct defaults.
- Repository, database, identity, approval, and content state remain instance-owned and are not added to profile metadata.

## Delivery Scope

- Work type: application change.
- Included outcome: the P0 profile contract, validation/fallback behavior, shared presentation-path resolution, and focused automated coverage.
- Excluded outcome: QDB experience extraction, named presentation slots, browser-storage/cache/manifest migration, and any repository/database/identity/approval/content-state partitioning.

## Completion Boundary

- Normal entry: a request or operational command selects a known, absent, or unknown site profile.
- End-to-end outcome: it receives a validated descriptor and the same profile-derived default presentation root across web and operational consumers.
- Required recovery: absent, unknown, or invalid active selection uses the Zenmemes descriptor and legacy root without changing explicit path overrides.
- Release condition: all three declared profiles validate, their default roots are isolated except for Zenmemes' preserved legacy root, and shared instance state has no profile ownership.

## Risks

- **A browser identifier can be unsafe or collide.** Earliest validation: descriptor validation tests. Mitigation before Step 3: define one browser-safe identifier rule and require uniqueness.
- **A path migration changes Zenmemes' existing artifacts.** Earliest validation: default-root tests for the fallback profile. Mitigation before Step 3: make legacy unsuffixed Zenmemes output an explicit compatibility condition.
- **Profile metadata absorbs instance configuration.** Earliest validation: review every proposed field by ownership. Mitigation before Step 3: reject repository, database, identity, approval, and content fields from the descriptor.

## Shared Component Inventory

- **Profile selection:** extend the canonical `SiteProfileRegistry`; retain `SiteConfig` as its existing name consumer rather than adding a second active-profile source.
- **Presentation-path consumers:** extend a new shared resolver for `public/index.php`, `scripts/task_queue.php`, `scripts/publish_offline_snapshot.php`, and `scripts/diagnose_offline_reading.php`; each keeps its explicit environment/command override behavior.
- **Profile contract coverage:** extend `SiteProfileRegistryTest` and the existing command/application smoke coverage; no new user-facing UI or API is needed.

## Simple User Flow

1. A web entry point or operational command resolves the selected profile.
2. The registry returns its validated descriptor, or the Zenmemes fallback.
3. The shared resolver supplies the default presentation root unless an explicit path override is present.
4. The selected profile's presentation artifacts remain isolated without changing instance data ownership.

## Success Criteria

- Every profile declares all P0 contract fields and passes uniqueness/browser-safety validation.
- Unset and unknown site selection both resolve to Zenmemes and preserve its existing default root.
- All four current default-path consumers use one profile-derived resolution rule.
- Chouse and QDB default presentation roots are distinct from Zenmemes and each other.
- No P0 change assigns repository, database, identity, approval, or content state to a profile.
