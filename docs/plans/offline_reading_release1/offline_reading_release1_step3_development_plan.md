# Offline Reading Release 1 Step 3 Development Plan

## Stage 1
- Goal: Build a bounded public SQLite snapshot.
- Dependencies: Approved Step 2; read model.
- Expected changes: Add `PublicOfflineSnapshotBuilder::build(string $sourcePath, string $targetPath, int $threadLimit = 50, int $maxBytes = 10485760): array`; project visible content and generation metadata only.
- Verification approach: Fixture covers newest threads, replies, display fields, cap, and omissions.
- Risks or open questions: Over-cap snapshots omit oldest whole threads and record the actual count.
- Canonical components/API contracts touched: Read-model `posts`, `threads`, `profiles`; static-artifact input.

## Stage 2
- Goal: Publish and safely expose each release snapshot.
- Dependencies: Stage 1; static-release publisher.
- Expected changes: Package snapshot/metadata; add a stable same-origin read-only resource.
- Verification approach: Release test checks bytes/type and the private-members gate.
- Risks or open questions: Offline browser caches cannot be remotely revoked.
- Canonical components/API contracts touched: `StaticArtifactBuilder`, `StaticArtifactReleasePublisher`, `FrontController`, approved-members gate.

## Stage 3
- Goal: Add a local-only offline-reader shell.
- Dependencies: Stage 2; browser SQLite runtime.
- Expected changes: Add route/template and reader asset for snapshot, runtime, generation time, and failure state.
- Verification approach: Browser/asset test loads a fixture offline after caching.
- Risks or open questions: No arbitrary-query surface or reuse of the developer query UI.
- Canonical components/API contracts touched: `TemplateRenderer`, shared layout, `/tools/sqlite/`, `sqlite_viewer.js`, `sql-wasm.js`/`.wasm`.

## Stage 4
- Goal: Render the local recent-thread list.
- Dependencies: Stage 3; snapshot thread metadata.
- Expected changes: Add local list query, selection state, and cached timestamp.
- Verification approach: Fixture checks order, metadata, empty state, and no fetch.
- Risks or open questions: Missing history must not imply deletion or online unavailability.
- Canonical components/API contracts touched: Online `/threads/` semantics; offline reader asset and snapshot contract.

## Stage 5
- Goal: Render each selected thread and visible replies locally.
- Dependencies: Stage 4; snapshot post data.
- Expected changes: Add detail query, reply order, return navigation, and online-only interaction state.
- Verification approach: Fixture opens every snapshot thread without fetches.
- Risks or open questions: Out-of-snapshot links need an explicit unavailable state.
- Canonical components/API contracts touched: Online `/threads/{id}` content semantics; offline reader asset and snapshot contract.

## Stage 6
- Goal: Add PWA registration and atomic refresh.
- Dependencies: Stages 2-5; fingerprinted assets.
- Expected changes: Add manifest, service worker, versioned app/snapshot caches; exclude APIs, writes, cookies, and personalized pages.
- Verification approach: Browser test covers initial cache, offline restart, failed refresh, and new timestamp.
- Risks or open questions: All routes retain normal behavior without service-worker support.
- Canonical components/API contracts touched: Shared layout, asset fingerprinting, `FrontController` cache classes, public routing.

## Stage 7
- Goal: Harden privacy boundaries and document verification.
- Dependencies: Stages 1-6.
- Expected changes: Add omission/members-only regressions and snapshot/cache-clear guidance.
- Verification approach: Targeted PHP/JS tests, artifact check, full suite, Chromium offline walkthrough.
- Risks or open questions: Fixtures include hidden and operational data to prove omission.
- Canonical components/API contracts touched: Existing smoke-test suite, static-artifact health check, production deployment guidance.
