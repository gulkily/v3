> **Feature plan:** [Step 1](./reply_title_directive_step1_solution_assessment.md) · [Step 2](./reply_title_directive_step2_feature_description.md) · [Step 3](./reply_title_directive_step3_development_plan.md) · [Step 4](./reply_title_directive_step4_implementation_summary.md)

Debug mode: enabled (inherited)
Effort study: `railspike-effort` / `v3/reply_title_directive` — [host ledger](../effort_estimation_trial.md#cycle-v3reply_title_directive); standing enrollment under protocol P2
Extended estimates: enabled (inherited)
**Cycle estimate: 0.85** (Step 3 projection reaffirmed at entry)

# Reply title directive — Step 4: Implementation Summary

## Entry

- 2026-11-03T17:10:31Z: Step 3 explicitly approved ("approved step 3"). Branch `feat/reply-title-directive` from `main` at `e6493e2f`; planning commit `671715b1` (Steps 1–3, debug log, planning index). Ledger and register updates stay uncommitted until closeout.
- Step 4 entry projection: **0.85 reaffirmed**, scope v2, R1/P2, Claude Code single exposed rater (model claude-fable-5-1). Stage identities and original stage-work estimates copied from the approved Step 3 plan (copying is not a new rating): S1 0.08, S2 0.08, S3 0.15, S4 0.15, S5 0.12, S6 0.20, S7 0.12, S8 0.12; non-additive.
- Enhancement requests: "suggested titles from unauthorized repliers" pending, out of scope.

*— Claude Code · 2026-11-03 UTC · Model: claude-fable-5-1*

## Stage 1 - Subject record provenance and spec

**Original estimate: 0.08**
**Retrospective stage-work assessment: 0.06**

- Changes:
  - `ThreadSubjectRecord` gains nullable `sourcePostId` and `sourcePostSha256`.
  - `ThreadSubjectRecordParser` reads `Source-Post-ID`/`Source-Post-SHA256` with the reaction-record validation (both or neither, ID token and 64 lowercase hex).
  - New `docs/specs/thread_subject_record_v1.md`: format, system versus authored records, precedence and withdrawal, signature and compatibility notes.
  - `tests/CanonicalRecordParsersTest.php`: provenance pair parses; lone or invalid headers rejected.
- Verification:
  - `php tests/run.php CanonicalRecordParsersTest`: 54 run, 54 passed (2026-11-03T17:11:59Z).
  - `php tests/run.php LocalWriteServiceThreadSubjectTest ReadModelThreadSubjectsTest`: all passed; existing system records parse unchanged.
  - `git diff --check` clean. UI, deployment and migration checks not applicable: no behavior or schema change in this stage.
- Notes: precedence and withdrawal described in the spec are implemented in Stages 4 and 7; until then the spec documents intended behavior for this branch.
- Estimate details: original scope unchanged; uncertainty low; no revisions. Retrospective 0.06: smaller than planned because the reaction parser's validation transferred directly.

*— Claude Code · 2026-11-03 UTC · Model: claude-fable-5-1*

## Stage 2 - Feature flags

**Original estimate: 0.08**
**Retrospective stage-work assessment: 0.08**

- Changes:
  - `FeatureFlagRegistry`: `FORUM_TITLE_DIRECTIVE_ENABLED` (default on, site-mutable) and `FORUM_TITLE_DIRECTIVE_APPROVED_MEMBERS` (default off, site-mutable, requires the first), appended to the Forum experience presentation list, parent then child.
  - `tests/FeatureFlagEvaluatorTest.php`: both keys added to the public-flag list and the environment-reset list; new dependency test (defaults on/off; child blocked with source `dependency` when parent off).
- Verification:
  - `php tests/run.php FeatureFlagsBehaviorTest FeatureFlagEvaluatorTest FeatureFlagChangePageTest`: all passed (2026-11-03T17:15:11Z); order/completeness/dependency assertions cover the new flags.
  - `php tests/run.php LocalAppSmokeTest`: 162 run, 159 passed; all five Feature Flags page tests pass, including every registered flag rendered once and dependency dimming. The 3 failures (private lobby copy, Forte expired-session copy, archive download filename) reproduce identically on the unchanged baseline `e6493e2f` in a throwaway worktree; not regressions.
  - `git diff --check` clean. No reference doc lists flags individually (the registry is canonical); nothing to update there.
- Notes: flags have no consumer yet; Stage 3 reads them in the write path.
- Estimate details: original scope unchanged; no revisions; retrospective 0.08 as planned.

*— Claude Code · 2026-11-03 UTC · Model: claude-fable-5-1*

## Stage 3 - Directive extraction, authority and write

**Original estimate: 0.15**
**Retrospective stage-work assessment: 0.18**

- Changes:
  - New `Support/TitleDirective`: `extract(string $body): ?string` with hashtag line rules (quoted lines skipped, first match, case-insensitive key, trimmed one-line value, empty ignored).
  - New `Security/TitleDirectiveAuthority::allows(root, pdo, rootPost, identity, flags)`: directive flag on, then verified root author (`posts.attribution_verified`), current operator, or approved member when the second flag is on.
  - `LocalWriteService`: `buildThreadSubjectRecord` takes author, provenance and reason; new `titleDirectiveRecord()` decides and builds the authored record (invalid title text or a missing root yields no record, never an error); `createReply`, `createPreparedPost` (replies only) and `createSignedPost` (replies only) write it in the same commit as the reply; `synchronizePostDerivedState` gains a subject-write flag that runs `applyThreadSubjectWrite`; a retitle also invalidates board/thread static artifacts.
  - Incidental correction in the code edited: `createSignedPost` called an undefined `buildThreadLabelRecord`; it now calls `buildReactionRecord(THREAD, …)` with the same arguments. Pre-existing bug on the hashtag path of signed bundles; no test exercises that path, so this correction is unverified by test and is flagged for review.
  - New `tests/LocalWriteServiceTitleDirectiveTest.php` (registered in `tests/run.php`): extraction rules; author retitles through unsigned reply with a linked identity; operator retitles any thread; stranger, guest, quoted and empty directives write nothing and the stranger's reply keeps its line; directive flag off (site record) blocks the operator; authority unit test for approved-member flag and unverified root; signed prepared reply through the real API routes with a generated key writes the record in the same commit as the reply.
- Verification:
  - `php tests/run.php LocalWriteServiceTitleDirectiveTest`: 7 run, 7 passed (2026-11-03T17:25:33Z).
  - `php tests/run.php WriteApiSmokeTest LocalWriteServiceThreadSubjectTest ReadModelThreadSubjectsTest ReactionRecordFormatParityTest`: 201 run, 200 passed. The one failure, `ReactionRecordFormatParityTest::testMixedFormatsBuildTheSameReadModelAsLegacy`, fails identically on the unchanged baseline `e6493e2f`; not a regression.
  - `git diff --check` clean. Displayed title does not change yet: the reducer still fills only empty subjects until Stage 4, so these tests assert record contents, not page output.
- Notes: unsigned replies require a linked identity (`author_identity_id` must resolve), so tests link real keys. Authority reads the read model current at write time.
- Estimate details: original scope unchanged; retrospective 0.18, above the original because the signed-path test needed real key generation and the identity requirement was discovered during testing (new understanding, not changed scope).

*— Claude Code · 2026-11-03 UTC · Model: claude-fable-5-1*
