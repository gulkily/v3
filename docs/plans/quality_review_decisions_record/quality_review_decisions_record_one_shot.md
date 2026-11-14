# Record the code quality review's decisions and lessons

Cycle: `v3/quality_review_decisions_record`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on` present; no explicit request)

Effort study: `railspike-effort`, protocol P2, reference R1; [host ledger](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)

Extended estimates: enabled (new enrolled cycle default); no real alternatives or work units, so no unit estimates

**Cycle estimate: 0.10 (intake)**

Status: documentation complete; PR open, stacked on #223 and #227.

## Original request

After PR #227, the maintainer wrote on 2026-11-14: "let us please document our findings here". "Here" is read as the review's own records, the [findings](../code_quality_review_findings_v1.md) and [checklist](../code_quality_review_checklist.md), which don't yet record how decisions 1–8 ended or what the later rounds learned.

Feedback items: none match.

## Authorization

Documentation only. The request covers recording the review's outcomes and lessons. It does not cover code, tests, or deciding the two held items (`buildSingleRoute`, `PresentationSlotRegistry::all`).

## Outcome and work type

Documentation. Allowed paths: `docs/plans/code_quality_review_findings_v1.md`, `docs/plans/code_quality_review_checklist.md`, this record, the plans index, the ledger row and the debug log.

Reader outcome: someone picking up the review sees each decision's outcome and PR in one place, which items are still open, and the rating and search mistakes to avoid next time.

## Approach and risks

Add a "Decisions and outcomes" section and a "Lessons for the next pass" section to the findings, update the Summary and §8 lines that are now out of date, and update the checklist's test-only item and ground rules.

- Risk: claiming PRs merged that aren't. Mitigation: state each PR's merge state as checked on 2026-11-14. #223 and #227 are open; this branch stacks on #227.

## Reuse decisions

Existing findings and checklist; no new document.

## Completion conditions

Both documents state every decision's outcome, the held items and the lessons; links resolve; `git diff --check` passes.

## Concepts touched

None.

## Verification and recovery plan

Check links and referenced paths, run `git diff --check`, and run the Markdown/docs tests. Runtime checks are inapplicable. Recovery: revert the PR.

Starting revisions and branch: PR #227 branch tip `f055f544` (on `origin/main` + #223 + #227); branch `docs/quality_review_decisions_record`.

Planning commit: `5607aeff` (confirmed with Git).

## Study history

- 2026-11-14 intake: 0.10, scope v1. Prior exposure: the same agent ran the whole review.
- 2026-11-14 execution entry: 0.10 reaffirmed.
- 2026-11-14 closeout: scope v1 delivered. Retrospective scope assessment: 0.10. Maintainer acceptance pending.

_Claude (Opus 5.5), 2026-11-14_

## Dated execution history

- 2026-11-14: checked the merge state of every cited PR (#139–#163 merged; #223 and #227 open).
- 2026-11-14: findings gain "Decisions and outcomes" (a table of decisions 1–8, the middle-eight calls, the held items, the four bugs found and fixed, and the current baseline failures) and "Lessons for the next pass" (six lessons). The Summary links to both, and §8's open interface question is marked resolved.
- 2026-11-14: checklist's test-only item ticked, with the held items named; new ground rule to list test callers before rating.

## Outcome

Both documents now say how each decision ended, what is still open (the two held items, and #223/#227 awaiting merge) and what to do differently next time.

## Verification

- Links and referenced paths in both documents: **passed** (script check, no broken relative links).
- `git diff --check`: **passed**.
- Smoke set plus PlatformDocsCatalog, DocsPublication, MarkdownListStructure and LlmsTxtRenderer: 474 run, 473 passed. The one failure is the **baseline** `testThreadCardWrapsMediaEmbedPreviewInDiv`, recorded in the findings.
- Runtime checks: inapplicable (documentation only).

## Final review

Only the two review documents, this record, the index, the ledger and the debug log changed. No code or tests.

Commit/merge/publication status: planning commit `5607aeff`; completion commit `5d9d02ae` (confirmed with Git); PR #228 open, stacked on open PRs #223 and #227.

_Claude (Opus 5.5), 2026-11-14_

