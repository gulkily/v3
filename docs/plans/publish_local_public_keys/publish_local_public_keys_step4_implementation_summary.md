> **Feature plan:** [Step 1](./publish_local_public_keys_step1_solution_assessment.md) · [Step 2](./publish_local_public_keys_step2_feature_description.md) · [Step 3](./publish_local_public_keys_step3_development_plan.md) · [Step 4](./publish_local_public_keys_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/publish_local_public_keys; [cycle ledger](../effort_ledger/publish_local_public_keys.md), standing P2 enrollment.
Extended estimates: enabled

**Cycle estimate: 0.65**

Status: implementation in progress; production untouched and acceptance pending.
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

- Status: locally complete.
- Changes: explicit fingerprint selection, exclusive Key: value review files outside canonical source, source/destination binding, all-selection validation before the first write, bounded public-key-only registration and mandatory read-back. Each invocation attempts each missing selection once; reruns skip confirmed registrations and preserve mixed outcomes.
- Verification: smoke plus PublishKeysCommandTest/PublicKeyPublisherTest: 494 run, 490 passed, four known Unicode failures reproduced on untouched baseline. Final focused PublicKeyPublisherTest: 5/5 passed, including source changes in another selected key, source-byte changes, review-file preservation, ambiguous responses, concurrent registration, partial refusal and reruns. Syntax/help/diff checks passed. Full suite not run.
- Estimate details: original 0.22 retained, retrospective 0.25; same v1/A, R1/P2 and exposed rater. Projection 0.65 reaffirmed; not measured labor. Production untouched and acceptance pending.

## Stage 3 - Ordinary-flow acceptance

**Original estimate: 0.15**

- Status: pending; Stage 2 must commit first.

## Stage 4 - Documentation and handoff

**Original estimate: 0.08**

- Status: pending; shared release documentation owner.

*— Codex · 2026-11-22 UTC*
