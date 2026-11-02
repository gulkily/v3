# Collection-selection fixes

Authorization: User requested QDB welcome selection first, then offline candidate selection, in separate commits, with this ongoing checklist. Direct execution of those two bounded audit recommendations; broader findings remain unapproved.
Source audit: [ranked findings](load_before_selection_audit/findings.txt).

## Current work

- [x] LBS01a: QDB welcome — scan only eligibility/order metadata, retain five recent quote IDs and three news IDs, hydrate only selected rows; preserve exact quote count, news order and More state.
- [x] Verify old/new result parity, bounded body hydration and normal QDB route; schema-version startup migration and ordinary rebuild.
- [x] Commit QDB fix separately with this checklist's evidence.
- [x] LBS02a: Offline candidate selection — stream the existing ordered visible rows, retain all pins plus bounded recent rows; preserve snapshot output and failure recovery.
- [x] Verify mixed eligibility/order/pins, large fixed-selection scaling, complete snapshot build and publication failure preservation.
- [x] Commit offline fix separately with this checklist's evidence.

## Follow-ups from the audit

- [ ] LBS01b: QDB numbered lists, random and search (independent semantics; not authorized by this two-fix request).
- [ ] LBS04: Tags-index narrow projection; inspect RSS/text/Forte before sharing.
- [ ] LBS07: Source-history lookup streaming and safe early stop.
- [ ] LBS05: Dedicated pending-user count.
- [ ] LBS06: Standard username active-tab loading.
- [ ] LBS03: Static tag-route enumeration.
- [ ] LBS08: Historical scoring body retention.
- [ ] Offline size-limit error reporting: SQLITE_FULL can auto-rollback, and the existing catch then masks the intended pinned-size message with “cannot rollback — no transaction is active.” Baseline reproduced; published snapshot/manifest remain intact.
- [ ] Legacy offline CLI test fixtures: provide current retracted_at schema or explicitly isolate the automatic-tagging setting so stderr-only warnings do not fail unrelated command assertions.
- [ ] Further offline work: narrow metadata transfer; selected-thread and expansion size dimensions; preserve unlimited pins.

## Contracts and verification

Reuse: ThreadRepository::byId and ThreadRowSupport filtering/decoding; QdbQuoteNumbers eligibility and QdbBoardPolicy news ordering; existing PublicOfflineSnapshotBuilder publication/size behavior. No new UI or third-party component.
Schema: QDB metadata query requires version 39 → 40 under CONTRIBUTING's core-query policy; tables unchanged. Offline change keeps existing SQL and adjusts iteration only.
Evidence boundary: Local checks must name entry points, fixture shape and limits. Live QDB PHP 8.3/corpus and production build-time savings remain unverified. Preserve unrelated drafts and todo.txt. No merge/push requested.

*— Codex · 2026-10-31 UTC*

## QDB welcome result — 2026-10-31 UTC

Implementation: QdbWelcomeSelection reads only metadata before retaining five recent/three news IDs. Existing PHP eligibility/news ordering and byId hydration are reused, with a cache for overlapping selections. All metadata is still examined; SQLite sorting/native cost is not bounded by the PHP result size. The current template renders news/counts; recentThreads remains available in the page data contract.
Verification: QdbWelcomeSelectionTest, QdbBoardPolicyTest and QdbExperienceRoutingTest: 11/11 passed. Mixed malformed/object/case-sensitive tags, retractions, quote IDs, pins, dates/ties and full selected rows match the old logic; body hydration equals distinct selected roots (at most eight). Prior version 39 rebuilt before normal welcome rendering. Full welcome routes at 1000/10000 quotes with 2048-byte bodies/1024-byte previews each peak at 6 MiB under 32 MiB. This is not directly comparable to the audit's isolated old reader timing.
CLI: ./v3 rebuild and ./v3 build-static passed on a copied minimal Git fixture; activated local release with 25 HTML/53 assets. Logs: /tmp/selection-fixes-cli-tn671llm/rebuild.txt and build.txt. Rebuild initially checked the existing default offline snapshot (already available); build artifacts and read model were isolated under the temporary workspace. No production run.
Commit: 2c0c1fb2 (QDB welcome); offline source unchanged at this point. Schema identity 40, no table/index changes, zero dependencies; whitespace/lint checks passed. Full suite and live-host capacity/recovery unverified.

