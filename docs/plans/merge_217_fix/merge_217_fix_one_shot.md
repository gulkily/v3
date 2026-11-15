# Fix the PHP parse error left by the #217 merge

Cycle: `v3/merge_217_fix`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/merge_217_fix`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)
Debug report: [log](merge_217_fix_debug_log.md)

**Cycle estimate: 0.03**

Status: fix committed and pushed; [PR #242](https://github.com/gulkily/v3/pull/242) open. Not merged or deployed.

## Original request

Maintainer, 2026-11-14 15:25 ET, in a Claude session: "we messed up a merge please help me fix", then "pr". No feedback item matches (searched `docs/feedback/items/`).

## Authorization

The two messages above authorize a fix, a branch and a PR. They do not authorize merging, deploying or production access.

## Outcome and work type

Application fix, one PHP file. `main` at `393a64d5` (merge of #217) fails `php -l` on `src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php` with `unexpected single-quoted string "RENDERING"`, so every request fails. After the fix the file parses and `PRESENTATION_ORDER['EXPERIENCE']` lists both `PENDING_CHIP_APPROVAL_ENABLED` (#217) and `USAGE_REPORTS_ENABLED` (#238), in that order.

Excluded: any other change from #217 or #238.

## Approach and risks

Cause: merging `main` into `feat/pending_chip_approve` (`e6ce2d95`) conflicted on the last `EXPERIENCE` line, which both sides had extended. The resolution kept both versions, closing the array twice. Replace them with one line holding both flags. `git show --cc e6ce2d95` shows the only other hand-resolved hunks (the flag definitions and the two lists in `FeatureFlagEvaluatorTest`) combined correctly; `git grep` finds no conflict markers. Risk: low.

## Reuse decisions

None needed; the existing constant list is edited in place.

## Completion conditions

- Every PHP file the merge touched passes `php -l`.
- Smoke set plus `FeatureFlagEvaluatorTest`, `BoardListTest`, `AgentApiWorkedExampleTest`, `ApiRouteCatalogTest` pass, apart from failures already on `main`.
- Release: PR merged and deployed by the maintainer.

## Concepts touched

None.

## Verification and recovery plan

As in completion conditions. Full suite not run, per `AGENTS.md`. Production not checked.

Starting revision and branch: `fix/merge_217_flag_order` from `393a64d5`.

## Dated execution history

- 2026-11-14 15:27 ET: fix committed (`67c0d7d1`). `php -l` clean. Smoke plus the four tests: 464/465 passed; the failure, `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`, was failing before today's merges. Pushed; PR #242 opened.

## Retrospective

Work matched the 0.03 estimate. Maintainer's judgment of fit: pending.
