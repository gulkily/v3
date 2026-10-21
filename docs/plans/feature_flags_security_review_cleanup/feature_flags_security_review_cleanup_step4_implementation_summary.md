> **Feature plan:** [Step 1](./feature_flags_security_review_cleanup_step1_solution_assessment.md) · [Step 2](./feature_flags_security_review_cleanup_step2_feature_description.md) · [Step 3](./feature_flags_security_review_cleanup_step3_development_plan.md) · [Step 4](./feature_flags_security_review_cleanup_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/feature_flags_security_review_cleanup` · [Ledger](../effort_estimation_trial.md#enrollment-index-after-p2)

# Access history — implementation

## Entry checkpoint
- Step 3 approved. Planning commit `e563a057`; runtime starting revision `0b9f5f8baba58b31251b6a6a6b228412784328fe`. Four stages; no pending requests or changed scope.
- Step 4 entry, 2026-10-21 UTC, scope v2, R1/P2, Codex exposed single rater (verified model identifier unavailable): **0.45 total reaffirmed**, including prior planning/mockup, implementation, verification, documentation and correction. Earlier frozen references and scores preserved; no independent rating or measured labor.

## Stage 1 - Protected entry and recovery
- Changes: replaced HTML-only security denials with a no-store access-history recovery page using existing signed PrivateSiteAuth and fixed internal return targets; retained plain API/RSS denials and non-operator restrictions. Removed Feature Flags paragraph. Added reusable authentication prompt and retry binding.
- Passed: `php tests/run.php WriteApiSmokeTest::testOperatorSecurityReviewRequiresAuthenticatedRootAndCorrelatesPolicyChanges` (1/1); `node tests/browser/access_history.mjs`; `git diff --check`.
- Evidence: isolated browser recreated hint-only HTTP 403 without private evidence, failed authentication and successful retry, actual signed session establishment, repeated expiry/resume, keyless recovery and ignored external return targets. This reproduces the source-supported reported one-line condition; the user's original browser session itself was not inspected.
- Notes: authenticated non-operators remain denied; no hint grants audit authority. Existing audit history can also be sparse; empty presentation is handled in later stages. No schema or endpoint contract change.

## Stage 2 - Recent history and evidence
- Changes: added five-event bounded history to User Access using existing query/enrichment methods; View all appears only with older events. Shared presenter/event partial uses existing receipt facts, canonical source links, concise summaries and expandable evidence, preserving unknown historical classifications. Hint-only operators receive recovery without protected data; authenticated non-operators do not see history. Existing account/pending controls retained.
- Passed: `php tests/run.php AccessHistoryPresenterTest WriteApiSmokeTest::testOperatorSecurityReviewRequiresAuthenticatedRootAndCorrelatesPolicyChanges` (4/4); `node tests/browser/access_history.mjs`; `git diff --check`.
- Evidence: PHP covers zero/four/five-of-seven events, conditional full-history link, receipt precedence, legacy/unknown facts, invitation/policy summaries and protected roles. Browser covers empty/five/six events from actual signed policy writes, expanded source links, authentication failure/retry and expiry.
- Notes: no SQL/schema changes; reused limit/has-more and five bounded detail enrichments. Stage 1 commit `3df2e156`. Full-history rendering/paging and broader theme acceptance remain later stages.

## Stage 3 - Full history and shared views
- Changes: standard security history now uses the shared event presentation, useful empty state, User Access return and stable cursor-based Older/Recent events links. Existing standard/Forte security URLs remain. Forte rows use concise summaries; selected evidence shares the event partial. Ordinary Activity rendering and API payload shapes retained. Invalid classic cursors offer a recovery link.
- Passed: four focused PHP tests; `node tests/browser/access_history.mjs`; `node tests/browser/forte_activity_parity.mjs`; `git diff --check`.
- Evidence: actual signed recent/full agreement, 111 canonical events including 105 legacy records sharing a timestamp, 100/11 page boundary without duplicate/missing IDs, legacy uncertainty, source expansion, invalid-cursor recovery, Forte detail/page parity and return to User Access.
- Corrections: consolidated an existing duplicate User Access stylesheet-map key so new CSS actually loads; fixed Forte's client-side security view falling back to All Activity because the private category intentionally has no ordinary folder-tree entry. A server-authorized view marker preserves the private category without adding navigation. Initial browser/test failures identified these issues and were corrected before commit.
- Notes: reused existing query/cursor contracts, no SQL/schema changes or new/renamed navigation destination. Stage 2 commit `b37e11af`. Theme/keyboard/role acceptance remains Stage 4.

## Stage 4 - Browser acceptance and closeout
- Changes: extended the isolated browser harness with actual member approval/session and forged-hint denial, empty/recovery states, keyboard/source expansion, recent/full-history agreement, canonical invitation/legacy fixtures, shared-date pagination, all-theme overflow and existing icon checks. Preserved relevant-file and signing-key evidence inside Source evidence. Updated the UI audit checklist and plan navigation.
- Passed: 28 focused PHP checks covering presenter, approval policy/history, flag behavior, offline exclusions, ordinary Activity/Forte/source/paging and account directory; 19 authentication/navigation/activity-link/recovery checks (47 total). `node tests/browser/access_history.mjs` and `node tests/browser/forte_activity_parity.mjs` pass. PHP/JS syntax and `git diff --check` pass.
- Browser evidence: normal Tools → User Access, actual signed session recovery after injected failure/expiry, keyless account recovery, unsafe-return handling, member/forged-hint protection, grant/invitation/policy/legacy events, empty history, event/source expansion, older/recent/back/return, and 111-event tied-date paging. Recent and expanded full history fit 390/1280 px in all 16 themes. Reviewed light desktop and Midnight phone screenshots under `/tmp/access-history-{theme}-{width}.png`. User Access launcher/subnavigation icons checked in Word97/Chicago/Midnight; no new destination or icon mapping required.
- Limitations: exact original browser session unobserved; isolated tests separately reproduce the one-line hint-only denial and sparse authorized history. Chromium only; full suite, production deployment and live-instance mutation were not performed. No schema change/migration or approval-policy behavior change, so schema startup/rebuild requirements are inapplicable.
- Commits: Stage 1 `3df2e156`, Stage 2 `b37e11af`, Stage 3 `2c367f7d`; this Stage 4 evidence/source-preservation commit follows them. Initial assertion and browser failures were corrected within their stages; no unresolved focused failures or deferred requirements. No new feature requests or scope splits.

## Study closeout
- Study `railspike-effort`, stable cycle `v3/feature_flags_security_review_cleanup`; scope v2; R1/P2; 2026-10-21T20:58:23Z; Codex single exposed rater, verified model identity and measured active work unavailable. Start `0b9f5f8baba58b31251b6a6a6b228412784328fe`; planning `e563a057`; four stages planned/completed, with stage-scoped commits and verification. Final stage hash is recorded in the subsequent debug handoff commit.
- Projected total **0.45 preserved**; retrospective scope assessment **0.45**, separate from predictions. Delivered embedded recent history, shared full/Forte evidence, paging and operator recovery. Ordinary corrective work includes stylesheet-map consolidation, private Forte category preservation and updated copy assertions, not new requests. Verification and omissions are listed above; no independent second rating, deployment or full-suite pass claimed.
- Maintainer comfortable-fit judgment/acceptance **pending**. Technical completion does not increment accepted study outcomes; original v1/0.18 and revised v2/0.45 snapshots remain intact. No parent/replacement cycle or duplicate-worktree outcome.
