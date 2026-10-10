# Compact unavailable private messages Step 4 implementation summary

> **Feature plan:** [Step 1](./private_message_unavailable_step1_solution_assessment.md) · [Step 2](./private_message_unavailable_step2_feature_description.md) · [Step 3](./private_message_unavailable_step3_development_plan.md) · [Step 4](./private_message_unavailable_step4_implementation_summary.md)

## Stage 1 - Compact individual recovery

- Changes: separated loading/support, unreadable-key, decryption, and signature outcomes; added neutral unavailable details with manual retry in the canonical message item. Loading and security failures retain visible recovery/warnings. Reader state remains ephemeral; verification still gates plaintext. Grouped FDP artifacts and repaired navigation.
- Verification: `php tests/run.php PrivateMessageReaderTest PrivateMessageListTest PrivateMessagePageControllerTest` — 8 passed; reader syntax and whitespace passed. Covers real encrypted/unsigned/tampered messages, category boundaries, deduplicated retry, recovery, and no plaintext persistence.
- Notes: planning-only commit `c479177b`. No schema/API changes, deployment, merge, or push. Group presentation follows in Stage 2.

## Stage 2 - Collapsed initial history

- Changes: added same-sender/local-date summaries for consecutive unavailable messages, native button expansion with accessible state/control relationships, and shared visible-representation lookup for Latest and seen visibility. Individual cards remain canonical and retain order/identity; warnings and pending reads cannot enter groups.
- Verification: reader/composer/page suites — 11 passed; JavaScript syntax and whitespace passed. Full encrypted browser journey passed (`/tmp/private-message-browser-GxgvmW/`), including normal Messages entry, twenty-to-one collapse, exact expansion order, keyboard activation, details, latest-summary acknowledgment, date/signature boundaries, and 375px screenshot/no overflow. Existing retry tests now use the details entry point.
- Notes: cross-page regrouping and asynchronous retry reconciliation follow in Stages 3–4; initial grouping does not change server receipts or read semantics.

## Stage 3 - Stable history joins and restart

- Changes: reading anchors resolve through visible summaries while retaining underlying message identity; history regroups after reading attempts settle and corrects position. Joined runs preserve an open choice from either side, and reconciliation removes obsolete summaries. Connected focused controls are restored without scrolling after a summary moves.
- Verification: reader/store/page suites — 17 passed. Full browser journey passed (`/tmp/private-message-browser-3e8Atn/`), including a sixty-message run across three pages, exact identity/order coverage, open and collapsed joins, summary anchoring within 5px, stale history/restart, retained drafts, and existing delayed-read/navigation/concurrent-send recovery.
- Notes: grouping remains presentation-only. Individual retry/send reconciliation follows in Stage 4.

## Stage 4 - Retry and send reconciliation

- Changes: reader lifecycle notifications reconcile groups during retry/loading/recovery and inline sends. Visible progress splits a run temporarily; failed attempts rejoin, verified messages remain in order, and expansion preferences survive. Focus follows surviving retry controls, recovered messages, or replacement group controls without scrolling. Detached-card completions are ignored; navigation clears active anchoring.
- Verification: reader/composer suites — 8 passed; syntax/whitespace passed. Full browser journey passed (`/tmp/private-message-browser-57Mo0D/`): failed retry/focus, actual decryption recovery with a retained test key, middle-group split, pending send/newer draft, open-state preservation, and delayed retry across a history join. Reviewed compact 375px screenshot. Final replacement-group focus fallback syntax-checked after this run; broader coverage follows in Stage 6.
- Notes: test-key recovery exercises existing manual retry only; this adds no key-management mechanism or recovery promise. Unread race checks follow in Stage 5.

## Stage 5 - Grouped unread recovery

- Changes: automatic acknowledgment waits while the latest message's retry is loading and reevaluates after reader-state changes. Shared summary visibility retains the original signed receipt and existing focus/identity gates.
- Verification: read-state/service suites — 13 passed; expanded focused messaging/navigation/offline/profile suite — 62 passed. Full browser journey passed (`/tmp/private-message-browser-pf56tN/`): hidden and unfocused grouped windows, pending latest retry, accepted-but-lost acknowledgment, same-receipt retry after a later backdated arrival, expansion/collapse, own send, older loading, and identity invalidation/restoration. Existing cross-device/reversed-refresh coverage also passed.
- Notes: deterministic visibility overrides isolate browser gate semantics in headless tests. No server-side contract or read-position change; final rollout and release checks follow in Stage 6.

## Stage 6 - Release verification and handoff

- Changes: completed the master checklist and [rollout/rollback notes](./private_message_unavailable_rollout.md); extended fixture direction support, failure-boundary coverage, mobile/zoom screenshots, layout comparison, and static-release exclusion assertions. Final review found overlapping retry anchors could leave native anchoring disabled; shared ownership now restores it only after the last active anchor, with a concurrent-retry regression check. Clarified retry-limit wording.
- Verification: `php tests/run.php PrivateMessageDatabaseConfigTest PrivateMessageApiRoutingTest PrivateMessageComposerTest PrivateMessageEnvelopeTest PrivateMessageMailboxServiceTest PrivateMessageListTest PrivateMessagePageControllerTest PrivateMessageReaderTest PrivateMessageReadStateTest PrivateMessageReleaseIsolationTest PrivateMessageStoreTest ApprovedUserKeyResolverTest AuthNavigationTest OfflineNavigationWorkerTest ProfilePresentationContentTest ProfileThemePresentationTest` — **62 passed, 0 failed**. `node tests/browser/private_message_list_browser.mjs` — passed, final artifacts `/tmp/private-message-browser-xfectv/` (`report.json`, screenshots, `server.log`). Checks cover the complete normal messaging journey plus collapsed/expanded unavailable history, exact sixty-message coverage, sender/date/readable/security/loading boundaries, failed and verified retry, concurrent history/send/draft/focus handling, native anchoring restoration, grouped seen boundaries, and identity/device recovery. Changed PHP/JavaScript syntax, relative document links, and whitespace checks passed. Reviewed collapsed and expanded 375px screenshots; expanded retry is above the composer and no horizontal overflow occurs. Same-fixture reconstructed legacy error presentation measured 2,733px versus a 51px collapsed summary on desktop; this is not a production measurement.
- Notes: all six stages complete with a planning-only commit and a summary-bearing commit per stage. No migration, deployment, merge, or push. Deploy/rollback template and assets together; preserve Cycle 4 metadata. Physical mobile keyboards, assistive-technology announcements, and other browser engines remain manual checks. Historical-key recovery remains deferred; manual test-key restoration demonstrates existing retry, not a new recovery mechanism.
