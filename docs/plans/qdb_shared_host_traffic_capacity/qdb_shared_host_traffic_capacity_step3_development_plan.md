> **Feature plan:** [Step 1](./qdb_shared_host_traffic_capacity_step1_solution_assessment.md) · [Step 2](./qdb_shared_host_traffic_capacity_step2_feature_description.md) · [Step 3](./qdb_shared_host_traffic_capacity_step3_development_plan.md) · [Step 4](./qdb_shared_host_traffic_capacity_step4_implementation_summary.md)

# QDB Shared-Host Traffic Capacity — Step 3: Development Plan

## Completion Contract

- Normal entry: an anonymous, queryless visitor opens an allowlisted QDB landing or quote route.
- End-to-end outcome: Apache reads the active complete artifact directly; cookie/query/mutable requests still use PHP.
- Required recovery: withdrawing the active release or direct-static rule falls back to PHP without exposing a partial release.
- Deployment/external verification: host-approved external ramp test records static/fallback behavior, safe capacity, SLOs, and upgrade trigger on staging.
- Release condition: automated route/release checks and the approved staging evidence pass; any dynamic optimization is justified by measured before/after results.

## Key Risks

- **High risk: personalized content leak.** Early validation: cookie/query requests never match static routing. Mitigation: narrow allowlist and automated exclusion tests before host deployment.
- **High risk: broken release pointer serves errors at peak.** Early validation: remove/activate a release while exercising routes. Mitigation: file-existence-gated rewrite and PHP fallback.
- **High risk: shared-host load testing causes suspension.** Early validation: written host limit and small external ramp. Mitigation: stop thresholds, staging only, and no uncontrolled live test.
- Host blocks document-root release exposure. Early validation: staging probe for permitted rewrite/symlink/static-header behavior. Mitigation: retain existing PHP artifact path and stop before direct routing.

## Stage 1

- Goal: Make the active QDB release safely addressable as files and include static targets for landing, canonical quotes, and numeric quote links.
- Dependencies: Staging host capability decision for a document-root-reachable active release.
- Expected changes: Extend the canonical static-release publication/artifact layout and QDB artifact coverage; preserve atomic activation and invalidation semantics.
- Verification approach: Artifact/release tests prove required QDB files, numeric-link parity, atomic activation, and missing-release fallback.
- Risks or open questions:
  - Impact: a partial public release could show stale or incomplete content.
  - Early warning / validation: assert all route assets before activation and exercise withdrawal.
  - Mitigation: activate only complete releases through the existing publisher.
- Canonical components/API contracts touched: `StaticArtifactBuilder`, `StaticArtifactReleasePublisher`, `StaticArtifactInvalidator`, `QdbExperience`, `QdbQuoteNumbers`.

## Stage 2

- Goal: Route only safe QDB artifact requests directly through Apache on a shared host.
- Dependencies: Stage 1’s addressable complete release and host rewrite capability.
- Expected changes: Extend `public/.htaccess` with a file-existence-gated, method/cookie/query constrained QDB allowlist; preserve the current asset and front-controller rules.
- Verification approach: Routing tests cover every allowlisted route and each exclusion; staging confirms direct-file responses plus PHP fallback after release withdrawal.
- Risks or open questions:
  - Impact: a broad rule could bypass authorization or dynamic behavior.
  - Early warning / validation: negative cases for cookies, queries, writes, account, search, random, and unknown paths.
  - Mitigation: exact route patterns, no catch-all physical-file bypass, and a documented rule rollback.
- Canonical components/API contracts touched: `public/.htaccess`, `public/router.php`, `FrontController`, `WebServerRoutingTest`.

## Stage 3

- Goal: Provide a repeatable, host-safe capacity measurement for the delivered static and PHP fallback paths.
- Dependencies: Stage 2 deployed to a production-shaped staging site and written host permission.
- Expected changes: Add a load-test profile and operator runbook covering route mix, staged concurrency, cold/warm cache state, cookie fallback, low-write isolation, metrics, stop conditions, and evidence capture.
- Verification approach: Validate the profile locally against a disposable instance; execute the external staged run only within host limits and retain its result.
- Risks or open questions:
  - Impact: an unrepresentative mix gives false confidence.
  - Early warning / validation: compare requested routes and cache states with QDB analytics/expected entry paths.
  - Mitigation: publish the route mix and rerun after material traffic changes.
- Canonical components/API contracts touched: static-release CLI, QDB public routes, production deployment runbook, load-test artifact.

## Stage 4

- Goal: Turn measured results into operating limits and fix only demonstrated dynamic hot spots.
- Dependencies: Stage 3 baseline and route-level evidence.
- Expected changes: Record launch SLOs, safe operating point, rollback/upgrade trigger, and—only if indicated—targeted read-model query/index or bounded-result improvements with no generic query-cache layer.
- Verification approach: Compare before/after query plans and load metrics for each remediation; confirm unchanged behavior and static delivery.
- Risks or open questions:
  - Impact: speculative caching can create stale, locked, or unbounded disk state.
  - Early warning / validation: no accepted change without a measured bottleneck and invalidation case.
  - Mitigation: prefer static artifacts; defer flat-file query caching unless direct static is unavailable and its key/invalidation contract is approved.
- Canonical components/API contracts touched: measured `ThreadRepository`/QDB read path, read-model indexes, static release, capacity runbook.
