# Private message unread state Step 4 implementation summary

> **Feature plan:** [Step 1](./private_message_unread_step1_solution_assessment.md) · [Step 2](./private_message_unread_step2_feature_description.md) · [Step 3](./private_message_unread_step3_development_plan.md) · [Step 4](./private_message_unread_step4_implementation_summary.md)

## Stage 1 - Durable private state

- Changes: added private tracking/baseline/signing-secret metadata and per-username/counterpart seen positions, without altering envelopes. Initialization is atomic and precedes new deliveries; progress is monotonic and insertion anchors are validated. State returns a global conversation count, up to 25 requested row states, and a coherence revision. Grouped the four FDP artifacts and repaired their links/index.
- Verification: `php tests/run.php PrivateMessageReadStateTest PrivateMessageStoreTest PrivateMessageMailboxServiceTest` — 20 passed. Covers ties/backdating, own sends, >25 conversations, scoped boundaries, concurrent first use/acknowledgment, existing-history baseline, old-code insertion during rollback, re-upgrade, and invalid anchors. PHP syntax and whitespace checks passed.
- Notes: planning-only commit `687e94f7`. Schema changes occur only in isolated tests so far; no production migration, merge, or push. Keep metadata during code rollback to avoid resetting baseline/progress. No UI is wired until later stages.

## Stage 2 - Authenticated receipt contracts

- Changes: added bounded no-store unread retrieval and same-origin JSON acknowledgment routes, with authenticated service authorization. Fresh conversation windows carry HMAC-protected received-message receipts; unsigned history cursors cannot authorize mutation. Receipts bind viewer, counterpart, and validated insertion anchor; retries retain their boundary. Existing envelope/pagination contracts remain compatible.
- Verification: focused store/read-state/service/page/routing suite — 25 passed; PHP syntax and whitespace passed. Full encrypted browser journey and new HTTP API checks passed (`/tmp/private-message-browser-lCKA13/`), covering forged/foreign receipts, missing request guard, cross-site writes, methods, replay, and backdated arrival after the opened boundary. Malformed row-state arrays are rejected.
- Notes: metadata stays private; fresh-window receipts are separate from history continuation and contain no secret. UI wiring follows in Stage 3.

## Stage 3 - Unread navigation and rows

- Changes: extended canonical navigation and list rows with accessible count/Unread indicators and loading/unavailable/zero states. A shared coordinator refreshes on navigation/visible return, batches requested rows by 25, requires matching state revisions, and ignores obsolete refreshes. Counts require no envelope fetch/decryption. Added retry on Messages and excluded assets/markup from static releases.
- Verification: list/page/static-release/theme suites — 9 passed; syntax/whitespace passed. Full browser journey passed (`/tmp/private-message-browser-km0vsF/`), including >25 rows, per-row/count agreement, failed refresh/retry, keyboard focus, 375px layout, and counts on profile pages. Mobile screenshot captured.
- Notes: the expanded mobile check exposed an existing full-width preview-retry button overflowing its margins; constrained its width without redesigning warnings. Conversation acknowledgment follows in Stage 4.

## Stage 4 - Acknowledge presented windows

- Changes: wired a conversation-only seen controller to reader settlement, latest-card visibility, document focus/visibility, and the signed receipt. Acknowledgment clears indicators only after server confirmation; old-history loads and inline sends cannot broaden its boundary. Successful fresh history restart supplies a new candidate after settlement. Added compact, separate acknowledgment retry/reopen feedback without changing message warnings or composer sending.
- Verification: page/reader/composer/static-release suite — 11 passed; JavaScript syntax and whitespace passed. Full browser journey passed (`/tmp/private-message-browser-pCCwBH/`), including normal Messages entry/count 2→1, controlled hidden-tab gates, early scrolling during delayed reads, failed signature displayed but not verified, later backdated arrival, older-history loading, inline send, and fresh restart acknowledgment.
- Notes: browser visibility gates are explicitly controlled where headless tab behavior differs; physical-device verification remains a final handoff limitation. Recovery and cross-device races follow in Stage 5.

## Stage 5 - Recovery and device convergence

- Changes: invalidate queued/in-flight presentation work on page exit and browser identity changes; reject malformed confirmations and mismatched viewers. Recovery keeps the exact receipt, saved/newer drafts, and pending sends. State revisions now depend only on the viewer's own mailbox activity/progress, not unrelated private activity.
- Verification: service/read-state/composer suite — 16 passed; syntax/whitespace passed. Expanded browser journey passed (`/tmp/private-message-browser-X0I9PR/`): accepted-but-lost acknowledgment, later arrival before retry, concurrent accepted send/newer draft, malformed/denied/stale confirmations, explicit reopen, two independently authenticated browser contexts, reversed refresh responses, identity changes, wrong-viewer response, and back navigation. Service checks cover two approved identities sharing one username and approval/key eligibility loss.
- Notes: remote changes converge on refresh/focus return, not polling. Unavailable state is shown as unknown, never zero; plaintext and verification results remain browser-only. Final release checks/documentation follow in Stage 6.

## Stage 6 - Release verification and handoff

- Changes: updated the API reference and [rollout/rollback notes](./private_message_unread_rollout.md), completed the master checklist, and expanded browser reporting. Added anonymous API and invalid-seen-anchor checks. Reopen feedback advises copying a draft as a precaution rather than promising storage succeeded when browser storage may be unavailable.
- Verification: `php tests/run.php PrivateMessageDatabaseConfigTest PrivateMessageApiRoutingTest PrivateMessageComposerTest PrivateMessageEnvelopeTest PrivateMessageMailboxServiceTest PrivateMessageListTest PrivateMessagePageControllerTest PrivateMessageReaderTest PrivateMessageReadStateTest PrivateMessageReleaseIsolationTest PrivateMessageStoreTest ApprovedUserKeyResolverTest AuthNavigationTest OfflineNavigationWorkerTest ProfilePresentationContentTest ProfileThemePresentationTest` — **61 passed, 0 failed**. `node tests/browser/private_message_list_browser.mjs` — passed; artifacts `/tmp/private-message-browser-XClY8q/` include `report.json`, screenshots, and `server.log`. This covers the normal complete messaging journey, unread indicators beyond 25 rows, visibility gates, signed receipts, uncertain updates with concurrent sends, two-device convergence, identity changes, backward navigation, authorization/no-store, and compatibility. Isolated upgrade/rollback/concurrency and static/offline exclusion passed. Changed PHP/JavaScript syntax, document links, and whitespace checks passed; mobile screenshot reviewed.
- Notes: six stages complete on `feature/private-message-unread`, with a planning-only commit and a summary-bearing commit per stage. No production migration/deployment, merge, or push. First store initialization establishes the one-time private baseline; preserve metadata during rollback. Physical mobile keyboards and other browser engines remain manual checks. Decryption-widget and historical-key follow-ups remain explicitly deferred, not marked complete.
