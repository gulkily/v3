# Offline Navigation Parity and Diagnostics Step 3 Development Plan

## Stage 1 - Share offline read-navigation controls
- Goal: Make offline Board and Tags navigation visibly match supported normal read navigation.
- Dependencies: Existing Board/Tag URLs and snapshot renderer.
- Expected changes: Add reusable offline subnav controls for Tags, Board filters/sorts, and an explicit reconnect-only New Post action; no database changes.
- Verification approach: DOM checks cover normal destinations, active states, and the reconnect-only write boundary.
- Risks or open questions: A parity control must not imply that posting works offline.
- Canonical components/API contracts touched: Board/Tags subnav labels, `appendBoardControls`, offline navigation policy.

## Stage 2 - Identify saved archive and reader revision
- Goal: Let offline readers identify what content and reader UI they are using.
- Dependencies: Snapshot metadata and fingerprinted reader-shell assets.
- Expected changes: Surface archive generation time and cached reader revision/capabilities in the offline reader; planned contract: `readerRevisionFromShell(document): string`.
- Verification approach: Fixture checks cover generated time, revision display, and unavailable metadata fallback.
- Risks or open questions: A reader revision identifies UI/cache freshness, not live-forum content freshness.
- Canonical components/API contracts touched: Public snapshot metadata, offline reader shell, fingerprinted asset contract.

## Stage 3 - Diagnose and refresh reader freshness
- Goal: Make the health page distinguish saved-reader freshness from saved-archive freshness.
- Dependencies: Stage 2, cache inspection, and the existing worker refresh message.
- Expected changes: Compare cached and live reader revisions while online, say when comparison is unavailable offline, and provide a refresh/recheck action with completion status; no database changes.
- Verification approach: Browser-script checks cover match, mismatch, offline-unavailable, refresh success, and refresh failure states.
- Risks or open questions: A refresh must retain the prior ready cache if fetching replacement resources fails.
- Canonical components/API contracts touched: Offline Reading health checks, `refresh-offline-reader` message contract, versioned cache lifecycle.

## Stage 4 - Document and verify recovery
- Goal: Make freshness diagnosis and recovery operationally clear.
- Dependencies: Stages 1-3.
- Expected changes: Update the offline-reading runbook and focused regression coverage; no database changes.
- Verification approach: Script/PHP syntax checks, focused offline tests, full suite, and an online health/refresh smoke where browser service-worker support is available.
- Risks or open questions: Headless browser service-worker limitations must be documented rather than masking an unverified release path.
- Canonical components/API contracts touched: Offline Reading Runbook, approved-members-only exclusion, public snapshot boundary.
