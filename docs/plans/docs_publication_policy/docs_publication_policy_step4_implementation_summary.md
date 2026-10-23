> **Feature plan:** [Step 1](./docs_publication_policy_step1_solution_assessment.md) · [Step 2](./docs_publication_policy_step2_feature_description.md) · [Step 3](./docs_publication_policy_step3_development_plan.md) · [Step 4](./docs_publication_policy_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/docs_publication_policy`, standing enrollment P2; [host ledger](../effort_estimation_trial.md#enrollment-index-after-p2).

## Execution State

Step 3 approved by the user. Branch `feat/docs-publication-policy`; planning commit `cf5e744c`. Stage 1 audit complete with a contract conflict; Stages 2–8 not started. Await renewed Step 2 approval, then revised Step 3 approval. No changed-scope work authorized.

## Step 4 Entry Study Snapshot

UTC 2026-10-21T20:53:58Z; study `railspike-effort`, cycle `v3/docs_publication_policy`, scope v1, R1/P2; original start `be2be9d4ad4c172db33147239ecca6d2ca860d4a`. Codex, single exposed rater. Step 3 **0.65 projected total reaffirmed** before implementation, including already performed investigation/planning and required verification/documentation. Missing intake and earlier scores remain preserved; no measured actual or independent rating. Debug mode inherited enabled. Ledger bookkeeping accumulates for closeout.

## Stage 1 - Dependency audit and baseline

- Changes: Audited Catalog/controller, TemplateRenderer/layout, static builder/publisher, feature flags, version endpoint and browser version checker. No application source changes.
- Verification: Dynamic README routes returned HTTP 200 on default Zenmemes and QDB profiles; their output hashes differ. Default version notifications include `app-version`; disabling `FORUM_APP_VERSION_NOTIFICATION` removes it. Frozen application snapshot at planning commit `cf5e744c`: complete 1,375-document cold build passed in 82.894 seconds. Separate compact four-document actual CLI import and duplicate replay passed (1.980 / 1.782 seconds); a new forum record was installed and unchanged README output differed only in its `app-version` marker. Both compact imports still rendered nine docs routes/shells under existing behavior. These timings are separate experiments, not an optimization comparison.
- Notes: `Application::appVersion()` returns `ReadModelMetadata::repositoryHead($repositoryRoot)`; layout embeds it by default, and the browser compares it to `/api/version`. A content-only import changes a rendering input. V1's unconditional input-invalidation rule cannot both preserve this behavior and allow the required import reuse. Audit identified the conflict as required; dependent implementation awaits a reviewed contract revision. No performance improvement is claimed.

## Discovery and Proposed Scope Revision

UTC 2026-10-21T20:59:04Z. Proposed scope v2 allows refreshing only the embedded version marker during validated copying; all other output-affecting inputs still invalidate reuse. This keeps the existing global version endpoint and notification behavior. Request disposition: changed scope under Step 2's explicit invalidation rule; revised Step 2 is drafted, not approved; revised Step 3 must follow renewed Step 2 approval. Original approvals and v1 artifacts/scores remain historical.

Study discovery snapshot: `railspike-effort / v3/docs_publication_policy`; scope v2 proposal, R1/P2, original start `be2be9d4ad4c172db33147239ecca6d2ca860d4a`; Codex single exposed rater. **0.70 projected total**, revised from 0.65 due to newly understood changing version metadata and guarded transfer/reload checks. Includes already performed work and future verification, not remaining work. R1 hashes reverified at entry. Intake remains missing; measured active work unknown; implementation incomplete and maintainer acceptance pending. Canonical proposed Step 2 checkpoint: [revision](./docs_publication_policy_step2_feature_description.md#revision-request--scope-v2-pending-renewed-step-2-approval).

### Stage 1 Evidence and Limitations

- Full baseline: isolated source snapshot from planning commit `cf5e744c`, fixture record checkout, complete static build and offline snapshot; 1,375 eligible documents, 82.894 seconds. Canonical document inputs remained frozen throughout. No repeat/full-inventory optimization benchmark yet.
- Content-only proof: separate four-root-document fixture, real `./v3 import-instance` against a local HTTP archive source and duplicate repeat. Cold fixture build 1.452 seconds; import 1.980 seconds; duplicate 1.782 seconds. New forum post installed; README source hash `95398c6c4126bf5bb7a480a0596abea03983f1199805052b250ec91267beb4c5` unchanged. Version changed from `8ef68771c665489d7233d4e5d1359255eeb77f79` to `8c80b64315587b5fc85ffe3e62eef4714705eb0d`; normalizing only that marker made the two rendered pages byte-identical.
- Output ownership identified: `docs.html`, `docs/index.html`, per-document HTML, missing/search shells, browse variants, `_search.json`, inert download copies and referenced fingerprinted assets. Shared layout depends on templates/code, profile/runtime config, effective feature flags, theme/runtime assets and destination HEAD for the version marker; unrelated forum records are not otherwise rendered into the sampled README page.
- Additional profile/flag probes: default Zenmemes, version notification disabled and QDB README responses all HTTP 200; profile changes altered HTML, disabled notifications omitted the marker. Source audit is not a proof that every possible dependency is covered; Stage 2's signature tests and conservative evidence remain required.
- Harness corrections: the first full attempt correctly failed the existing source-change guard when planning edits occurred during rendering. An intermediate frozen run was interrupted to correct the root-document route to `/docs/_project/README.md`; no timing from either attempt is presented as a completed baseline. A successful full-baseline run then reduced its isolated inventory for the first replay, so that replay was not treated as a content-only output comparison; the separate unchanged compact-fixture replay above provides that proof.
- Scripts/reports are local audit scratch files (`/tmp/docs-publication-audit.php`, `/tmp/docs-publication-full-baseline.json`, `/tmp/docs-publication-audit.json`, `/tmp/docs-dependency-probe.php`); canonical results are recorded here. Test workspaces were temporary. Production data, application source and tests were not modified.
- Deferred: no reuse helper, publication wiring, recovery injection, performance improvement or browser acceptance has been delivered. Stages 2–8 await the revised approvals. No complete-cycle or accepted-study outcome.

## Stage 1 Commit Record

Planning commit: `cf5e744c`. Stage 1 discovery/evidence commit is the commit introducing this summary and its matching debug entry; its exact hash will be recorded in the next authorized summary update, without amending history. Revised Step 2 and accumulated ledger bookkeeping remain uncommitted planning work.

## Resumption after clone migration — 2026-10-23

Stage 1 evidence commit: `a6110024`; historical planning commit `cf5e744c`. Restored only this cycle's planning files onto current `main` (`1a358bc9`), resolved the applied stash's Step 2/ledger conflicts without losing upstream entries, and retained the original branch and stash. No historical branch merge or application change. Historical Stage 1 evidence remains historical; revalidate dependencies and baseline on the current source before dependent implementation. Current gate and R1 resumption snapshot: [revised Step 2](./docs_publication_policy_step2_feature_description.md#resumption-on-current-main--2026-10-23). Renewed Step 2 and Step 3 approvals are still pending.

## Approval disposition — 2026-10-23T08:05:54Z

User approved revised Step 2 v2. The [revised Step 3](./docs_publication_policy_step3_development_plan.md#execution-and-study-checkpoint) now includes current-main revalidation and guarded version-marker refresh; renewed Step 3 approval is pending. Historical Stage 1 remains complete, current-main revalidation and Stages 2–8 remain unstarted. No application changes or stage execution in this planning handoff.

## Step 4 v2 entry — 2026-10-23

Renewed Step 3 approved. Fresh branch `feat/docs-publication-reuse` at current-main base `1a358bc9`; revised planning-only commit `6e77d190`. Debug mode inherited enabled. Scope v2 / R1 / P2, same cycle and original start; **0.70 projected total reaffirmed** before implementation, including prior investigation and current-main revalidation. R1 hashes verified at revised Step 3. Codex single exposed rater; missing intake, prior projections and historical Stage 1 remain preserved. No new measured active effort or independent judgment. Stage 1 revalidation now in progress; Stages 2–8 pending.

## Stage 1 - Current-main dependency revalidation and baseline (v2)

- Changes: Revalidated the normal docs/controller/renderer, profile/config/feature flags, catalog/link boundary, referenced assets and full/shared publishers at frozen planning source `6e77d190`. Source documents, directory/link availability, application/templates/public assets, active profile and effective flags form the compatibility inputs. Anonymous publication avoids viewer-derived records; destination HEAD is used only for the approved version marker. High-traffic timestamps are not emitted by ordinary docs. Shared-refresh seeding uses hard links, requiring atomic candidate replacement for marker refresh.
- Verification: `php /tmp/v3-docs-reuse-audit.php` on an isolated immutable source copy: **1,459 documents, 70.090 s cold full publication**, 1,886 docs progress callbacks (document/landing/browse rendering; missing/search shells are additionally rendered without callbacks). Real CLI content import **79.916 s**, imported record present, README differs only in embedded version marker (`990590fc…` to `7064004b…`). Zenmemes/QDB and notification-disabled dynamic probes HTTP 200; profile output differs, disabled mode omits marker. Separate `php /tmp/v3-docs-reuse-probe.php` confirms a stylesheet change changes rendered docs. `php tests/run.php PlatformDocsCatalogTest`: **9/9 passed**.
- Notes: Reports `/tmp/v3-docs-reuse-baseline.json` and `/tmp/v3-docs-reuse-probe.json`; source copy `/tmp/v3-docs-reuse-baseline`. Full inventory differs from historical 1,375 and must be frozen consistently in Stage 7 comparison. Historical timing is not reused as current evidence. All test record/static workspaces isolated and removed by existing fixtures. No application changes, production deployment or optimization claim. Scope v2 **0.70 total reaffirmed**; Stage 2 may proceed after this commit.

## Stage 2 - Compatibility evidence

- Changes: Added canonical `DocsPublication::inputSignature()` over fresh eligible document metadata/content, docs directory/link availability, application/templates/public inputs, PHP version, active profile and all effective feature flags. Destination record HEAD is deliberately excluded; no unrelated post data is used. Added a backwards-compatible fresh catalog read to bypass scoped renderer snapshots for mid-build validation. Anonymous context is explicit; visitor/session contexts cannot establish reuse evidence.
- Verification: `php tests/run.php DocsPublicationTest PlatformDocsCatalogTest`: **11/11 passed**. Tests cover new destination record commits, changed/added/deleted docs, code/template/assets, empty directories, profile/notification changes, and fresh detection inside an existing catalog snapshot.
- Notes: Stage 1 commit `b0ba9080`. Signature is conservative across public/application changes; no secrets or input values are written to publication evidence (only a digest). No schema/dependency changes. Transfer and publication wiring are pending.

## Stage 3 - Validate and transfer complete documentation

- Changes: Added versioned complete-output evidence, canonical required-path reconstruction, file/asset integrity validation, exact marker/endpoint validation, safe independent candidate copies, atomic replacements and candidate cleanup. Enabled notifications refresh only the embedded marker; disabled mode retains absence. Output/evidence are revalidated against current inputs/version, and fresh evidence supports repeated reuse. Compatibility also includes retraction/Fastmod generation tokens to keep copied artifact sidecars within their existing publication contracts.
- Verification: `php tests/run.php DocsPublicationTest`: **7/7 passed**. Includes later reuse after a version-changing commit; source/candidate/next-release isolation including preexisting hard links; missing/corrupt assets/files, legacy evidence, stale output, source symlink/traversal, malformed/duplicate markers and wrong endpoint even with updated integrity hashes; disabled notifications/mode change; partial-copy cleanup and candidate asset symlink rejection; changed input prevents recording. PHP lint and `git diff --check` passed.
- Notes: Stage 2 commit `5e484da8`. Uses original-publication integrity before rewriting, validates assets against their actual source fingerprints, and never mutates active-release links. Reuse failure requests full owned-output rebuild; fallback/activation wiring remains in Stages 4–5. No third-party/schema changes or changed-scope requests.

## Stage 4 - Full publication and real CLI imports

- Changes: Split forum shared rendering from documentation, pass the active release as an optional reuse source on full publication, and preserve existing callers. Full fallback clears docs-owned output, renders the complete library and records evidence against inputs/version captured before rendering. CLI progress reports ten forum shared routes separately from every actually rendered docs route/shell or a reuse count. Added an isolated real-application fixture with a small inventory for normal CLI regression coverage.
- Verification: `php tests/run.php DocsPublicationFlowTest DocsPublicationTest`: **9/9 passed**. Real cold/new-record/duplicate CLI imports verify complete publication, zero docs-render progress on reuse, current forum output, unchanged source release, HTTP static docs and marker agreement with real `/api/version`. Full publisher cases automatically rebuild missing legacy evidence, changed/deleted docs and damaged output with current search inventory. PHP lint/whitespace pass.
- Notes: Stage 3 commit `afea34a6`. Initial CLI observer assertion used PHP's cached `current` realpath after a child process activated a new release; clearing the parent observation cache proved actual new forum output and repeated imports. No production code change was needed for that harness correction. Shared-refresh reuse remains Stage 5.
