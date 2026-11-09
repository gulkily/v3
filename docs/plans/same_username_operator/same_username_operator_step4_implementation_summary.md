> **Feature plan:** [Step 1](./same_username_operator_step1_solution_assessment.md) · [Step 2](./same_username_operator_step2_feature_description.md) · [Step 3](./same_username_operator_step3_development_plan.md) · [Step 4](./same_username_operator_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/same_username_operator`; [host ledger](../effort_estimation_trial.md#cycle-v3same_username_operator). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.55** (Step 3 projection, reaffirmed at entry)

Branch: `feat/same_username_operator`. Planning commit: `1ccbadc`.

## Step 4 entry

2026-11-09T05:26:30Z, `railspike-effort` / `v3/same_username_operator`, Step 4 entry. Step 3 approved by the maintainer on 2026-11-09 ("Approve step 3"). Scope v2 unchanged. Projected total **0.55** reaffirmed. Stage originals copied from Step 3: 0.08, 0.12, 0.12, 0.06, 0.05, 0.07, non-additive. Same R1/P2, single exposed Claude rater. Copying is not a new rating.

Starting revision: `f60d639` (`main`, which includes PR #88).

## Stage 1 - Operator grant record

**Original estimate: 0.08**

**Retrospective stage-work assessment: 0.07**

- Changes:
  - New spec `docs/specs/operator_grant_record_v1.md`: headers `Record-ID`, `Created-At`, `Operator-Identity-ID`, `Target-Identity-ID`, `Target-Username`; no body; signed `.asc` beside it; evidence only, excluded from import.
  - New `OperatorGrantRecord` and `OperatorGrantRecordParser`, copied from the flag change pair. They also reject a grant whose target is its own operator.
  - `CanonicalPathResolver::operatorGrant()`, `SourcePathValidator` (record path; the `.asc` check reuses it), `CanonicalRecordRepository::loadOperatorGrant()`.
  - Approval seeds take an optional `Operator-Grant-ID` header, parsed into `ApprovalSeedRecord::$operatorGrantId`. This replaces the plan's idea of reading the grant ID from the free-text seed reason. `user_approval_seed_record_v1.md` documents the header and no longer says seeds "only bootstrap" trust.
- Verification:
  - Passed: `./v3 test CanonicalRecordParsersTest CommitMapTest RootApprovalPageTest ApprovalSeedCommandTest`, 84 run, 84 passed. The new test covers a valid grant, six rejections, the path checks, a seed with and without `Operator-Grant-ID`, and a bad `Operator-Grant-ID`.
  - Inapplicable: UI and deployment; this stage adds no route or page.
- Notes:
  - Reuse: `FeatureFlagChangeRecord`, its parser and its spec as the model; `GenericTextRecordParser` for the `Key: value` format.
- Estimate details (extended mode only): original scope as in Step 3; uncertainty low; no revision. The seed header added a few lines and replaced reason parsing planned for Stage 4.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Stage 2 - Prepare and finalize a grant

**Original estimate: 0.12**

**Retrospective stage-work assessment: 0.11**

- Changes:
  - `LocalWriteService::prepareOperatorGrant(input, operatorIdentityId)` and `finalizePreparedOperatorGrant(input, operatorIdentityId)`, copied from the flag change pair.
  - Shared `assertOperatorGrantAllowed()` runs in both, so finalize re-checks inside the write lock. The operator must be approved, unretired and seeded; the target must be approved, unretired, not the operator and not seeded. It returns the target's `username_token`, which goes into `Target-Username`.
  - Finalize verifies the detached signature against the operator's key, checks the read model is current, and commits the grant, its `.asc` and the seed in one commit. The seed has `Seed-Reason: Granted by <operator>` and `Operator-Grant-ID`.
  - After the commit, `applyApprovalSeedWrite` refreshes the read model, as `./v3 approval seed` does. On refresh failure, the stale marker is set and the error names the commit and `./v3 rebuild`.
  - `buildOperatorGrantRecord()` builds and self-parses the record.
- Verification:
  - Passed: `WriteApiSmokeTest::testOperatorGrantWritesSignedRecordAndSeedInOneCommit`. Rejections: unapproved target, non-operator actor, self-target, and a grant signed by the wrong key (commit and files unchanged). Success: HEAD holds exactly the record, `.asc` and seed; record headers; seed `Operator-Grant-ID`; target is an active operator; a second grant is refused. A granted key grants in turn. A full rebuild gives the same profiles rows as the incremental refresh. Retiring a granted key ends its operator status and its grant attempt fails, with no new record.
  - Passed: mutation check. Removing the "already an operator" check made the test fail.
  - Passed: `./v3 test --smoke` plus the test above, `CanonicalRecordParsersTest`, `ApprovalSeedCommandTest`, `RootApprovalPageTest`, `ApprovalPolicyTest`: 463 run, 462 passed. The failure is the baseline `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, failing since before this cycle. Full suite not run.
- Notes:
  - Reuse: `prepareFeatureFlagChange`/`finalizePreparedFeatureFlagChange` structure, `storePreparedPost`, `signatureVerifier`, `commitCanonicalWrite`, `assertApprovalSeedReadModelReady`, `incrementalReadModelUpdater()->applyApprovalSeedWrite`, `invalidateApprovalSeed`, `KeyRetirement` through `isApprovedIdentity`.
  - Tests live in `WriteApiSmokeTest` beside the flag change tests, to reuse its signing helpers.
- Estimate details (extended mode only): original scope as in Step 3; no revision. The retired case needed the key retirement API, which already existed.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Stage 3 - The "Make root" button

**Original estimate: 0.12**

**Retrospective stage-work assessment: 0.13**

- Changes:
  - Routes `/api/prepare_operator_grant` and `/api/finalize_operator_grant` in `Application.php`, in the known API path list, and in `ApiRouteCatalog` as operator routes.
  - `ToolsPageController::operatorGrantApi()`: POST only; the viewer must pass `RouteServices::profileIsActiveOperator` (PR #88); then it calls the Stage 2 writer methods, which re-check everything.
  - `ApprovalPolicy::operatorGrantActors(root, profile)`: active operator fingerprints, or none when the profile is not an approved, unretired, non-operator key.
  - New partial `templates/partials/operator_grant.php`, included in `profile_access.php` after key retirement. Like `key_retirement.php`, it decides visibility before paint from the actors list and the browser's stored key.
  - New `public/assets/operator_grant.js`, modeled on `key_retirement.js`: "Make root…" prepares, the confirmation reads "Make <username> (<8 hex>…) a root operator? This key will be able to do everything root can.", then "Make root" signs with `ForumBrowserSigning.signCanonicalRecord` and finalizes. A failed finalize keeps the signed request for a retry and says to reload to check the outcome. Success reloads.
- Verification:
  - Passed: `WriteApiSmokeTest::testOperatorGrantRoutesAndAccessPageButton`. GET is refused; no viewer and a member viewer get 403. The operator sees the partial, its fingerprint in the actors list and the hashed `operator_grant` script on the target's page, and no partial on its own page. A grant through both routes succeeds, and the partial is gone from the target's page afterwards.
  - Passed: new `tests/browser/operator_grant.mjs`, desktop and 390px. It renders the real partial with PHP, checks hidden for a non-operator key and no markup with no actors, shown before paint for an operator, the confirmation text, cancel, a failed finalize with retry resending the same signed request, one signature in total, and a reload. Run as `node --import <hook> tests/browser/operator_grant.mjs`; the hook maps `playwright-core` to `/opt/node-tools`, because this checkout has no `node_modules`.
  - Passed: `ApiRouteCatalogTest`; `./v3 test --smoke` plus the changed tests: 443 run, 442 passed, the baseline `BoardListTest` failure only. Full suite not run.
  - Moved to Stage 6: the real-key headless run on `./v3 start`. It is the same scenario as Stage 6's two-key replay, so it runs once there.
- Notes:
  - Reuse: `key_retirement.php` and `key_retirement.js` patterns, the flag change API handler shape, `ForumBrowserSigning`, `identityScripts`.
  - Asset URLs carry a content hash (`/assets/operator_grant.<hash>.js`), so the page test matches the pattern.
- Estimate details (extended mode only): original scope as in Step 3; no revision. Slightly over: the charset bug in the test fixture and the asset hash cost two extra runs.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Stage 4 - Show who granted root

**Original estimate: 0.06**

**Retrospective stage-work assessment: 0.06**

- Changes:
  - `RootApprovalPageController`: when the seed has `Operator-Grant-ID`, it loads that grant. It uses the grant only if the file exists, parses, and targets the same key.
  - `root_approval.php`: for a granted seed, "Granted by: <operator username, linked> · <time>" replaces "Recorded reason", which would repeat the grantor. "Signed operator grant" links the grant beside "Raw seed record". The "Authorizer: not recorded" note shows only for seeds without a grant.
  - `ActivityService`: the file role "operator grant record", and the grant's signer resolves to its operator, as for flag changes.
- Verification:
  - Passed: `RootApprovalPageTest::testGrantedSeedNamesItsGrantorAndLinksTheSignedGrant`: missing grant falls back to the reason; a matching grant shows the grantor, the link and no "Authorizer" note; a grant naming another key falls back.
  - Passed: `WriteApiSmokeTest::testOperatorGrantRoutesAndAccessPageButton` now checks the grant commit's manifest: "operator grant record", a detached signature signed by the operator, and the "approval seed record".
  - Passed: `./v3 test --smoke` plus `RootApprovalPageTest`, `ActivityItemLinkTest` and both grant tests: 455 run, 454 passed, the baseline `BoardListTest` failure only. Full suite not run.
  - Inapplicable: `ActivityItemLinkTest` needs no new case. Grants create no activity item kind; they appear as commit files.
- Notes:
  - Reuse: `CanonicalRecordRepository::loadOperatorGrant`, `sourcePathHref`, `ProfileRepository::byIdentityId`, the template's `$timestamp` helper.
  - The source commit route answers in plain text; the roles show in `activityCommitManifest`, which the activity view uses.
- Estimate details (extended mode only): original scope as in Step 3, minus seed-reason parsing, which Stage 1's header replaced; no revision.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
