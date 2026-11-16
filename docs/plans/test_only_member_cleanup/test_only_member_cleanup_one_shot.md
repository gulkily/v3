# Remove low-value test-only members and two single-implementation interfaces

Cycle: `v3/test_only_member_cleanup`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on` present; no explicit request)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: enabled (new enrolled cycle default); no real alternatives or work units, so no unit estimates

**Cycle estimate: 0.30 (intake)**

Status: implemented and verified locally; PR open, awaiting maintainer review and merge.

## Original request

The code quality review ([checklist](../code_quality_review_checklist.md), [findings](../code_quality_review_findings_v1.md) §3) listed about 25 methods in `src/` that only tests call, each rated 0–1 for future usefulness in the [ZenMemes review thread](https://zenmemes.com/threads/thread-20261111012557-b02a1583) (decision 2), plus two interfaces with one implementation each (decision 3) and constructor properties nothing reads (decision 4).

On 2026-11-14 the maintainer wrote: "please remove the 0.3 or lower. for the 0.35-0.6, please put on your senior developer hat and re-rate them 0-1 remove/keep. keep the 0.8-0.9. what is $featureFlags and $sections? keep $repositoryRoot if we're using it to store pwd, it may come in handy. you can delete and use teh classes directly for decision 3".

Feedback items: none match.

## Authorization

That message authorizes, in one bounded change:

1. Removing the 13 members rated 0.3 or lower, and the test code that exists only to exercise them.
2. Deleting `Scoring/FastScoreStore` and `Agent/AgentReplyGenerationStore`, with callers typed to the concrete `SqliteFastScoreStore` and `SqliteAgentReplyGenerationStore`.
3. Keeping the five 0.8–0.9 members and marking them as test hooks so later reviews skip them.

Excluded: the eight 0.35–0.6 members (the maintainer asked for a re-rating, reported in the handoff, not a change); `$featureFlags` and `$sections` (the maintainer asked what they are); every `$repositoryRoot` property (keep). Merge and deployment are not authorized.

## Outcome and work type

Internal maintenance (no user-visible change). Allowed paths: the classes that define the members and interfaces, their direct callers, the tests that call removed members, this record, the plans index, the findings/checklist status lines, the ledger row and the debug log.

The 13 members, by the [decision 2 table](https://zenmemes.com/threads/thread-20261111012557-b02a1583):
`StaticArtifactInvalidator::invalidateProfile`, `SqliteFastScoreStore::latestScoredForPostContent`, `SqliteAgentReplyGenerationStore::claimNextRequested`, `ImageBackup::copyTree`, `TagGrouping::limitPreview`, `PlatformDocsCatalog::resolvePath`, `AgentResponseTask::defaultGenerationInput`, `PrivateMessageStore::conversationFor`, `PrivateMessageMailboxService::conversation`, `DedalusPostAnalyzer::decodeCompletionPayload`, `Application::noStoreTimingHeaders`, `Application::mergeResultTimings`, `Application::activityCommitManifest`.

Kept test hooks: `CommitEvidenceLookup::manifestReads`, `CommitEvidenceLookup::processes`, `ContentImportPlanner::dependencyVisits`, `PrivateConfigSchema::keysOmittedFromTemplate`, `ApiRouteCatalog::paths`.

## Approach and risks

Delete each member; where a test only exercises a removed wrapper, point it at the method production calls instead (for example `conversationPageFor`), so coverage of the real path stays. Delete a test only when the removed member is all it covers. Replace interface type hints with the concrete classes.

- Risk: a member is reached dynamically (string callable, reflection from a script). Validation before editing: repo-wide search for each name, including strings and `scripts/`; re-run the PHPStan dead-code pass after editing.
- Risk: a removed test was the only coverage of production behavior behind a wrapper. Mitigation: retarget such tests to the production method instead of deleting them.
- Risk: something outside `src/` implements an interface (a test double). Validation: search for `implements` before deleting.

## Reuse decisions

No new code. Existing production methods replace the wrappers in tests; concrete store classes replace the interfaces.

## Completion conditions

The 13 members and two interfaces are gone; the five hooks carry a test-hook note; every caller and test compiles and passes; the PHPStan dead-code pass reports no new dead code from the change. Handoff: PR open with the re-rating and the `$featureFlags` / `$sections` answers in the reply.

## Concepts touched

None.

## Verification and recovery plan

- `php -l` on touched files; `git diff --check`.
- Smoke set plus the tests of every touched class (per the maintainer's standing preference, not the full suite).
- PHPStan dead-code pass before/after, diffed.
- Runtime UI checks: inapplicable, no rendered output changes.
- Recovery: revert the PR.

Starting revisions and branch: `origin/main` at `9122adf0fa97f2e9e1bdee3d1b1bdd5e5fa684d5`; branch `chore/test_only_member_cleanup`.

Planning commit: `77ad17d1` (confirmed with Git; contains only this record and the index entry).

## Study history

- 2026-11-14 intake: 0.30, scope v1 (above), written before detailed inspection. Prior exposure: the same agent rated these members on 2026-11-10 and read their code then.
- 2026-11-14 execution entry: 0.30 reaffirmed; outcome, approach and verification unchanged.
- 2026-11-14 closeout: scope v1 delivered. Retrospective scope assessment: 0.35. More test call sites needed retargeting than the intake assumed (11 test files), and two helpers turned out to be test oracles that had to move rather than go. Maintainer acceptance pending.

_Claude (Opus 5.5), 2026-11-14_

## Dated execution history

- 2026-11-14: re-checked all 13 members against current `main` (`9122adf0`). Each still had no production caller; all but `PrivateMessageMailboxService::conversation` had test callers.
- 2026-11-14: discovery (not a scope change). Two members are test helpers, not dead API. `TagGrouping::limitPreview` is the reference algorithm that `TagsIndexMemoryTest` compares the streaming version against. `ImageBackup::copyTree` copies fixtures. Both moved to `tests/Support/` (`TagGroupingReference`, `TestFiles`) instead of being deleted, so `src/` loses them and the tests keep their checks.
- 2026-11-14: tests that reached a removed wrapper now call the production method behind it. Examples: `RouteServices::noStoreHeaders/serverTimingHeaders/mergeResultTimings`, `ActivityService::activityCommitManifest`, `latestForPostContent`, `claimRequestedForPost`, `conversationPageFor`, `conversationPage`, `resolveSource`, `generationInputForType`, `StructuredChatCompletionDecoder`.
- Deleted outright: `AgentReplyGenerationTest::testStoreClaimsRequestedRowsOnce` (claim-once is covered by `testStoreClaimsRequestedRowForSpecificPost`), the two `invalidateProfile` entries in the static-invalidation lists, and the oversized-file assertion in `testRejectsOversizedFilesAndSymlinkEscapes` (covered by the catalog test that checks `available` is false for `large.md`).
- 2026-11-14: correction. The first test run failed `ApplicationServerTimingTest::testMergeResultTimings…` and `PrivateMessageMailboxServiceTest::testConversation…`, which still used removed wrappers. The pre-edit search missed them: a display limit cut one off, and the other names the method in a reflection string. Both retargeted; the follow-up search ran without a display limit.
- 2026-11-14: deleted `Scoring/FastScoreStore` and `Agent/AgentReplyGenerationStore`. `AgentReplyFulfillmentService` is now typed to `SqliteAgentReplyGenerationStore`. No doc named either interface.
- 2026-11-14: `@internal` test-hook notes on the five kept members. Two stale comments now point at the surviving methods (`PlatformDocsCatalog`, `ForteContentAndUserDetailApiController`).

## Outcome

Delivered scope v1: 13 members and 2 interfaces removed, 5 hooks marked. No user-visible change. Not changed, as authorized: the eight 0.35–0.6 members (re-rated in the PR and handoff for the maintainer to decide), `$featureFlags`, `$sections` and every `$repositoryRoot`.

## Verification

- PHPStan dead-code pass, planning commit vs working tree: **passed**. 60 findings before, 39 after. Nothing new appears; the 21 that disappear are exactly the removed members and the interface methods.
- `php -l` on every touched PHP file: **passed**. `git diff --check`: **passed**.
- Smoke set plus ApplicationServerTiming, LocalAppSmoke, SqliteFastScoreStore, AgentReplyGeneration, WriteApiSmoke, AgentResponseGenerator, DedalusPostAnalyzer, PlatformDocsCatalog, PrivateMessageStore, RegistrationBenchmark, TagsIndexMemory, FastmodScoreTransfer, TestFileRegistration, PrivateMessageMailboxService, CommitEvidenceLookup, ContentImportPlanner, PrivateConfigSchema and ApiRouteCatalog: 782 run. After the correction above, every failure caused by this change passes (rerun: 21/21).
- Two failures are **baseline**, reproduced on `main` without this change: `WriteApiSmokeTest::testLlmExchangeLinksAppearOnlyOnTheirPostDetailPages` and `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`.
- Full suite: not run (maintainer runs it).
- Runtime UI checks: inapplicable (no rendered output changes).

## Final review

Diff checked for authorized scope: only the listed classes, their callers, the tests that used removed members, two new test-support files, this record, the index, the ledger and the debug log. No duplication added; the moved helpers live only in `tests/Support/`.

Commit/merge/publication status: planning commit `77ad17d1`; completion commit `bee46630` (confirmed with Git); PR #223 open; not merged or deployed.

_Claude (Opus 5.5), 2026-11-14_

