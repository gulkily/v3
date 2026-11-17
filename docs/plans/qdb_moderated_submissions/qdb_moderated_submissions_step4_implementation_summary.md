# QDB moderated submissions Step 4 implementation summary

> **Feature plan:** [Step 1](./qdb_moderated_submissions_step1_solution_assessment.md) · [Step 2](./qdb_moderated_submissions_step2_feature_description.md) · [Step 3](./qdb_moderated_submissions_step3_development_plan.md) · [Step 4](./qdb_moderated_submissions_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/qdb_moderated_submissions`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.65**

Branch `feat/qdb-moderated-submissions` from `6bfe1243`; planning commit `3b34a30`.

## Step 4 entry

- Step 3 approved 2026-11-17. Whole-cycle projection **0.65** reaffirmed after the planning commit. Stage originals 0.15/0.12/0.08/0.12/0.08/0.06 copied from Step 3, non-additive. Claude single exposed rater; R1 hashes not re-verified.

## Stage 1 - Pending rule in shared listings

**Original estimate: 0.15**

**Retrospective: 0.15**

- Changes:
  - New flag `FORUM_QDB_QUEUE_ENABLED` (site-mutable, off).
  - New `src/ForumRewrite/Qdb/QdbModeration.php`: the queue state, its SQL conditions (`listedSqlSuffix`, `queueSql`) and `state()` for one quote.
  - `ScoreVisibility::listedSql` appends the moderation condition, so every public listing that already hides flagged posts also hides pending and rejected quotes. On other sites, or with the queue off, the condition is empty.
  - `Application` registers the repository with `QdbModeration::useRepository`, beside `SignatureCheckCache::useBeside`.
  - New `src/ForumRewrite/Security/FeatureFlagSwitchOn.php`: the first operator-signed switch-on of a flag. `AttributionCutoff` now uses it instead of its own copy of the loop.
- Verification:
  - `./v3 test --smoke QdbModerationTest QdbPageTest ScoreVisibilityTest PublicOfflineSnapshotBuilderTest QdbWelcomeSelectionTest QdbExperienceRoutingTest ReadModelProvenanceTest FeatureFlagChangePageTest FeatureFlagEvaluatorTest`: 475 passed. Full suite not run.
  - New `QdbModerationTest`: archive, anonymous, visitor, operator, news, approved (both key notations), self-approved, rejected, old rejected, approved-then-rejected and non-quote rows; inactive queue; empty operator list; unsigned switch-on.
  - `FeatureFlagEvaluatorTest::testRegistryListsCurrentPublicFlags` updated for the new flag.
  - Timing on 20,000 quotes with 60,000 tag rows: Latest 47 ms off, 52 ms on. A first version with correlated lookups took 222 ms; the tagged sets are now built once per query.
- Notes:
  - **Deviation from Step 3:** no `QDB_QUEUE_STARTED_AT` private setting. The start time is the first operator-signed switch-on record of the flag, as `AttributionCutoff` already does. It lives in Git and no one but an operator can set or move it. Without a signed switch-on, nothing waits. New understanding, not changed scope.
  - **Deviation from Step 3:** the rule sits in `ScoreVisibility::listedSql` rather than separately in `QdbPage`, Random and news. That one fragment reaches `ThreadRepository::fetchThreads` (board, RSS, Random, news), `QdbPage`, `QdbWelcomeSelection`, `ThreadKeysetPage`, `AuthoredContentRepository` and the offline snapshot.
  - Readers that bypass the rule, by design: `ThreadRepository::byId`, `Application::fetchThread`, `QdbQuoteNumbers::resolve` (permalinks) and `StaticArtifactBuilder::fetchVisibleThreadIds` (static permalink pages). Stage 3 marks pending permalinks; Stage 5 rechecks static builds.
  - Operator approval reads `viewer_tags` from active operators. `approved` and `rejected` are not yet writable tags; Stage 4 adds them as signed, operator-only tags. Until then nobody can write them.
  - The signed switch-on path is covered only for the unsigned case; no test fixture signs feature-flag changes today. The qdb.us replay in Stage 6 covers the signed path.
- Estimate details: original scope as planned; no revision. Retrospective matches: the main extra was the performance fix.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 2 - Public queue page

**Original estimate: 0.12**

**Retrospective: 0.10**

- Changes:
  - `QdbPage::page` gains a `queue` section: newest first, the score rule alone plus `QdbModeration::queueSql`.
  - `ScoreVisibility::scoreSql` split out of `listedSql`, so the Queue page can keep flagged quotes hidden while listing pending ones.
  - `QdbExperience` routes `/queue`, `/queue/N` and `/?queue`; `BoardPageController` renders them through the existing board template, quote card and empty state, titled "Queue".
  - `QdbPresentation::navigation` shows Queue between Random and Add Quote, only while the queue runs.
  - `theme-nav-icons.css`: an hourglass in the pixel-art and smooth-line sets.
  - `Application` lists `/queue` and `/queue/N` with the other qdb routes.
- Verification:
  - `./v3 test QdbModerationTest QdbExperienceRoutingTest NavIconSetsTest QdbPageTest`: 17 passed. Full suite not run.
  - New `QdbModerationTest::testQueuePageListsOnlyWaitingQuotes` and `QdbExperienceRoutingTest::testQueueRoutesExistOnlyWhileTheQueueRuns`. With the queue off, `/queue` paths return 404 and `/?queue` stays the welcome page, as any unknown flag did before.
  - Icons rendered with headless Chrome in Word97, Chicago, Midnight, Gloss, Gloss Dark and Engagify; each shows the hourglass beside Random and Add Quote.
- Notes:
  - `QdbModeration::current()` now recomputes when the site profile changes, so tests that switch `FORUM_SITE_ID` in one process see the right state.
  - The Queue page is not prebuilt by `StaticArtifactBuilder`; it is served live, which keeps it current between rebuilds.
- Estimate details: original scope as planned; no revision. Retrospective slightly lower: the board template and empty state fitted without changes.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 3 - Pending notices

**Original estimate: 0.08**

**Retrospective: 0.06**

- Changes:
  - The thread page shows one line above a pending quote: "Pending approval · Queue", linking to `/queue`.
  - A rejected quote's thread page and number address return 404.
  - `/api/create_quote` adds `moderation=pending` or `moderation=approved`, matching the route's existing `key=value` lines.
  - `docs/reference/agent_api.md` documents the new line.
- Verification:
  - `./v3 test --smoke WriteApiSmokeTest::testQdbQueueHoldsANewQuoteOffListingsButOnItsPermalink QdbModerationTest QdbExperienceRoutingTest ApiRouteCatalogTest`: 459 passed. Full suite not run.
  - The new end-to-end test creates an anonymous quote through `/api/create_quote` with the queue on. The quote is absent from `/latest`, present on `/queue` and its permalink with the marker, and listed again with the queue off.
- Notes:
  - **Deviation from Step 3:** the Add page keeps its existing redirect to the new quote's permalink. The permalink's marker is the confirmation, so the Add flow needs no extra copy.
  - **Deviation from Step 3:** a rejected quote returns 404 to everyone, operators included, so the page stays the same for every viewer. Operators still reach the record through the source browser and Git.
  - Fixed: `useRepository` cleared a test override when a second `Application` was built.
- Estimate details: original scope; no revision. Retrospective lower because the Add flow needed no change.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 4 - Approve and reject from the queue

**Original estimate: 0.12**

**Retrospective: 0.14**

- Changes:
  - `LocalWriteService::applyThreadTag` accepts `approved` and `rejected` on qdb only through `assertQdbModerationTag`. The tag must target a quote and carry a verified signature from an active operator. Unsigned tags, other keys and non-quote threads are refused.
  - Queue cards render Approve and Reject buttons inside the quote card header. They use the existing `apply-thread-tag` action in `thread_reactions.js`, so signing, the Outbox and error feedback are unchanged.
  - New `templates/partials/qdb_moderation_access.php`: an inline script before the cards compares this browser's key fingerprint with the operator list and sets `data-qdb-moderator` before paint. Server HTML is the same for every viewer.
  - `QdbModeration::operatorFingerprints` supplies that list; `BoardPageController` passes it only on the Queue page.
- Verification:
  - `./v3 test --smoke WriteApiSmokeTest::testQdbQueueApprovalAndRejectionAreSignedOperatorDecisions WriteApiSmokeTest::testQdbQueueHoldsANewQuoteOffListingsButOnItsPermalink WriteApiSmokeTest::testSignedThreadReactionPreservesBytesAndRejectsTampering QdbModerationTest TagScoreTest QuoteCardDisplayNumberTest`: 487 passed. Full suite not run.
  - New write test with GPG keys: unsigned operator tag, signed visitor tag and a non-quote target are refused. A signed operator approval lists the quote on Latest; a signed rejection removes it from the queue and returns 404 at its address.
  - Local acceptance in headless Chrome over the DevTools protocol, against a qdb instance whose queue was switched on through `prepareFeatureFlagChange` and an operator's GPG signature:
    - `/api/create_quote` answered `moderation=pending` for two anonymous quotes.
    - Signed out, the moderation buttons were hidden; with the operator's key in browser storage they showed.
    - Approve signed in the browser and showed "✓ Approved"; Reject removed the other quote.
    - After reload the queue was empty; Latest showed the approved quote and not the rejected one.
  - This run also covers the signed switch-on path that Stage 1 could test only unsigned.
- Notes:
  - **Deviation from Step 3:** a decided card stays in place, showing the applied label, until the page reloads. This reuses the vote buttons' behavior instead of adding card-removal code.
  - The queue footer still shows the queue's own count ("2 quotes"); Stage 5 replaces it.
- Estimate details: original scope; no revision. Retrospective slightly higher for the browser acceptance harness.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
