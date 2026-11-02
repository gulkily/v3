> **Feature plan:** [Step 1](./deployment_config_step1_solution_assessment.md) · [Step 2](./deployment_config_step2_feature_description.md) · [Step 3](./deployment_config_step3_development_plan.md) · [Step 4](./deployment_config_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/deployment_config; [standing enrollment P2](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
Status: Step 2 approved; Step 3 proposed; implementation not authorized
**Cycle estimate: 0.85**

# Deployment Config Step 3 Development Plan

Concepts touched: *deployment configuration* (created in Stage 6 as `docs/concepts/deployment_configuration.md`); private config (changed: gains these keys and a per-checkout location; described in the same entry).

## Completion Contract

- Normal entry: the operator edits the private file by hand, with `./v3 private-config` or with the TUI, then uses the site, `./v3` commands and cron as usual.
- Outcome: the web app (normal and high-traffic cached pages), all CLI scripts, the task-queue cron worker and child processes resolve the site profile and the six instance settings through one lookup: environment, then private file, then today's default. With the private file alone set, all of them agree, and that survives `git reset --hard`.
- Recovery: status shows each value's source and the private file in use; an unknown profile or unreadable file fails with a fix-it message; environment variables still override; removing the keys restores today's behavior.
- External: no production change. The runbook has an operator checklist for QDB; QDB acceptance stays pending until it is run on that host.
- Release: focused tests per stage; the Apache scenario with the profile only in the private file; full `php tests/run.php` with no failures beyond a baseline recorded at Step 4 entry.

## Key Risks

- **High risk:** a missed direct read (about 35 files read these keys from the environment today). Impact: CLI or cron disagrees with the web app. Validation: Stage 4 adds a test that fails on any direct read outside the lookup. Mitigation: convert all of them in Stages 3–4.
- **High risk:** behavior change for existing deployments. Impact: wrong data paths after upgrade. Validation: Stage 2 tests show identical results with no private-file keys; Stage 2 checks the template and known private files for the new keys. Mitigation: the new keys are not in the generated template by default.
- Lookup before project root is known (`SiteProfileRegistry::active()` takes no root). Impact: an inconsistent private file location. Mitigation: the lookup defaults to the checkout containing the code; callers with an explicit root pass it.
- Apache harness has a known failing import step (recorded in the QDB diagnosis). Impact: the end-to-end check may stop early. Validation: run the unmodified harness at Step 4 entry. Mitigation: if it still fails there, run the new scenario on its own and record the limitation.

## Stage 1

**Estimate: 0.10**

- Goal: one private file location rule.
- Dependencies: none.
- Expected changes: `PrivateConfig::path(string $projectRoot): string` (`FORUM_SECRETS_PATH`, else `../forum-private/<checkout folder>/secrets.php` if it exists, else `../forum-private/secrets.php`) used by `PrivateConfig::load()`, `write_private_config.php` and `terminal_llm_editor.php`.
- Verification approach: unit tests with temporary checkouts for all three cases, including two sibling checkouts; existing private-config and TUI editor tests pass.
- Estimate details: driver: three callers and their tests; uncertainty: low.
- Risks or open questions:
  - Impact: the editor and the app pick different files.
  - Early warning / validation: test that all three resolve the same path.
  - Mitigation: one function.
- Canonical components/API contracts touched: `PrivateConfig`, `./v3 private-config`, TUI editor.

## Stage 2

**Estimate: 0.15**

- Goal: the deployment settings lookup.
- Dependencies: Stage 1.
- Expected changes: `DeploymentSettings::value(string $key, ?string $projectRoot = null): ?string` and `::source(...)` for `FORUM_SITE_ID`, `FORUM_REPOSITORY_ROOT`, `FORUM_DATABASE_PATH`, `FORUM_STATIC_HTML_ROOT`, `FORUM_PUBLIC_ARTIFACT_ROOT` and `FORUM_IMAGE_ROOT`; schema entries kept out of the generated template; `SiteProfileRegistry::active()`, `LocalRepositoryBootstrap` defaults and `PresentationPathResolver` use it; an unknown profile set in the private file is an error, not a fallback.
- Verification approach: precedence tests per key (environment > file > default); no-file results identical to today's; unknown-profile and unreadable-file errors; template unchanged.
- Estimate details: driver: precedence and error semantics; uncertainty: how the current silent fallback for an unknown environment value is kept for compatibility.
- Risks or open questions:
  - Impact: existing deployments change behavior.
  - Early warning / validation: the no-file parity tests.
  - Mitigation: defaults untouched; only explicit file values are new.
- Canonical components/API contracts touched: `PrivateConfigSchema`, `SiteProfileRegistry`, `LocalRepositoryBootstrap`, `PresentationPathResolver`.

## Stage 3

**Estimate: 0.15**

- Goal: the web app and shared classes use the lookup.
- Dependencies: Stage 2.
- Expected changes: `public/index.php`, `Application`, `RouteServices`, `HighTrafficSafety`, `RetractionSweepFactory`, `FastmodTaggingPolicy`, `LocalWriteService` and `ImageStore` read through `DeploymentSettings`; the high-traffic worker keeps receiving the resolved profile.
- Verification approach: Apache scenario in `tests/high_traffic_apache_test.py` with `FORUM_SITE_ID` only in the private file and an unmodified `.htaccess`: normal, cold and warm cached homepages are QDB; existing high-traffic and smoke suites pass.
- Estimate details: driver: Apache scenario; uncertainty: the harness's known import failure.
- Risks or open questions:
  - Impact: cached pages render the wrong profile again.
  - Early warning / validation: cold/warm Apache assertions.
  - Mitigation: keep the explicit profile argument to the worker.
- Canonical components/API contracts touched: front controller, high-traffic cache and worker, image store.

## Stage 4

**Estimate: 0.15**

- Goal: CLI scripts and cron use the lookup, enforced by a test.
- Dependencies: Stage 3.
- Expected changes: the 27 scripts in `scripts/` that read these keys (status, task queue, rebuild, import, docs, fast score and others) use `DeploymentSettings`; explicit `--repository-root` and similar options keep top precedence; a test fails on direct environment reads of these keys outside `DeploymentSettings`.
- Verification approach: the guard test; a CLI test where the private file alone selects profile and repository for `./v3 status`, `rebuild` and `task-queue run`; existing CLI suites pass.
- Estimate details: driver: mechanical breadth; uncertainty: scripts that compute defaults differently today.
- Risks or open questions:
  - Impact: a script silently changes its default.
  - Early warning / validation: compare each script's no-file default before and after.
  - Mitigation: keep each script's existing default as the lookup default where it differs, and note it.
- Canonical components/API contracts touched: `./v3` CLI, task-queue cron worker.

## Stage 5

**Estimate: 0.10**

- Goal: status shows where settings come from.
- Dependencies: Stage 4.
- Expected changes: `./v3 status` lists the private file in use and each setting's value and source; warns when tracked `public/.htaccess` (or other tracked deployment files) has local edits; the cron reference notes that cron reads the same private file.
- Verification approach: status tests for environment, file and default sources, a missing per-checkout file, and a modified `.htaccess` warning in a temporary Git checkout.
- Estimate details: driver: Git-based local-edit detection; uncertainty: low.
- Risks or open questions:
  - Impact: secrets leak into status output.
  - Early warning / validation: test that only these non-secret keys are printed.
  - Mitigation: print only the six listed settings and the file path.
- Canonical components/API contracts touched: `scripts/status.php`, task-queue cron reference.

## Stage 6

**Estimate: 0.10**

- Goal: documentation, operator checklist and release verification.
- Dependencies: Stage 5.
- Expected changes: production deploy runbook (moving `SetEnv` into the private file, side-by-side sites, recovery after reset or folder rename, QDB operator checklist with exact commands); CLI reference; private-config help text; `docs/concepts/deployment_configuration.md`; study ledger closeout.
- Verification approach: full suite compared with the Step 4 baseline; links resolve; `git diff --check`; QDB acceptance recorded as pending unless the operator runs it.
- Estimate details: driver: runbook accuracy; uncertainty: baseline noise.
- Risks or open questions:
  - Impact: operators follow stale `SetEnv` guidance.
  - Early warning / validation: search the runbook for `SetEnv` and old guidance.
  - Mitigation: update every mention in the same stage.
- Canonical components/API contracts touched: runbook, CLI reference, concept inventory, study ledger.

## Execution and study checkpoint

Checkpoint: Step 3 handoff, 2026-10-30 UTC; whole-cycle 0.85 reaffirmed
Scope: v1.1 / revised Option A; baseline 4abda3dbd144370a3353781b29e8690e46d47d88; R1 / P2
Stage work originals S1–S6/v1: 0.10 / 0.15 / 0.15 / 0.15 / 0.10 / 0.10, not additive. Stage 6 owns shared release verification.
Rater: Claude (claude-opus-5-5), single exposed rater; additionally inspected the reader inventory (35 files), the Apache harness and the `SiteProfileRegistry` entry point. No scope change.
Authorization: Approved Step 2 received. No enhancement requests. Awaiting Approved Step 3; no branch, commits or implementation.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*
