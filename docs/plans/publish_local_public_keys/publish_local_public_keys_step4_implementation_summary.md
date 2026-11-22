> **Feature plan:** [Step 1](./publish_local_public_keys_step1_solution_assessment.md) · [Step 2](./publish_local_public_keys_step2_feature_description.md) · [Step 3](./publish_local_public_keys_step3_development_plan.md) · [Step 4](./publish_local_public_keys_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/publish_local_public_keys; [cycle ledger](../effort_ledger/publish_local_public_keys.md), standing P2 enrollment.
Extended estimates: enabled

**Cycle estimate: 0.65**

Status: all four implementation stages locally complete; production untouched and acceptance pending.
Branch: feat/publish-local-public-keys
Application baseline: 8b00a78be (freshly fetched origin/main)
Planning commit: 68e4c4269
Authorization: Maintainer approved Steps 1, 2 and 3 in this conversation; latest request "approved step 3". Authorizes implementation and stage commits, not production access, deployment, merge or external replies.
Scope: v1/A public-key-only CLI. No approvals, private keys, key updates, user-post transfer, new API, dependencies or schema changes.

## Step 4 entry

2026-11-22T22:41:11+00:00: 0.65 reaffirmed after the required planning commit. Frozen R1/P2, Codex single exposed rater; original stage work 0.18, 0.22, 0.15, 0.08 copied from approved Step 3, not summed. Fresh main changed test discovery and SQL insert guidance; no selected registration/helper contracts changed. No measured work or independent rating claimed.

Reuse and risks: use the existing inspector, identity/name/retirement parsers, public get_profile and link_identity routes. Add only missing CLI orchestration and bounded transport, as justified in the [assessment evidence](publish_local_public_keys_assessment_evidence.txt). Strict public-only packet classification is required before network access. Bind reviewed source/destination; reconcile lost responses before retry. Production and physical-device verification remain unclaimed.

## Stage 1 - Read-only preview

**Original estimate: 0.18**

**Retrospective stage work: 0.20**

- Status: locally complete; commit 78da8c12c.
- Changes: added ordinary publish-keys preview, canonical inventory validation, typed bounded destination reads and opt-in strict public inspection. Reused existing parsers/inspector and left legacy inspect semantics intact. Added public-key publication concept and reconciled the exact accepted F03 intake without closing it.
- Verification: focused PublishKeysCommandTest 4/4 passed, including GET-only preview, private packets under public armor, symlink rejection, refusal, name/retirement mismatch and unexpected responses. Syntax, help and diff checks passed. Smoke plus PublishKeysCommandTest/OpenPgpKeyInspectorTest/CanonicalRecordParsersTest: 497 run, 492 passed, five failed. Four Unicode-related failures reproduce on untouched 8b00a78be (4/4 selected baseline tests fail identically). ViewerSessionStoreTest concurrency hit a SQLite lock once, then passed individually on this branch and baseline. Full suite not run.
- Notes: initial fixture cleanup used the wrong helper name; corrected to GpgHomeCleaner::dispose and all focused checks recovered. No product exception was hidden. Feedback check passed 356 records, zero problems; index regenerated. No source, private key or production instance was published. Existing inspector/registration consumers remain covered by smoke/inspector tests. No dependency/schema/UI changes. Source family type checks and absolute response deadline were tightened inside this stage; focused preview checks reran successfully.
- Estimate details: original 0.18 retained; retrospective 0.20 for inventory/transport and meaningful fault checks. Same v1/A, R1/P2 and exposed rater; not measured labor.

## Stage 2 - Publication and retry

**Original estimate: 0.22**

**Retrospective stage work: 0.25**

- Status: locally complete; commit 3304d9083.
- Changes: explicit fingerprint selection, exclusive Key: value review files outside canonical source, source/destination binding, all-selection validation before the first write, bounded public-key-only registration and mandatory read-back. Each invocation attempts each missing selection once; reruns skip confirmed registrations and preserve mixed outcomes.
- Verification: smoke plus PublishKeysCommandTest/PublicKeyPublisherTest: 494 run, 490 passed, four known Unicode failures reproduced on untouched baseline. Final focused PublicKeyPublisherTest: 5/5 passed, including source changes in another selected key, source-byte changes, review-file preservation, ambiguous responses, concurrent registration, partial refusal and reruns. Syntax/help/diff checks passed. Full suite not run.
- Estimate details: original 0.22 retained, retrospective 0.25; same v1/A, R1/P2 and exposed rater. Projection 0.65 reaffirmed; not measured labor. Production untouched and acceptance pending.

## Stage 3 - Ordinary-flow acceptance

**Original estimate: 0.15**

**Retrospective stage work: 0.17**

- Status: locally complete; commit f3d24dd33.
- Changes: reused SingleRequestHttpTest::server/FrontendTestServer and the ordinary bash v3 CLI against the actual FrontController/get_profile/link_identity routes. Verified registered key/name, unchanged local source, destination commit changes only on first publish, unapproved status and duplicate-free rerun. Corrected JSON access-denial classification and required the exact typed missing-profile response.
- Verification: final focused PublicKeyPublishHttpTest 5/5 passed: actual registration and restricted instance, TLS failure, oversized/unexpected 404, timeout/recovery, cryptographically verified rename and incomplete retirement evidence. Each unsafe source check made zero requests. Smoke plus PublicKeyPublishHttpTest/PublicKeyPublisherTest/PublishKeysCommandTest/IdentityBootstrapDiagnosticsTest and the two link-identity name/bootstrap WriteApiSmokeTest cases: 505 run, 501 passed, only the four reproduced baseline Unicode failures. All new and selected shared-consumer tests passed. Syntax and diff checks passed. Full suite not run.
- Notes: no browser UI, themes, roles, schema/migration or deployment changes; UI/theme checks inapplicable. Existing registration/inspector semantics remain intact; shared bootstrap consumers selected for verification. Live production and physical-device acceptance remain pending and require separate authorization.
- Estimate details: original 0.15 retained, retrospective 0.17; same v1/A/R1/P2/exposed rater, cycle projection 0.65 unchanged. Not measured labor.

## Stage 4 - Documentation and handoff

**Original estimate: 0.08**

**Retrospective stage work: 0.08**

- Status: locally complete; shared release documentation owner.
- Changes: documented ordinary preview/publish/retry, explicit source and destination, plan placement, exit states and approval boundary in docs/reference/v3_cli.md; indexed the launcher in scripts/README.md and added its required command comment. Updated plan index and retained F03 as an open broader request.
- Verification: ScriptsIndexTest initially found the missing launcher comment; corrected and reran 3/3 passed. CLI help, all changed PHP syntax, bash syntax and git diff --check passed. Feedback check: 356 items, zero problems, 36 existing source-review notices; regenerated index unchanged. New/changed feature links and concept source paths resolve. The wider plans README already contains a broken Windows ME link on untouched 8b00a78be; left outside scope. No runtime changes in this stage, so prior Stage 3 smoke/ordinary CLI evidence applies. Full suite not run.
- Notes: no dependencies, schema, migration, browser UI or theme changes. Production, deployment, merge and physical-device checks not performed; live acceptance pending. No external messages, push or PR. Original workspace drafts and mockups preserved; this branch/worktree is the canonical implementation, not a separate study outcome.
- Estimate details: original 0.08 retained; retrospective 0.08. Same v1/A/R1/P2 and exposed rater, not measured labor.

## Closeout

**Retrospective cycle scope assessment: 0.75**

2026-11-22: Whole-cycle judgment made separately from stage values, including planning/inspection, strict public-only validation, bound review artifacts, ordinary CLI/API acceptance and fault checks; not the sum of stage values. Original 0.50 and projection 0.65 retained. R1 hashes reverified. Scope v1/A unchanged; start 8b00a78be; planning 68e4c4269; preview 78da8c12c; publication 3304d9083; acceptance f3d24dd33; documentation commit is the commit introducing this closeout. Four planned stages completed. Measured active work unknown. Maintainer comfortable-fit judgment, acceptance date and production outcome pending.

Final review: the diff contains only this CLI slice, its strict inspector extension, tests, feedback progress and approved documentation. Reused identity/name/retirement parsers and existing profile/registration APIs; no server payload or registration behavior changed. No new request or scope expansion. Applicable local entry, source safety and partial/uncertain retry checks pass. The four retained Unicode failures are baseline failures; full-suite success is unclaimed.

Next unaided action: from this worktree, run the documented preview with an explicit destination and a new plan filename, review fingerprints, then publish selected keys only when authorized for that destination. For an uncertain outcome, resolve access/connectivity and rerun the same selection; for changed source or refused eligibility, resolve it and create a fresh preview. Production trial is a separate authorization and acceptance step. Broader approval propagation/message access and reverse-content import remain out of scope.

*— Codex · 2026-11-22 UTC*
