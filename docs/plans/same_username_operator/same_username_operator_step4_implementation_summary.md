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

## Stage 5 - Imports bring in no operators

**Original estimate: 0.05**

**Retrospective stage-work assessment: 0.03**

- Changes:
  - `ContentImportPlanner.php`: `operator-grants` joins the authority families excluded as "Instance authority, settings, or private data". A grant's `.asc` follows its record, so it is excluded too.
  - Not changed, unlike the plan: `ArchiveRecordCatalog.php`. The planner excludes authority families before it consults the catalog, and `feature-flag-changes` is likewise absent from the catalog. Adding grants there would change nothing.
- Verification:
  - Passed: `ContentImportPlannerTest::testOperatorGrantsAndTheirSeedsAreExcludedAsAuthority`: a granted seed, a grant and its signature are each `excluded` with the authority reason, while ordinary posts still import. Without the planner change the grant plans as `unsupported`; checked by stashing the change and rerunning.
  - Passed: `./v3 test --smoke ContentImportPlannerTest`, with only the baseline `BoardListTest` failure. Full suite not run.
  - Not run: a full `import-instance` run. The runner writes only entries the plan marks `import`, so a plan-level test covers "brings in no operators". Recorded as a limit, not a pass.
- Notes:
  - Reuse: the existing authority exclusion in `ContentImportPlanner::describe`.
- Estimate details (extended mode only): original scope as in Step 3; under, because the catalog needed no change.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Stage 6 - Documentation and replay

**Original estimate: 0.07**

**Retrospective stage-work assessment: 0.09**

- Changes:
  - New concept `docs/concepts/operator_grant.md`: "Signed operator grant" and "Seed as the only authority".
  - `docs/reference/v3_cli.md`: under `./v3 approve`, the **Make root** button, the grant spec, and that imported instances still need `./v3 approve` for the first operator.
- Verification:
  - Passed: known-user replay on `./v3 start` against a copy of `state/local_repository` (`FORUM_REPOSITORY_ROOT`, `FORUM_DATABASE_PATH`, `FORUM_STATIC_HTML_ROOT`), with real ed25519 keys made by the site's OpenPGP.js bundle and real browser signing. Key A was seeded with `./v3 approve`, and A approved key B with `./v3 approval approve`. The site names linked keys "guest", so both keys shared one username, matching the maintainer's case. Before: B's Feature flags page was read-only, with 0 enabled controls; A had 168. On B's User Access page at 390px, A saw "Make root…"; the confirmation read "Make guest (8ccbe9d6…) a root operator? This key will be able to do everything root can."; "Make root" reloaded the page without the button. After: B had 168 enabled controls. The commit "Grant operator … by …" held exactly the seed, the grant and its `.asc`. `./v3 rebuild` kept B's 168 enabled controls. B saw no button on A's page. The root approval page showed "Granted by: guest · Nov 9, 2026 at 05:50 UTC" and "Signed operator grant". No page errors. Screenshots inspected.
  - Passed: themes. On a third approved key's page, the button showed in Word97, Midnight, Chicago and Gloss at 390px, matching the Retire button. Screenshots inspected.
  - Passed: final `./v3 test --smoke` plus `CanonicalRecordParsersTest`, both grant tests, `RootApprovalPageTest`, `ContentImportPlannerTest`, `ApiRouteCatalogTest`, `ApprovalPolicyTest`, `ApprovalSeedCommandTest`, `UserAccessTest`, `VisitorStatisticsPageTest`: 481 run, 480 passed. The one failure is the baseline `BoardListTest::testPinnedThreadsComeFirstAndLatestThreadsArePaged`, failing before this cycle. `tests/browser/operator_grant.mjs` passed again. `git diff --check` clean. Full suite not run.
  - Pending: production. The maintainer deploys, then grants `openpgp-1fc2edb074108568ad28fec8748e323e033d32b4` on zenmemes.com from his seeded key and checks its Feature flags switches on his phone.
- Notes:
  - Final diff review: 34 files. No duplicated helpers. Each new piece follows an existing pattern: the flag change record set, the key retirement partial and script, the seed refresh. No debug output or scratch paths in committed files.
  - The replay keys and data stayed in the scratch area; nothing from them is in the repository.
- Estimate details (extended mode only): original scope as in Step 3, plus the Stage 3 real-key run moved here and a theme check; gpg-agent could not start here, so the keys came from OpenPGP.js.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*

## Closeout

2026-11-09T05:53:29Z, `railspike-effort` / `v3/same_username_operator`, Step 4 closeout.

- Delivered scope: v2 (Option E) as approved: a signed "Make root" button on User Access key pages; grant and seed in one commit; grantor shown on the root approval page; grants excluded from import; spec, concept and CLI docs. No feature flag.
- Revisions: starting `f60d639`; planning `1ccbadc`; stages `88b9e20`, `382e4ce`, `de3e02c`, `939b5f5`, `3f44c86`, and this Stage 6 commit. Six stages planned, six completed; no splits.
- Deviations from Step 3, all recorded in their stages: `ArchiveRecordCatalog.php` unchanged (not needed); seeds name their grant in a new `Operator-Grant-ID` header instead of in the reason text; the real-key browser run moved from Stage 3 to Stage 6.
- Corrective work: test fixture charset, hashed asset name, commit manifest API. No new requests during Step 4.
- Projection: **0.55** at entry, no revision.
- **Retrospective scope assessment: 0.50**, single exposed Claude rater, not independent. Stage retrospectives 0.07/0.11/0.13/0.06/0.03/0.09, non-additive.
- Verification: as recorded per stage. Production deploy check pending. Full suite not run.
- Maintainer comfortable-fit judgment: pending. Study acceptance: pending. No measured labor.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
