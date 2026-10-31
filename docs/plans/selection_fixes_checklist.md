# Collection-selection fixes

Authorization: User requested QDB welcome selection first, then offline candidate selection, in separate commits, with this ongoing checklist. Direct execution of those two bounded audit recommendations; broader findings remain unapproved.
Source audit: [ranked findings](load_before_selection_audit/findings.txt).

## Current work

- [x] LBS01a: QDB welcome — scan only eligibility/order metadata, retain five recent quote IDs and three news IDs, hydrate only selected rows; preserve exact quote count, news order and More state.
- [x] Verify old/new result parity, bounded body hydration and normal QDB route; schema-version startup migration and ordinary rebuild.
- [x] Commit QDB fix separately with this checklist's evidence.
- [ ] LBS02a: Offline candidate selection — stream the existing ordered visible rows, retain all pins plus bounded recent rows; preserve snapshot output and failure recovery.
- [ ] Verify mixed eligibility/order/pins, large fixed-selection scaling, complete snapshot build and publication failure preservation.
- [ ] Commit offline fix separately with this checklist's evidence.

## Follow-ups from the audit

- [ ] LBS01b: QDB numbered lists, random and search (independent semantics; not authorized by this two-fix request).
- [ ] LBS04: Tags-index narrow projection; inspect RSS/text/Forte before sharing.
- [ ] LBS07: Source-history lookup streaming and safe early stop.
- [ ] LBS05: Dedicated pending-user count.
- [ ] LBS06: Standard username active-tab loading.
- [ ] LBS03: Static tag-route enumeration.
- [ ] LBS08: Historical scoring body retention.
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
Commit: This QDB-only fix commit; offline source unchanged at this point. Schema identity 40, no table/index changes, zero dependencies; whitespace/lint checks passed. Full suite and live-host capacity/recovery unverified.

*— Codex · 2026-10-31 UTC*
