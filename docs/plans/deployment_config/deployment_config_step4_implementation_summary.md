> **Feature plan:** [Step 1](./deployment_config_step1_solution_assessment.md) · [Step 2](./deployment_config_step2_feature_description.md) · [Step 3](./deployment_config_step3_development_plan.md) · [Step 4](./deployment_config_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/deployment_config; [standing enrollment P2](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
Branch: `feat/deployment-config`; planning commit `e51751cf`
**Cycle estimate: 0.85**

# Deployment Config Step 4 Implementation Summary

## Step 4 entry checkpoint

- Checkpoint: Step 4 entry, 2026-10-30 UTC; whole-cycle **0.85 reaffirmed** from Step 3.
- Scope v1.1 / revised Option A; planning baseline `4abda3db`; implementation starts from `origin/main` `2ee0d198` (17 later upstream commits, one touching `PrivateConfigSchema` by adding an unrelated flag). R1 / P2; Claude (claude-opus-5-5) single exposed rater.
- Original stage-work estimates S1–S6/v1: 0.10 / 0.15 / 0.15 / 0.15 / 0.10 / 0.10, copied from the approved plan, not additive.
- Full-suite and Apache-harness baselines at `2ee0d198` run in a separate worktree; results recorded in Stage 2.

## Stage 1 - One private file location rule

**Original estimate: 0.10**
**Retrospective stage work: 0.08**

- Changes:
  - `PrivateConfig::path(string $projectRoot)`: `FORUM_SECRETS_PATH`, else `../forum-private/<checkout folder>/secrets.php` when it exists, else `../forum-private/secrets.php`. `PrivateConfig::load()`, `scripts/write_private_config.php` (`./v3 private-config`) and `scripts/terminal_llm_editor.php` (TUI) all use it; the three duplicated rules are gone. Help text updated.
  - New `PrivateConfigCommandTest::testPrivateConfigPathPrefersPerCheckoutFileThenSharedDefault`: shared default; per-checkout file wins once it exists; trailing slash; sibling checkout keeps the shared file; values load from the right file; explicit override; and a copied `site-a` checkout's `./v3 private-config view` reports the per-checkout path.
- Verification (all passed): `PrivateConfigCommandTest` 8/8, `PrivateConfigSchemaTest` 5/5, `FeatureFlagEvaluatorTest` 19/19, `SiteProfileRegistryTest` 8/8, `tests/terminal_llm_editor_test.py` and `tests/private_config_review_test.py`.
- Notes: the per-checkout file is used only when it exists, so existing single-site deployments are unaffected.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Stage 2 - Deployment settings lookup

**Original estimate: 0.15**
**Retrospective stage work: 0.12**

- Changes:
  - New `DeploymentSettings` with `value($key, ?$projectRoot)`, `source(...)` and `projectRoot()` for `FORUM_SITE_ID`, `FORUM_REPOSITORY_ROOT`, `FORUM_DATABASE_PATH`, `FORUM_STATIC_HTML_ROOT`, `FORUM_PUBLIC_ARTIFACT_ROOT` and `FORUM_IMAGE_ROOT`. A non-empty environment variable wins, then the checkout's private file, else `null` so the caller applies its existing default. File paths must be absolute strings. Without an explicit root, the lookup uses the checkout containing the code. The parsed file is cached per process, keyed by path, mtime and size.
  - The six keys were added to `PrivateConfigSchema` as non-template settings, so `./v3 private-config view` lists them with their source and generated templates are unchanged.
  - `SiteProfileRegistry::active()` reads through the lookup. An unknown profile set in the private file is an error naming the file and the known profiles; an unknown environment value keeps today's fallback to the default profile, for compatibility.
  - Plan deviation, same outcome: `LocalRepositoryBootstrap` and `PresentationPathResolver` stay unchanged because they produce the fallback defaults. Callers in Stages 3–4 use `DeploymentSettings::value(...) ?: <existing default>`, which keeps every caller's current default exactly.
  - New `DeploymentSettingsTest` (registered in `tests/run.php`): precedence and source for every key, empty environment values ignored, file-driven QDB profile, unknown file profile error, unknown environment fallback, relative and non-string path rejection, per-checkout selection, keys absent from the template.
- Verification (all passed): `DeploymentSettingsTest` 7/7, `SiteProfileRegistryTest` 8/8, `PrivateConfigSchemaTest` 5/5, `PrivateConfigCommandTest` 8/8.
- Notes: this developer machine's private file sets none of the new keys (checked), so local test behavior is unchanged. Baselines still running; recorded in Stage 3.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*
