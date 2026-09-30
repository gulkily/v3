# Offline Reading Health Check Step 3 Development Plan

## Stage 1
- Goal: Establish separate public health and internal reader route contracts.
- Dependencies: Approved Steps 1–2; existing offline reader controller and routing.
- Expected changes: Add a health-page render contract; retain the reader at a dedicated fallback URL; route both URL forms; add **Offline Reading** to the shared Tools registry.
- Verification approach: Route and Tools-index smoke assertions confirm `/offline/` is health-only, the reader shell remains reachable, and the link resolves.
- Risks or open questions: The fallback URL must remain an application route and must not become a visible navigation destination.
- Canonical components/API contracts touched: `Application` routing, `OfflineReaderController`, page templates, `ToolsPageSupport::registry()`.

## Stage 2
- Goal: Present precise browser and published-snapshot health at `/offline/`.
- Dependencies: Stage 1 health-page contract; existing public snapshot boundary.
- Expected changes: Add a focused health asset and presentation states for connection, service-worker readiness, local reader/snapshot/assets, online snapshot reachability, pending checks, and recovery guidance.
- Verification approach: Browser-facing tests or focused script assertions cover ready, offline-local-only, missing cache, missing worker, and unavailable published-snapshot states.
- Risks or open questions: Offline status must never imply that an unavailable network check succeeded.
- Canonical components/API contracts touched: Health page template and client asset; Cache Storage, Service Worker, and snapshot HTTP contracts.

## Stage 3
- Goal: Move cache refresh and normal-navigation fallback to the dedicated reader shell safely.
- Dependencies: Stages 1–2; existing snapshot and service-worker cache lifecycle.
- Expected changes: Update worker cache identity, refresh resources, cacheable-resource rules, registration refresh inputs, and app manifest behavior so the health page and reader shell have distinct roles.
- Verification approach: Inspect the refreshed cache contents and confirm a disconnected Board and snapshot-contained thread receive the reader shell, not the health page.
- Risks or open questions: A partial upgrade must retain the prior valid cache until the replacement reader shell and snapshot are both available.
- Canonical components/API contracts touched: `service_worker.js`, `pwa_registration.js`, web manifest, offline snapshot route.

## Stage 4
- Goal: Protect public-data and route behavior with automated regression coverage.
- Dependencies: Stages 1–3.
- Expected changes: Update offline-reader smoke coverage for the separate routes, Tools link, cache contract, and approved-members-only exclusion; retain snapshot publication coverage.
- Verification approach: Run the targeted offline and front-controller tests, then the project test suite.
- Risks or open questions: Source-level worker assertions should validate observable cache behavior without coupling tests to incidental formatting.
- Canonical components/API contracts touched: `LocalAppSmokeTest`, public snapshot builder/front-controller tests.

## Stage 5
- Goal: Make verification and recovery discoverable in operations.
- Dependencies: Completed automated coverage.
- Expected changes: Update the offline-reading runbook with the health-page checks, expected ready state, and revised cache-recovery path.
- Verification approach: Follow the runbook in a public browser: prepare online, verify health, disconnect, load Board and a saved thread, then reconnect and refresh.
- Risks or open questions: Approved-members-only deployments must describe the intentional unavailable state without suggesting a bypass.
- Canonical components/API contracts touched: `docs/runbooks/offline_reading.md`, `/offline/` health page, normal offline navigation.
