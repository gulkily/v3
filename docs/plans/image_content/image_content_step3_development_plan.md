> **Feature plan:** [Step 1](./image_content_step1_solution_assessment.md) · [Step 2](./image_content_step2_feature_description.md) · [Step 3](./image_content_step3_development_plan.md) · [Step 4](./image_content_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract

- Complete approved-member compose → signed thread/reply → authorized viewing → backups → independent restore, including draft recovery and missing-media explanations. Release requires browser/HTTP, rebuild, and deployment-path verification; production deployment is excluded. Each stage targets ≤1 hour; exceeding eight stages/one day requires rescoping.

## Key Risks

- **High risk: disclosure.** Static HTML precedes application checks: validate direct/static/cache paths early; protect delivery and bypass stale flag-state artifacts. Downloaded copies cannot be recalled.
- **High risk: preservation.** Archives lack coordination: validate concurrent writes early; use the existing write lock, immutable objects, revision-bound snapshots/checksums; reject incomplete exports/mismatched restores.
- **High risk: capacity.** Start at 5 MiB/image, 4096 pixels/side, JPEG/PNG; 256 MiB/1,024 objects/instance. Validate PHP limits, temporary space, concurrency and archive duration early; reject excess without losing drafts.

## Stage 1
- Goal: Establish portable storage and gating.
- Dependencies: Approved plans; organize/commit them after Step 3 approval.
- Expected changes: Default-off flag; private instance store; versioned body references with checksum/type/dimensions/size/description. No database migration.
- Verification approach: Reference round-trip, hostile paths/files, quotas, flag precedence.
- Risks or open questions: Reject unsafe roots/symlinks; keep provider URLs out of records for future Option C.
- Canonical components/API contracts touched: Flag registry; post spec; new ImageReference parser; ImageStore put/resolve/inventory contracts.

## Stage 2
- Goal: Publish a signed attachment safely.
- Dependencies: 1.
- Expected changes: Prepare metadata; accept matching bytes at signed finalization; validate membership/signature/content/limits; publish under lock.
- Verification approach: Tampering, anonymous/direct-write bypass, flag changes, retries, commit failure.
- Risks or open questions: Keep failed uploads private/outside exports; prevent duplicate retries.
- Canonical components/API contracts touched: LocalWriteService prepare/finalize; write-controller multipart input; unsigned paths reject attachments.

## Stage 3
- Goal: Attach from normal thread/reply compose.
- Dependencies: 2.
- Expected changes: Shared image/description controls, signed submission, progress/errors, preserved drafts; online requirement.
- Verification approach: Browser thread/reply publication, inline/dialog forms, invalid files, signing/network failures.
- Risks or open questions: Block anonymous/Outbox attachment loss; explain reselection after reload.
- Canonical components/API contracts touched: Compose partials; browser/lazy signing; draft clearing; Outbox.

## Stage 4
- Goal: View images consistently and enforce disablement.
- Dependencies: 2–3.
- Expected changes: Protected delivery, shared rendering/alt text/fallback; no-store responses; effective-flag-aware generated pages/caches.
- Verification approach: Standard/Forte/QDB surfaces; membership; flag/environment changes after static builds.
- Risks or open questions: Never serve image files through public assets or trust identity hints as authentication.
- Canonical components/API contracts touched: Application/FrontController; shared renderer; static builder/invalidation; HTML cache.

## Stage 5
- Goal: Download complete, attributable backups.
- Dependencies: 4.
- Expected changes: Image archive/inventory/checksums/revision; coordinated repository snapshots; bounded export resources; flag-off backup access.
- Verification approach: Concurrent publishing, missing/corrupt objects, capacity exhaustion, denied downloads, complete inventory.
- Risks or open questions: Copy a consistent snapshot under lock, archive afterward; fail before streaming incomplete output.
- Canonical components/API contracts touched: InstancePageController; Backup template; ExecutionLock; ImageStore inventory/export.

## Stage 6
- Goal: Restore independently with clear recovery.
- Dependencies: 5.
- Expected changes: Verification/restore command and instructions; validate paths/revision/checksums/references before activating an isolated destination.
- Verification approach: Clean-instance restore/rebuild, wrong pair, traversal, corruption, interruption and retry.
- Risks or open questions: Preserve existing data; distinguish missing media from a complete restore.
- Canonical components/API contracts touched: CLI/import conventions; canonical references; ImageStore; recovery runbook.

## Stage 7
- Goal: Preserve secondary surfaces and host compatibility.
- Dependencies: 4–6.
- Expected changes: Readable references/missing-media notices; document permissions, limits, backup capacity and future Option C migration without rewriting posts.
- Verification approach: Secondary surfaces; shared instance profiles; PHP/Apache routing/request-limit fixtures.
- Risks or open questions: No offline binary caching or cloud adapter; report unverified target-host constraints.
- Canonical components/API contracts touched: Text APIs/RSS/activity/SQLite/offline/import surfaces; deployment runbook.

## Stage 8
- Goal: Verify release readiness.
- Dependencies: 1–7.
- Expected changes: Integrated regression/browser coverage; recovery evidence in implementation summary.
- Verification approach: Project tests; thread/reply → backup → restore with identical references/checksums; rebuild/flag/access/failure matrix.
- Risks or open questions: Unresolved security, preservation, or deployment-path failures block release.
- Canonical components/API contracts touched: Existing test runner/browser harness; complete Step 2 surface inventory.