*— Codex · 2026-10-31 UTC*

## Offline selection result — 2026-10-31 UTC

Implementation: visibleThreadRows is now a cursor-backed generator with finally/closeCursor. Existing SQL, filtering, pin parsing and ordering are unchanged. snapshotThreadRows retains all pinned rows plus the requested recent rows. This removes whole-candidate PHP retention; it still scans/transfers every candidate preview and does not cap pins, selected-thread posts or expansion work.
Verification: 22/22 tests passed across PublicOfflineSnapshotBuilderTest, OfflineSnapshotPublisherTest, PublicOfflineSnapshotManifestTest, OfflineSnapshotPresentationTest, OfflineSnapshotThreadPresentationTest and OfflineSnapshotPublishCommandTest. Includes exact pinned-first/recent order, hidden/object/malformed tags, all-pinned and empty sets, complete snapshot IDs, effective-tag overlay, saved-reader presentation and failed-publication preservation. Full snapshot fixture at 1000/10000 candidates (2048-byte bodies/1024-byte previews) selects the same oldest pin plus newest 200, peaks at 2 MiB for both under 32 MiB. Same PHP fixture/snapshot cap; native SQLite scan/RSS and production speedup unmeasured.
Failure evidence: Initial pinned-size assertion expected the friendly error, but SQLite emitted a rollback error. Extracted committed pre-change builder reproduced the identical PDOException; existing snapshot hash remained unchanged. The new test asserts the actual recovery contract (failure, unchanged snapshot/manifest, removed temporary file), without asserting misleading error wording. No rollback-source changes were folded into this selection fix; follow-up above.
Test environment: Initial command suite was 20/22 because two legacy-schema CLI fixtures emitted Fastmod warnings about missing p.retracted_at while exiting successfully. Rerun with FASTMOD_AUTOMATIC_TAGGING_ENABLED=false passed 22/22. The dedicated effective-tag builder test explicitly enables tagging and passed; unrelated fixtures' stderr assertions require isolation. The first strict-message size test failed during development as recorded above; final recovery assertion passed.
CLI: Final ./v3 rebuild, ./v3 build-static and ./v3 offline publish all passed with repository/database/static paths isolated under /tmp/selection-fixes-cli-tn671llm and tagging disabled. Full static release activated locally; independent offline publication produced base/update snapshots. Logs: rebuild-final.txt, build-final.txt, offline-final.txt and offline-memory.txt in that workspace.
Commit: This offline-only fix commit, after 2c0c1fb2. QDB source unchanged in this commit; no additional schema/dependency changes. Lint, local document links and whitespace passed. No production run, merge or push; full application suite and physical-browser verification not performed (UI output contract unchanged).

*— Codex · 2026-10-31 UTC*

## Main integration — 2026-10-31 UTC

Authorization: User requested merge and push. Merged into updated main at f4f275cf, preserving remote invite changes through 2ee0d198 and the separate fix commits 2c0c1fb2/e5fb84b1.
Verification: Combined QDB/offline suite passed 33/33 on the merged tree with FASTMOD_AUTOMATIC_TAGGING_ENABLED=false for legacy CLI fixtures; dedicated effective-tag test enables tagging internally. Log: /tmp/v3-selection-merge-tests.log. Removed trailing blank lines from the two audit output archives without changing samples. Integration whitespace check passed; full suite/live deployment not claimed. Unrelated working drafts excluded.

*— Codex · 2026-10-31 UTC*
