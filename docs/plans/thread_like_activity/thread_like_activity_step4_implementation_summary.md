> **Feature plan:** [Step 1](./thread_like_activity_step1_solution_assessment.md) · [Step 2](./thread_like_activity_step2_feature_description.md) · [Step 3](./thread_like_activity_step3_development_plan.md) · [Step 4](./thread_like_activity_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort`, `v3/thread_like_activity`; [ledger](../effort_estimation_trial.md#cycle-v3thread_like_activity).

## Entry

- Step 3 approved; branch `feat/thread-like-activity`; planning commit `ab24d158` verified at HEAD before summary/application edits.
- Scope v1: every eligible canonical like contribution is reported once, independent of prior label presence. Different signing keys can have the same displayed username; the actual local incident uses two keys both currently named `ilyag`. No claim that the user previously clicked Like is required for the fix.
- 2026-10-23 UTC; baseline `39be82932fcc36c5017fcf368655ef449bf042da`; Codex single exposed rater; R1/P2. Step 3 total projection remains unavailable pending exact frozen-reference recovery, not zero or measured effort. Broader repository coverage, unlike controls and inactive-source duplicate-guard fixes remain separate.

## Stage 1 — completed

Full and incremental derivation now emit one event per eligible like-bearing record, retaining mixed-label grouping and existing score deduplication. Normal signed writes, duplicate retry, distinct same-author comments and rebuild parity passed; existing retraction coverage confirms withdrawn contributions stay excluded. Focused verification: 10 tests passed. No eligibility or vote calculation changed.

## Stage 2 — completed

Incremental refresh resolves each canonical label path's own commit instead of borrowing the triggering write's commit. Unavailable/invalid history is stored as null. Actor, original timestamp and canonical action keys are preserved. Five focused tests passed, including three distinct source commits compared against Git and full rebuild, one-row cursor traversal/count agreement, duplicate retry, and existing link contracts. Existing source/signature enrichment remains shared by consumers.

## Stage 3 — completed

Read-model version 24 forces normal startup to recover omitted like events from version-23 databases, without changing table shape or canonical records. The schema specification describes the derivation change. Both focused tests passed: a deliberately omitted second like was recovered before normal Activity rendering, `./v3 rebuild` retained both items without adding records, and existing incremental-failure/full-rebuild fallback remained healthy.

## Stage 4 — completed

- **Known incident passed in isolation.** Copied the local content repository to a temporary directory; baseline `39be8293` rebuild produced one like event for `thread-20261010002251-6cb36993`, while the fixed rebuild produced both canonical contributions. The recovered record is `thread-label-e4b175bfa8628a2c86d7169d1c6f7519`, timestamp `2026-10-23T02:23:22Z`, commit `78e1f3fccd3d9fc5b9976ad608c5895731ca78c4`. Earlier contribution retains `5ae0cfa0cf9f8eef75b9d2ecac3013d7550a7088`. Both rebuilds report score 1, vote count 0, labels `["like"]`. Original canonical-record tree SHA-256 remained `e8400143ab4e34e3c5c1d3fcb7723e7d07e5f0fa3219c98b559ad09553807531`; no original content or database was modified.
- **Normal browser flow passed.** Extended `tests/browser/signed_reactions.mjs` seeds an earlier contribution, accepts a real signed Like button action and finds the second event in classic/Forte Activity, source/commit links and RSS. Verifies actual 390/1280 widths, Classic Light/Word97/Chicago/Midnight and Forte's corresponding light/dark schemes, empty approval lists, expanded source evidence and no page overflow. Phone Midnight screenshots were visually inspected. Existing guest setup, legacy unsigned submission, signing failure, Forte clock correction and QDB flows passed.
- **Shared UI recovery passed.** Existing `forte_activity_parity.mjs` covers phone/desktop category membership, bounded preloading, detail/page failure and retry, keyboard selection, duplicate-page merging, stale sort responses and security boundaries. Real canonical paths, full fingerprints and expanded commit metadata exercise long values; normal Like acceptance and source signatures were checked against saved bytes.
- **Focused PHP verification passed:** 29 tests across label/reaction/retraction derivation, Activity recovery/links/shared manifests/counts, signed APIs, QDB/non-QDB vote behavior, version-23 startup and fallback. After adding missing-history coverage, the contribution regression passed again: incremental refresh with no Git history stores null instead of an unrelated triggering SHA and matches full rebuild. One-row cursor traversal checks count agreement, unique action keys and inclusion of every contribution. All five changed PHP files pass syntax checks; JavaScript browser execution and whitespace checks pass.
- Browser harness correction: rapid reuse of a page across theme navigations intermittently aborted navigation. Each matrix case now opens a fresh page, verifies its actual viewport and theme/scheme, loads fully and closes afterward; the complete final run passed. RSS assertions follow the existing feed contract (two like items), which does not expose canonical record IDs.

## Verification boundaries and recovery

All four approved stages are delivered in separate summary-bearing commits after the planning commit. No new controls, copy, routes or navigation destinations were introduced, so the copy-cleanup checklist and navigation icon mappings are inapplicable. The schema specification documents version 24 and operator recovery. Existing installations rebuild at normal application startup; `./v3 rebuild` also restores omitted events. No canonical replacement likes are needed.

Live/production deployment, the user's live browser and physical devices remain **unverified**; original user content was inspected read-only. A full-suite pass is **not claimed**: only the focused suite ran for this cycle. PHP GD is still absent; the preceding cycle reproduced two image failures on this same baseline. Production deployment and external-system migration are outside this local implementation scope. Broader repository coverage, button-like withdrawal and the inactive-source duplicate guard remain separate follow-ups. Unrelated drafts and `.test_self_approval` are preserved.

## Study closeout

2026-10-23 UTC; `railspike-effort`, `v3/thread_like_activity`, scope v1, baseline `39be8293`, single exposed Codex rater, R1/P2. **Retrospective scope assessment:** four bounded stages delivered the approved writes/history/recovery/browser slice without expanding scoring or withdrawal scope. Numeric retrospective and projected totals remain unavailable pending exact frozen R1 reference recovery; this is not a zero rating or measured effort. Actual verification and omissions are recorded above. Maintainer comfortable-fit judgment and acceptance are **pending**; no accepted-count increment, independent rating or parent all-repository completion is claimed.
