> **Feature plan:** [Step 1](./qdb_shared_host_traffic_capacity_step1_solution_assessment.md) · [Step 2](./qdb_shared_host_traffic_capacity_step2_feature_description.md) · [Step 3](./qdb_shared_host_traffic_capacity_step3_development_plan.md) · [Step 4](./qdb_shared_host_traffic_capacity_step4_implementation_summary.md)

# QDB Shared-Host Traffic Capacity — Step 2: Feature Description

## Problem

On a shared host, popular anonymous QDB reads must avoid PHP and SQLite where safely possible, and launch capacity must be measured rather than assumed.

## User Stories

- As an anonymous QDB visitor, I want common public quote pages to load from static files so that traffic spikes do not consume scarce PHP/SQLite capacity.
- As a returning or signed-in visitor, I want cookie-aware and query-driven behavior to remain correct so that static delivery does not show stale or generic state as personal state.
- As an operator, I want a repeatable, host-approved load test and a recorded safe operating point so that I can launch with evidence and know when to upgrade capacity.

## Core Requirements

- Apache directly serves a reviewed allowlist of anonymous, queryless QDB public-read artifacts, including the landing page and canonical quote pages, without invoking PHP.
- Requests with cookies or query strings, plus all write, account, reaction, search, random, and non-allowlisted routes, retain the existing front-controller behavior.
- Static release activation and invalidation stay atomic: a missing or withdrawn release safely falls back to PHP rather than exposing partial or stale artifacts.
- Fingerprinted assets retain long-lived immutable delivery; static HTML has an explicit freshness and rollback policy.
- A production-shaped external load test exercises static and PHP fallback paths, records latency/error/throughput limits, and establishes launch SLOs and an upgrade threshold.

## Delivery Scope

- **Work type:** application change.
- **Included:** static release publication reachable by Apache, conservative `.htaccess` routing, QDB static-route coverage, load-test harness/runbook, and measured SQL/query review for any dynamic route exposed as a bottleneck.
- **Excluded:** a generic query-cache layer, new hosting plan/CDN purchase, Apache vhost changes, and optimization without profiling evidence.

## Completion Boundary

- **Normal entry:** A visitor without cookies opens an allowlisted QDB route.
- **End-to-end outcome:** Apache returns the active static artifact directly; cookie/query-bearing and mutable requests continue through PHP unchanged.
- **Recovery:** Invalid, absent, or withdrawn static artifacts bypass the direct path and are rendered by PHP until a complete release is activated; the documented rollback disables direct-static routing.
- **Release condition:** Host-approved staging load evidence meets recorded SLOs with headroom, static/fallback routing is verified, and the runbook names the capacity and upgrade trigger.

## Risks

- **Host cannot safely expose the active release:** validate `.htaccess`, symlink/path, and static-header behavior on staging before route work; retain PHP fallback and direct-static rollback.
- **Personalized or stale content is served as public:** validate cookie/query exclusions and write invalidation before load testing; allowlist only generic public artifacts.
- **Load test harms a shared account:** obtain host limits and use an external staged ramp with a stop threshold before any sustained test.
- **Dynamic fallback is still a bottleneck:** profile the measured route mix first, then tune only demonstrated SQL/query hot spots and set an upgrade threshold.

## Shared Component Inventory

- **StaticArtifactReleasePublisher / StaticArtifactBuilder:** extend the canonical atomic release, not a second page-cache format.
- **FrontController / public `.htaccess`:** retain the front controller as the fallback and extend its existing static-versus-dynamic boundary.
- **QdbExperience, board/thread renderers, and quote links:** reuse their public output as the artifact source; do not fork QDB presentation.
- **Fingerprint asset delivery:** retain its existing immutable asset contract for both direct and fallback reads.
- **Production deployment runbook:** extend the canonical operator guidance with host validation, release, test, rollback, and capacity evidence.

## Simple User Flow

1. An anonymous visitor opens an allowlisted QDB read route.
2. Apache serves the active static artifact and fingerprinted assets directly.
3. A visitor with a cookie or a dynamic request is handled by PHP as today.
4. After a content write, direct artifacts withdraw until the next complete release is activated.
5. The operator runs the staged load test and compares its result with the recorded launch and upgrade thresholds.

## Success Criteria

- Verified anonymous allowlisted QDB reads do not execute PHP or query SQLite.
- Verified cookie, query, write, and non-allowlisted requests retain current behavior.
- Static-release withdrawal and activation never serve a partial release and have a documented rollback.
- The load-test result records the safe request rate, p95/p99 latency, error rate, cache/fallback mix, and required headroom against agreed launch SLOs.
- Any SQL/query change is backed by an observed bottleneck and before/after evidence.
