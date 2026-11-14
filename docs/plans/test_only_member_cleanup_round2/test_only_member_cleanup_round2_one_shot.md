# Test-only member cleanup, round 2

Cycle: `v3/test_only_member_cleanup_round2`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on` present; no explicit request)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: enabled (new enrolled cycle default); no real alternatives or work units, so no unit estimates

**Cycle estimate: 0.20 (intake)**

Status: planned; next permitted action: planning commit, then implementation.

Parent: `v3/test_only_member_cleanup` ([record](../test_only_member_cleanup/test_only_member_cleanup_one_shot.md), PR #223, unmerged). This is a follow-up with its own identity. Its branch starts from the parent's branch tip `c4126a45`, so it should merge after #223.

## Original request

In the [review thread](https://zenmemes.com/threads/thread-20261111012557-b02a1583) (decision 2, reply-20261114185245-f0927801) the agent re-rated the eight 0.35–0.6 test-only members: keep `PostRecord::rendersMarkdown` and `ViewerSession::isSignedIn`; remove `PresentationSlotRegistry::all`, `LocalWriteService::setFeatureFlag`, `PrivateMessageStore::markSeenThrough`, `ImageBackup::cachedBytes`, `FastScoreResult::scored` and `StaticArtifactBuilder::buildSingleRoute`. It also suggested removing the unread properties `AgentReplyFulfillmentService::$featureFlags` and `FeedbackItem::$sections` (decision 4).

On 2026-11-14 the maintainer wrote: "leave setFeatureFlag in case we can't sign records. the rest as you marked them."

Feedback items: none match.

## Authorization

Remove `PresentationSlotRegistry::all`, `markSeenThrough`, `cachedBytes`, `FastScoreResult::scored`, `buildSingleRoute`, `$featureFlags` and `$sections`; keep `rendersMarkdown`, `isSignedIn` and `setFeatureFlag`, with a note on each saying why. Merge and deployment are not authorized.

**Held at intake (2026-11-14), pending renewed authorization:** `buildSingleRoute` and `PresentationSlotRegistry::all`. A precise search before planning found that these are test seams, which the re-rating hadn't accounted for:

- `buildSingleRoute` renders one static page for 9 test files and fixtures (`PlatformDocsStaticTest`, `ThreadThemeTest`, `StandardActivityPaginationTest`, `LocalAppSmokeTest`, `tag_page_memory.php`, `bookmarklet_browser.php`, `profile_public_details_fixture.php` and others). `PlatformDocsStaticTest` also uses it to check that per-route path safety rejects `/docs/%2e%2e/README.md`.
- `PresentationSlotRegistry::all` lets `PresentationSlotRegistryTest` check every slot definition for consistency.

Removing either would mean rewriting those tests around full builds or reflection. That cost differs materially from what the maintainer approved, so they stay unchanged in this cycle and go back to the maintainer.

## Outcome and work type

Internal maintenance (no user-visible change). Allowed paths: the five classes holding the removed or kept members, `PostWorkflowService` (constructs `AgentReplyFulfillmentService`), `FeedbackItemCheck` (constructs `FeedbackItem`), the tests calling removed members, this record, the plans index, the ledger row and the debug log.

## Approach and risks

- `markSeenThrough` passes straight through to `PrivateMessageReadState::mark`; its tests (including the race test's inline script) call `PrivateMessageReadState::mark` on the same PDO.
- `cachedBytes` is `cachedArchive($kind)['bytes']`; `ArchiveExportTest` uses that instead.
- `FastScoreResult::scored` is used only by a shape assertion in `FastScoreContextFactoryTest`. The test's other assertions cover the same shape, so that one assertion goes.
- `$featureFlags`: drop the constructor parameter and the argument `PostWorkflowService` passes.
- `$sections`: drop the property and the argument `FeedbackItemCheck` passes; the checker still parses and validates sections locally.
- `setFeatureFlag`, `rendersMarkdown` and `isSignedIn`: add a one-line note saying why each is kept.
- Risk: a positional constructor call elsewhere (tests, fixtures) breaks when a parameter goes. Validation: search for `new AgentReplyFulfillmentService(` and `new FeedbackItem(` across `src/`, `tests/` and `scripts/` before editing, and run the PHPStan dead-code pass before and after.

## Reuse decisions

No new code; tests move to the production methods named above.

## Completion conditions

Five members gone (or three, while the two held items wait); the three kept members carry notes; the two held items unchanged; tests pass apart from known baseline failures; nothing new in the dead-code pass.

## Concepts touched

None.

## Verification and recovery plan

`php -l`, `git diff --check`, smoke set plus the touched tests, and the PHPStan dead-code pass before and after. Runtime UI checks are inapplicable. Recovery: revert the PR.

Starting revisions and branch: parent branch tip `c4126a45` (on `origin/main` `68724f9b` + #223); branch `chore/test_only_member_cleanup_round2`.

Planning commit: pending.

## Study history

- 2026-11-14 intake: 0.20, scope v1. Prior exposure: the same agent rated and searched these members before planning.
- 2026-11-14 execution entry: 0.15, revised down because the two held items are out of the executable scope for now.

_Claude (Opus 5.5), 2026-11-14_
