> **Feature plan:** [Step 1](./docs_publication_policy_step1_solution_assessment.md) · [Step 2](./docs_publication_policy_step2_feature_description.md) · [Step 3](./docs_publication_policy_step3_development_plan.md) · [Step 4](./docs_publication_policy_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/docs_publication_policy`, standing enrollment P2; [host ledger](../effort_estimation_trial.md#enrollment-index-after-p2).

## Problem

`./v3 import-instance` rebuilds the entire local documentation library during static publication even when its rendering inputs are unchanged. The full library must remain available on dynamic and static hosts without routinely repeating that work.

## User Stories

- As an instance operator, I want imports to reuse compatible documentation so that publication spends its effort on changed content.
- As a reader, I want the complete documentation library to remain current and usable so that imports do not break browsing, search, downloads or related links.

## Core Requirements

- Reuse only a verified, complete documentation publication compatible with current documentation and all relevant rendering inputs. During reuse, refresh the embedded repository-version marker to the current destination version without rerendering documentation. This marker is the sole permitted changing-input exception: all other output-affecting changes, including presentation/profile/configuration and relevant record-derived content, must invalidate reuse. Unrelated imported-record commits must not require documentation rendering.
- Automatically build the full library when no compatible publication exists, including first publication, older releases without reuse evidence, changed/added/deleted documents, missing or damaged output, and uncertain compatibility. Reuse is an optimization; operators need no new flag or manual repair step.
- Preserve all eligible documents, plans/history, root files and supporting assets; existing URLs, source boundaries, safe downloads, search/filter/pagination, related links and missing-document recovery remain usable across dynamic/static delivery, including existing no-JavaScript behavior.
- Preserve complete-release activation, applicable asset availability, private-instance access gates and interrupted-import recovery. A failed documentation rebuild must not activate a partial release; retain the previous complete release and existing retry guidance.
- Apply one documentation publication policy to full builds and shared refreshes; retain content-dependent forum publication. CLI progress must distinguish reuse from rendering without claiming rendered work that was skipped.

## Delivery Scope and Completion Boundary

Work type: **application change**, internal publication maintenance verified through the normal operator and reader flows. Includes focused regression coverage, the import/publication runbook, cycle plans/debug log and `effort_estimation_trial.md`; record any affected UI-copy verification in `ui_copy_audit_checklist.md`. No documentation exclusions, new delivery mode, navigation destination or database schema change is planned.

The reported environment is the repository's `./v3 import-instance` workflow. From a destination with a compatible release, an ordinary content import completes, publishes the imported forum records and reuses documentation. Readers then open Tools → Platform Docs on dynamic and static hosts and browse/search/download successfully. If fallback rendering fails, the operator retains the previous complete release and can use the existing `./v3 import-instance --resume` flow with the same destination options, verify successful completion and reopen Docs. Release requires CLI, recovery and browser evidence through these paths; dynamic-only evidence is insufficient.

## Risks

- **Stale output:** an incomplete dependency boundary can reuse stale presentation or record-derived content. Before dependent work, inspect actual docs output dependencies and vary representative inputs; invalidate conservatively. If normal imports necessarily change docs output, resolve that conflict before committing to reuse behavior.
- **Incomplete release or unsafe reuse:** missing files, broken referenced assets or a changed access boundary could break browsing or expose content. Validate cold/legacy/damaged releases and private destinations early; rebuild on failed validation and preserve publication/access guards.
- **Little practical benefit:** repeated inventory scans, copying and validation can dominate even after rendering stops. Measure cold and unchanged repeat publication against the same representative full inventory early; require zero docs rendering on compatible repeats and report elapsed work honestly, without a hardware-independent speed promise.

## Shared Component Inventory

Reuse/extend the canonical `PlatformDocsCatalog` inventory and eligibility policy, `StaticArtifactBuilder` documentation/assets publication, and `StaticArtifactReleasePublisher` full-build/shared-refresh paths. Integrate with `ImportedContentPublisher` and `ContentImportRunner` progress/recovery. Preserve `FrontController` static docs serving and `PlatformDocsController` dynamic routes, shared Markdown rendering, docs templates/search assets, and Tools links. Audit shared page chrome, profile/theme/configuration and referenced assets as compatibility inputs. No replacement reader/search UI is needed; any reusable publication helper must serve both build paths.

## Simple User Flow

1. Run the usual import command; publication validates existing documentation and reuses it or rebuilds automatically.
2. Open imported forum content and Platform Docs; browse/search, open a historical document, follow a related link and download a source file.
3. If publication fails, follow existing recovery guidance and resume; confirm completion and working documentation.

## Success Criteria

Compatible repeat imports render **zero documentation pages or docs browse/search shells** while publishing new forum content; when version notifications are enabled, each reused page’s version marker must agree with the destination’s current `/api/version`, preserving normal reload-notification behavior. Cold/legacy, changed-input and damaged-output cases rebuild a complete library; deleted docs disappear from results and stale URLs recover safely. Dynamic/static inventory and representative reader journeys agree; failure/resume, private-instance and shared-refresh checks pass. Record representative cold/repeat timings and verification limitations.

## Approval and Study Snapshot

User approved Step 1 (Option B); Step 2 approval pending. Earlier snapshots remain in [Step 1](./docs_publication_policy_step1_solution_assessment.md#study-snapshots). UTC 2026-10-21T20:24:13Z; cycle `v3/docs_publication_policy`; scope v1 clarified above; start `be2be9d4ad4c172db33147239ecca6d2ca860d4a`; R1/P2; Codex, single exposed rater. **0.65 projected total reaffirmed**, including all investigation, planning, implementation, recovery/performance/browser verification and documentation. No scope expansion; the dependency audit remains the principal uncertainty. R1 hashes reverified from the Step 1 sources; prior scores and source inspection visible. Pre-inspection intake remains missing; no independent rating, measured actual effort, implementation approval or accepted outcome is claimed.

## Approval Record

2026-10-21T20:42:55Z — User replied “Approved Step 2”; requirements advance unchanged to Step 3. Earlier handoff state remains historical.

## Revision Request — Scope v2, Pending Renewed Step 2 Approval

UTC 2026-10-21T20:55:35Z. Stage 1 found that `Application::appVersion()` uses the canonical-record repository HEAD; `templates/layout.php` embeds it in every docs page when version notifications are enabled (the default), and `version_check.js` compares it with `/api/version`. A normal import changes this output without changing documentation. Thus v1's unconditional invalidation rule conflicts with its ordinary-import reuse requirement.

Preserved v1 requirement: “Reuse only a verified, complete documentation publication compatible with current documentation and all relevant rendering inputs. An imported-record commit alone must not invalidate reuse; output-affecting changes, including presentation/profile/configuration and any relevant record-derived content, must invalidate it.”

Proposed v2: permit only the embedded repository-version marker to be refreshed during validated copying. Keep the same version endpoint, reload behavior, complete docs and all other invalidation rules. When notifications are enabled, malformed/missing/unexpected markers cannot silently pass reuse validation; rebuild safely. When disabled, preserve the absence of version markers. This does not change global app-version semantics or remove notifications. The updated Core Requirements and Success Criteria above express the proposed outcome; v1's approval is preserved above but does not approve v2.

Study discovery/Step 2 revision snapshot: `railspike-effort / v3/docs_publication_policy`, scope v2, R1/P2, original start `be2be9d4ad4c172db33147239ecca6d2ca860d4a`; Codex single exposed rater, UTC 2026-10-21T20:55:35Z. **0.70 projected total**, revised from v1's 0.65 because newly discovered changing version metadata adds guarded transfer and reload-behavior verification. Includes all prior work and remaining planning/implementation/acceptance, not remaining effort. Prior projections and missing intake remain preserved. R1 sources unchanged and hashes verified at Step 4 entry; no independent rating or measured actual claimed. Revised Step 2 approval pending; revise and reapprove Step 3 afterward before dependent implementation.

## Resumption on current main — 2026-10-23

Request: resume the preserved publication-policy work after moving this clone to `origin/main`. Restored this cycle's reviewed planning files and pending v2 revision onto `main` at `1a358bc9`; retained both upstream study records and this cycle's stashed checkpoints. Original branch `feat/docs-publication-policy`, Stage 1 commit `a6110024`, and `stash@{0}` remain recovery sources. No old branch history was merged; all restored planning changes remain uncommitted.

Read-only inspection confirms that `Application::appVersion()` still uses destination repository HEAD, layout still emits the version marker, and `StaticArtifactBuilder` still renders the documentation inventory. The current renderer also emits `v3-generated-at`; reused pages retain their original generation timestamp under the existing sole-marker exception. Before implementation, the renewed Step 3 must include revalidation of the dependency inventory and baseline against current main; historical timings are not current-baseline results. No new third-party components are proposed or introduced.

Study resumption / Step 2 handoff: UTC 2026-10-23T07:49:12Z, `railspike-effort / v3/docs_publication_policy`, scope v2 / R1 / P2, original start unchanged. **0.70 projected total reaffirmed**, including prior work and current-baseline revalidation, not remaining effort. Codex single exposed rater; no independent assessment or measured actual. Calibration report and diff-review hashes match R1; the working feedback file differs, so exact frozen feedback was recovered read-only from `4f6cfe2b436fae8d939b1349fe724a3879ee0a0e` and its R1 hash verified. Prior scores, missing intake and incomplete outcome are preserved.

Approval status: v2 still awaits **Approved Step 2**, followed by revised Step 3 and renewed approval. The resume request restores work at this gate; it does not supply the specifically required scope approval. Stages 2–8 remain pending.

## Renewed Step 2 Approval — Scope v2

UTC 2026-10-23T08:05:54Z: user replied **“Approved Step 2”** to the v2 revision and resumption handoff. Version-marker refresh, all other invalidation rules, current-main revalidation and the zero-dependency proposal are approved requirements. This supersedes the pending status above; historical approvals/snapshots remain unchanged. Revised Step 3 is prepared for separate approval; no implementation is authorized yet.

Correction to the resumption note: `v3-generated-at` is emitted by `renderHighTrafficRoute()`, not by ordinary static docs rendering. Stage 1 will verify that boundary; no general generation-timestamp rewrite is proposed.
