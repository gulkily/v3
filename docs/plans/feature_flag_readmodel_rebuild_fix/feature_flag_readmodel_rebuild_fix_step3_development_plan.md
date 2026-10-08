# Step 3: Development Plan — Feature Flag Write Triggers Spurious Read-Model Rebuild

## Stage 1
- Goal: Make a successful feature-flag write update `repository_head` metadata and clear the stale marker atomically with its activity-log entry, so the next request's staleness check passes.
- Dependencies: none
- Expected changes:
  - `IncrementalReadModelUpdater::writeMetadata(PDO $pdo, string $commitSha): void` — change visibility from `private` to `public` so the feature-flag write path can reuse it on its own PDO connection/transaction (it already accepts an external `$pdo`, so no other change needed there).
  - `LocalWriteService::insertFeatureFlagActivity(string $key, bool $value, string $recordPath, string $commitSha): void` — keep the existing `canIncrementallyUpdateReadModel()` early-return gate unchanged; when incremental update is possible, wrap the existing activity `INSERT` and a new call to `$this->incrementalReadModelUpdater()->writeMetadata($pdo, $commitSha)` in one `$pdo->beginTransaction()`/`commit()`/`rollBack()` block (matching every other incremental-update method's pattern), then call `$this->staleMarker()->clear()` after a successful commit.
  - Consider renaming the method (e.g. to `syncReadModelAfterFeatureFlagWrite`) since it now does more than insert activity — private method, single call site, low-risk rename.
- Verification approach:
  - Reuse the manual reproduction already run during investigation (git-init a temp repo, do an initial GET to seed the read model, POST a feature-flag change, assert `metadata.repository_head` now equals the new git HEAD immediately after the write — no separate request needed to trigger a resync).
  - Confirm a subsequent unrelated GET request no longer performs a full rebuild (no `rebuild_reason` fires / fast-path match in `ensureReadModel()`).
- Risks or open questions:
  - Confirm `IncrementalReadModelUpdater`'s constructor dependencies are cheap enough to instantiate from `LocalWriteService::incrementalReadModelUpdater()` (already used elsewhere in the class, so this is an existing, proven pattern, not new).
  - If the activity insert or metadata write throws mid-transaction, confirm the existing outer `catch`/stale-marking behavior in `setFeatureFlag()`'s caller still degrades safely (rollback should leave state exactly as before the write attempt, same as pre-fix behavior when the insert alone failed).
- Canonical components/API contracts touched: `IncrementalReadModelUpdater.php`, `LocalWriteService.php`. No change to `/api/set_feature_flag`'s request/response contract.

## Stage 2
- Goal: Add regression coverage proving the fix, and confirm no regressions elsewhere.
- Dependencies: Stage 1
- Expected changes:
  - New test(s) in `tests/WriteApiSmokeTest.php` (alongside the existing `testFeatureFlagFormSubmit*` tests): assert `metadata.repository_head` matches git HEAD immediately after a feature-flag write; assert a subsequent request does not trigger a full rebuild (mirroring existing helpers like `assertUsedIncrementalUpdate`/`assertReadModelHealthy` already used for other write types).
  - New test: pre-set the stale marker before a feature-flag write, then assert it's cleared after a successful write (mirroring `assertReadModelMarkedStale`/`assertReadModelHealthy` helpers already in the same file).
- Verification approach:
  - Run the new tests directly, then run the full project suite (`php tests/run.php`) and confirm no new failures beyond the pre-existing, already-tracked long-standing ones (activity-manifest tests, the Node-helper compose-signing test).
- Risks or open questions: none beyond Stage 1's.
- Canonical components/API contracts touched: `tests/WriteApiSmokeTest.php`.
